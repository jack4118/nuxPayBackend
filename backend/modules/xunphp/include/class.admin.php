<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the Database functionality for Admins.
 * Date  11/07/2017.
 **/

class Admin {

    function __construct($db, $setting, $general, $cash, $invoice, $product, $country, $activity, $client, $bonus) {
        $this->db      = $db;
        $this->setting = $setting;
        $this->general = $general;
        $this->cash    = $cash;
        $this->invoice = $invoice;
        $this->product = $product;
        $this->country = $country;
        $this->activity= $activity;
        $this->client  = $client;
        $this->bonus   = $bonus;
    }

    public function adminLogin($params) {

        $db = $this->db;
        $setting = $this->setting;

        //Language Translations.
        $language        = $this->general->getCurrentLanguage();
        $translations    = $this->general->getTranslations();

        // Get the stored password type.
        $passwordEncryption = $setting->getAdminPasswordEncryption();

        $username = trim($params['username']);
        $password = trim($params['password']);

        $db->where('username', $username);
        if($passwordEncryption == "bcrypt") {
            // Bcrypt encryption
            // Hash can only be checked from the raw values
        }
        else if ($passwordEncryption == "mysql") {
            // Mysql DB encryption
            $db->where('password', $db->encrypt($password));
        }
        else {
            // No encryption
            $db->where('password', $password);
        }
        $result = $db->get('admin');

        if (!empty($result)) {
            if($passwordEncryption == "bcrypt") {
                // We need to verify hash password by using this function
                if(!password_verify($password, $result[0]['password']))
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00181"][$language] /* Invalid Login */, 'data' => $data);
            }

            if($result[0]['disabled'] == 1) {
                // Return error if account is disabled
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00182"][$language] /* Your account is disabled. */, 'data' => '');
            }

            $id = $result[0]['id'];

            // Join the permissions table
            $db->orderby('a.priority', 'ASC');
            $db->where('a.site', 'Admin');
            $db->where('a.disabled', 0);
            $db->where('a.type', 'Page', '!=');
            if ($result[0]["role_id"] != 1) {
                $db->where('b.disabled', 0);
                $db->where('b.role_id', $result[0]['role_id']);
                $db->join('roles_permission b', 'b.permission_id=a.id', 'LEFT');
            }

            $db->orderBy('id', "asc");
            $res = $db->get('permissions a', null, 'a.id, a.name, a.type, a.parent_id, a.file_path, a.priority, a.icon_class_name, a.translation_code');

            foreach ($res as &$array) {

                if (!empty($array["translation_code"])){
                    $array["name"] = $translations[$array["translation_code"]][$language];
                }
                $data['permissions'][] = $array;
                
            }

            unset($array);

            $sessionID = md5($result[0]['username'] . time());

            $fields = array('session_id', 'last_login', 'updated_at');
            $values = array($sessionID, date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));

            $db->where('id', $id);
            $db->update('admin', array_combine($fields, $values));

            // This is to get the Pages from the permissions table
            $ids = $db->subQuery();
            $ids->where('disabled', 0);
            $ids->get('roles_permission', null, 'permission_id');

            $db->where('id', $ids, 'in');
            $db->where('type', 'Page');
            $db->where('site', 'Admin');
            $db->where('disabled', 0);
            $pageResults = $db->get('permissions');
            foreach ($pageResults as $array) {
                $data['pages'][] = $array;
            }

            // This is to get the hidden submenu from the permissions table
            $db->where('type', 'Hidden');
            $db->where('site', 'Admin');
            $db->where('disabled', 0);
            $hiddenResults = $db->get('permissions');
            foreach ($hiddenResults as $array){
                $data['hidden'][] = $array;
            }

            $admin['userID']                = $id;
            $admin['username']              = $result[0]['username'];
            $admin['userEmail']             = $result[0]['email'];
            $admin['userRoleID']            = $result[0]['role_id'];
            $admin['sessionID']             = $sessionID;
            $admin['timeOutFlag']           = $setting->getAdminTimeOut();
            $admin['pagingCount']           = $setting->getAdminPageLimit();
            $admin['decimalPlaces']         = $setting->getSystemDecimalPlaces();

            $data['userDetails'] = $admin;

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }
        else
            return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00183"][$language] /* Invalid Login */, 'data' => "");
    }

    public function getAdminList($params) {
        $db              = $this->db;
        $general         = $this->general;
        $language        = $this->general->getCurrentLanguage();
        $translations    = $this->general->getTranslations();

        $searchData      = $params['inputData'];
        $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;

        //Get the limit.
        $limit           = $general->getLimit($pageNumber);
        
        // Means the search params is there
        if (count($searchData) > 0) {
            foreach ($searchData as $k => $v) {
                $dataName = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);
                    
                switch($dataName) {
                    case 'name':
                        $db->where('name', $dataValue);
                            
                        break;
                        
                    case 'username':
                        $db->where('username', $dataValue);
                            
                        break;
                        
                    case 'email':
                        $db->where('email', $dataValue);
                            
                        break;
                        
                    case 'disabled':
                        $db->where('disabled', $dataValue);
                            
                        break;
                }
                unset($dataName);
                unset($dataValue);
            }
        }
        
        $copyDb = $db->copy();
        $db->orderBy("id", "DESC");

        $getRoleName  = '(SELECT name FROM roles WHERE admin.role_id = roles.id) as roleName';

        //Meaning a = admin table
        $result = $db->get("admin", $limit, $getRoleName. ", id, username, name as Name, email, disabled, created_at, last_login");
        // print_r($result);
        $totalRecord = $copyDb->getValue ("admin", "count(*)");

        if (!empty($result)) {
            foreach($result as $value) {
                $admin['id']           = $value['id'];
                $admin['username']     = $value['username'];
                $admin['name']         = $value['Name'];
                $admin['email']        = $value['email'];
                $admin['roleName']     = $value['roleName'];
                $admin['disabled']     = ($value['disabled'] == 1)? 'Yes':'No';
                $admin['createdAt']    = $value['created_at'];
                $admin['price']        = $value['last_login'];

                $adminList[] = $admin;
            }

            $data['adminList']   = $adminList;
            $data['totalPage']   = ceil($totalRecord/$limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord']   = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
        }

        else
        {
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");
        }
    }

    public function getAdminDetails($params) {
        $db             = $this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $id             = trim($params['id']);

        if(strlen($id) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00184"][$language] /* Please Select Admin */, 'data'=> '');

        $db->where('id', $id);
        $result = $db->getOne("admin", "id, username, name, email, disabled as status"); //, role_id as roleID

        if (empty($result)) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00185"][$language] /* Invalid User. */, 'data'=>"");

        foreach ($result as $key => $value) {
            $adminDetail[$key] = $value;
        }

        $data['adminDetail'] = $adminDetail;

        return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
    }

    public function addAdmins($params) {
        $db             = $this->db;
        $setting        = $this->setting;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        //Check the stored password type.
        $passwordFlag = $setting->systemSetting['passwordVerification'];

        $email        = trim($params['email']);
        $fullName     = trim($params['fullName']);
        $username     = trim($params['username']);
        $password     = trim($params['password']);
        $roleID       = trim($params['roleID']);
        $status       = trim($params['status']);

        if(strlen($fullName) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00186"][$language] /* Please Enter Full Name */, 'data'=>"");

        if(strlen($username) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00187"][$language] /* Please Enter Username */, 'data'=>"");

        if(strlen($email) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00188"][$language] /* Please Enter Email */, 'data'=>"");

        if(strlen($password) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00189"][$language] /* Please Enter Password */, 'data'=>"");

        // if(strlen($roleID) == 0)
        //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations[""][$language]/* Please Select a Role */, 'data'=>"");

        if(strlen($status) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00190"][$language] /* Please Choose a Status */, 'data'=>"");

        $db->where('email', $email);
        $result = $db->get('admin');
        if (!empty($result)) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00191"][$language] /* Email Already Used */, 'data'=>"");

        // Retrieve the encrypted password based on settings
        $password = $this->getEncryptedPassword($password);

        $fields = array("email", "password", "username","name", "created_at", "role_id", "disabled", "updated_at");
        $values = array($email, $password, $username, $fullName, date("Y-m-d H:i:s"), $roleID, $status, date("Y-m-d H:i:s"));
        $arrayData = array_combine($fields, $values);
        try{
            $result = $db->insert("admin", $arrayData);
        }
        catch (Exception $e) {
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00192"][$language] /* Failed to add new user */, 'data'=>"");
        }

        return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00102"][$language] /* Successfully Added */, 'data'=>"");
    }

    public function editAdmins($params) {
        $db             = $this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $id       = trim($params['id']);
        $email    = trim($params['email']);
        $fullName = trim($params['fullName']);
        $username = trim($params['username']);
        $roleID   = trim($params['roleID']);
        $status   = trim($params['status']);

        if(strlen($id) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00193"][$language] /* Admin ID does not exist */, 'data'=>"");

        if(strlen($email) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00194"][$language] /* Please Enter Email */, 'data'=>"");

        if(strlen($fullName) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Please Enter Full Name */, 'data'=>"");

        if(strlen($username) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00196"][$language] /* Please Enter Username */, 'data'=>"");

        // if(strlen($roleID) == 0)
        //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations[""][$language]/* Please Select a Role */, 'data'=>"");

        // $db->where('id', $roleID);
        // $result = $db->getOne('roles');
        // if (empty($result))
        //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations[""][$language]/* Invalid Admin Role */, 'data'=>"");

        if(strlen($status) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00197"][$language] /* Please Select a Status */, 'data'=>"");

        $db->where('id', $id);
        $result = $db->getOne('admin');

        if (!empty($result)) {
            $fields    = array("email", "username", "name", "role_id", "disabled", "updated_at");
            $values    = array($email, $username, $fullName, $roleID, $status, date("Y-m-d H:i:s"));

            $arrayData = array_combine($fields, $values);
            $db->where('id', $id);
            $db->update("admin", $arrayData);

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00103"][$language] /* Admin Profile Successfully Updated */, 'data' => "");

        }
        else{
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00198"][$language] /* Invalid Admin */, 'data'=>"");
        }
    }

    public function getEncryptedPassword($password) {
        $db = $this->db;
        $setting = $this->setting;

        // Get the stored password type.
        $passwordEncryption = $setting->getAdminPasswordEncryption();
        if($passwordEncryption == "bcrypt") {
            return password_hash($password, PASSWORD_BCRYPT);
        }
        else if ($passwordEncryption == "mysql") {
            return $db->encrypt($password);
        }
        else return $password;
    }

    public function getClientPortfolioList($params) {

        $db             = $this->db;
        $general        = $this->general;
        $setting        = $this->setting;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $tableName      = "mlm_client_portfolio";
        $joinTable      = "mlm_product_setting";
        $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $clientId       = $params['clientId'];
        $limit          = $general->getLimit($pageNumber);
        $decimalPlaces  = $setting->getSystemDecimalPlaces();
        $column         = array(

            "mlm_client_portfolio.reference_no",
            "mlm_client_portfolio.product_id",
            "mlm_client_portfolio.created_at",
            "mlm_client_portfolio.unit_price",
            "mlm_client_portfolio.status",
            "mlm_product_setting.value"
        );

        if (empty($clientId))
            return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["E00119"][$language] /* Client not found */, 'data' => "");


        $db->where("client_id", $clientId);
        $db->join($joinTable, $joinTable . ".product_id = " . $tableName . ".product_id", "LEFT");
        $db->joinWhere($joinTable, $joinTable . ".name", "Max Cap");
        $db->orderBy($tableName . ".created_at");
        $copyDb = $db->copy();
        $result = $db->get($tableName, $limit, $column);

        if (empty($result))
            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00104"][$language] /* No Results Found*/, 'data' => "");

        $totalRecord = $copyDb->getValue ($tableName, "count(*)");

        foreach($result as $value){

            if (!empty($value['reference_no']))
                $portfolio['referenceNumber']   = $value['reference_no'];
            else
                $portfolio['referenceNumber']   = "-";

            if (!empty($value['product_id']))
                $portfolio['productId']         = $value['product_id'];
            else
                $portfolio['productId']         = "-";

            if (!empty($value['created_at']))
                $portfolio['entryDate']         = $value['created_at'];
            else
                $portfolio['entryDate']         = "-";

            if (!empty($value['unit_price']))
                $portfolio['price']             = number_format($value['unit_price'], $decimalPlaces, '.', '');
            else
                $portfolio['price']             = "-";

            if (!empty($value['value']))
                $portfolio['maxCap']            = $value['value'];
            else
                $portfolio['maxCap']             = "-";

            if (!empty($value['status']))
                $portfolio['status']            = $value['status'];
            else
                $portfolio['status']             = "-";

            $portfolioList[]                = $portfolio;
        }

        $memberDetails = $this->client->getCustomerServiceMemberDetails($clientId);
        $data['memberDetails'] = $memberDetails['data']['memberDetails'];
        $data['portfolioList']      = $portfolioList;
        $data['totalPage']          = ceil($totalRecord/$limit[1]);
        $data['pageNumber']         = $pageNumber;
        $data['totalRecord']        = $totalRecord;
        $data['numRecord']          = $limit[1];

        return array('status' => "ok", 'code' => 0, 'statusMsg'=> "", 'data' => $data);
    }

    public function getMemberList($params) {

        $db             = $this->db;
        $general        = $this->general;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

        //Get the limit.
        $limit              = $general->getLimit($pageNumber);
        $searchData         = $params['searchData'];
        
        // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'username':
                            $db->where('username', $dataValue);
                            break;

                        case 'name':
                            $db->where('name', $dataValue);
                            break;
                            
                        case 'countryName':
                            $db->where('country_id', $dataValue); 
                            break;
                            
                        case 'sponsor':
                            $sponsorID = $db->subQuery();
                            $sponsorID->where('username', $dataValue);
                            $sponsorID->getOne('client', "id");
                            $db->where('sponsor_id', $sponsorID);
                            break;
                            
                        case 'disabled':
                            $db->where('disabled', $dataValue);
                            break;
                            
                        case 'suspended':
                            $db->where('suspended', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

        $getCountryName = "(SELECT name FROM country WHERE country.id=country_id) AS country_name";
        $getSponsorUsername = "(SELECT username FROM client sponsor WHERE sponsor.id=client.sponsor_id) AS sponsor_username";
        $db->where('type', "Client");
        $copyDb = $db->copy();
        $db->orderBy("created_at","DESC");
        $result = $db->get('client', $limit, 'id, username, name, '.$getCountryName.','.$getSponsorUsername.', disabled, suspended, freezed, last_login, created_at');

        if(empty($result))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00105'][$language] /* No Results Found. */, 'data' => "");

        foreach($result as $value) {
            $client['id']              = $value['id'];
            $client['username']        = $value['username'];
            $client['name']            = $value['name'];
            $client['sponsorUsername'] = $value['sponsor_username'] ? $value['sponsor_username'] : "-";
            $client['country']         = $value['country_name'] ? $value['country_name'] : "-";
            $client['disabled']        = $value['disabled'] == 1 ? "Yes" : "No";
            $client['suspended']       = $value['suspended'] == 1 ? "Yes" : "No";
            $client['freezed']         = $value['freezed'] == 1 ? "Yes" : "No";
            $client['lastLogin']       = $value['last_login'] == "0000-00-00 00:00:00" ? "-" : $value['last_login'];
            $client['createdAt']       = $value['created_at'];

            $clientList[] = $client;
        }

        $totalRecords = $copyDb->getValue("client", "count(*)");
        $data['memberList']  = $clientList;
        $data['totalPage']   = ceil($totalRecords/$limit[1]);
        $data['pageNumber']  = $pageNumber;
        $data['totalRecord'] = $totalRecords;
        $data['numRecord']   = $limit[1];
        $data['countryList'] = $db->get('country', null, 'id, name');

        return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
    }

    public function getMemberDetails($params) {
        $db              = $this->db;
        $country         = $this->country;
        $language        = $this->general->getCurrentLanguage();
        $translations    = $this->general->getTranslations();

        $memberId = trim($params['memberId']);

        $cols = array ("name", "email", "phone", "address", "country_id", "state_id", "disabled", "suspended", "freezed");
        $db->where('id', $memberId);
        $member = $db->getOne("client", $cols);

        $countryParams = array('pagination' => 'No');
        $countryList = $country->getCountriesList($countryParams);
        if($countryList['status'] == 'ok')
            $data['countryList'] = $countryList['data']['countriesList'];

        $data['member'] = $member;
        if(empty($member))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['B00106'][$language] /* No Results Found. */, 'data' => "");
        $memberDetails = $this->client->getCustomerServiceMemberDetails($memberId);
        $data['memberDetails'] = $memberDetails['data']['memberDetails'];

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
    }

    public function editMemberDetails($params) {
        $db           = $this->db;
        $client       = $this->client;
        $setting      = $this->setting;
        $activity     = $this->activity;
        $language     = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $memberId     = $params['memberId'];
        $fullName     = $params['fullName'];
        $email        = $params['email'];
        $phone        = $params['phone'];
        $address      = $params['address'];
        $state        = $params['state'];
        $country      = $params['country'];
        $disabled     = $params['disabled'];
        $suspended    = $params['suspended'];
        $freezed      = $params['freezed'];
        $tPassword    = $params['tPassword'];
        // return array($phone);
        //get max and min full name length
        $maxFName     = $setting->systemSetting['maxFullnameLength'];
        $minFName     = $setting->systemSetting['minFullnameLength'];

        //checking client ID
        if(empty($memberId))
            return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00120"][$language] /* Client not found */, 'data' => '');

        //checking email address
        if(!empty($email)) {
            if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorFieldArr[] = array(
                                            'id'  => 'emailError',
                                            'msg' => $translations["E00121"][$language] /* Invalid email format. */
                                        ); 
            }
        }

        if ($activity->creatorType == "Admin") {
            if(strlen($fullName)<$minFName || strlen($fullName)>$maxFName) {
                $errorFieldArr[] = array(
                                            'id'  => 'fullNameError',
                                            'msg' => $translations["E00122"][$language] /* Fullname cannot be less than */ . $minFName . $translations["E00123"][$language] /* or more than */.$maxFName.'.'
                                        );
            }
        
            //checking phone number
            if (!ctype_digit($phone)) {
                $errorFieldArr[] = array(
                                            'id'    => 'phoneError',
                                            'msg'   => $translations["E00124"][$language] /* Invalid mobile number. */
                                        );
            }

            if($disabled != 0 && $disabled != 1) {
                $errorFieldArr[] = array(
                                            'id'  => 'disabledError',
                                            'msg' => $translations["E00125"][$language] /* Invalid value */
                                        );
            }

            if($suspended != 0 && $suspended != 1) {
                $errorFieldArr[] = array(
                                            'id'  => 'suspendedError',
                                            'msg' => $translations["E00126"][$language] /* Invalid value */
                                        );
            }

            if($freezed != 0 && $freezed != 1) {
                $errorFieldArr[] = array(
                                            'id'  => 'freezedError',
                                            'msg' => $translations["E00127"][$language] /* Invalid value */
                                        );
            }
        }

        //checking transaction password
        if ($activity->creatorType == "Member") {
            if (empty($tPassword)) {
                $errorFieldArr[] = array(
                                            'id'    => 'tPasswordError',
                                            'msg'   => $translations["E00128"][$language] /* Please enter transaction password */
                                        );
            } else {
                $result = $client->verifyTransactionPassword($memberId, $tPassword);
                if($result['status'] != "ok") {
                    $errorFieldArr[] = array(
                                                'id'  => 'tPasswordError',
                                                'msg' => $translations["E00129"][$language] /* Invalid password */
                                            );
                }
            }
        }

        $data['field'] = $errorFieldArr;
        if($errorFieldArr)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);

        if ($activity->creatorType == "Admin") {
            $updateData = array(
                                    "name"       => $fullName,
                                    "email"      => $email,
                                    "phone"      => $phone,
                                    "address"    => $address,
                                    "country_id" => $country,
                                    "disabled"   => $disabled,
                                    "suspended"  => $suspended,
                                    "freezed"    => $freezed
                                );
            $db->where('id', $memberId);
            $updateResult = $db->update('client', $updateData);
        }

        if ($activity->creatorType == "Member") {
            $updateData = array(
                                    "email"      => $email,
                                    "address"    => $address
                                );
            $db->where('id', $memberId);
            $updateResult = $db->update('client', $updateData);
        }

        if($updateResult)
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");

        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00131"][$language] /* Update failed */, 'data' =>"");
    }

    public function changeMemberPassword($params) {
        $db           = $this->db;
        $client       = $this->client;
        $setting      = $this->setting;
        $activity     = $this->activity;
        $language     = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $memberID     = $params['memberId'];
        $newPassword  = $params['newPassword'];
        $passwordCode = $params['passwordType'];

        $minPass      = $setting->systemSetting['minPasswordLength'];
        // Get password encryption type
        $passwordEncryption  = $setting->getMemberPasswordEncryption();

        if (empty($memberID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00132"][$language] /* Member not found */, 'data'=> "");

        // checking client
        $db->where('id', $memberID);
        $clientDetails = $db->getValue('client', 'username');
        if(empty($clientDetails))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00133"][$language] /* Member not found */, 'data' => "");

        $memberId      = $memberID;
        $username    = $clientDetails;

        if (empty($passwordCode)) {
            $errorFieldArr[] = array(
                                        'id'  => 'passwordTypeError',
                                        'msg' => $translations["E00134"][$language] /* Please select a password type */
                                    );
        } else {
            if ($passwordCode == 1) {
                $passwordType  = "password";
            } else if ($passwordCode == 2) {
                $passwordType  = "transaction_password";
            } else {
                $errorFieldArr[] = array(
                                            'id'  => 'passwordTypeError',
                                            'msg' => $translations["E00135"][$language] /* Invalid password type */
                                        );
            }
        }
        // get error msg type
        if ($passwordType == "password") {
            $idName        = 'Password';
            $msgFieldB     = 'Password';
            $msgFieldS     = 'password';
            $titleCode     = 'T00013';
            $activityCode  = 'L00013';
            $transferType  = 'Reset Password';
            $maxLength     = $maxPass;
            $minLength     = $minPass;
        } else if ($passwordType == "transaction_password") {
            $idName        = 'TPassword';
            $msgFieldB     = 'Transaction password';
            $msgFieldS     = 'transaction password';
            $titleCode     = 'T00014';
            $activityCode  = 'L00014';
            $transferType  = 'Reset Transaction Password';
            $maxLength     = $maxTPass;
            $minLength     = $minTPass;
        }
        if (empty($newPassword)) {
            $errorFieldArr[] = array(
                                        'id'  => "new".$idName."Error",
                                        'msg' =>  $translations["E00136"][$language] /* Please enter new */ . " " . $msgFieldS . "."
                                    );
        } elseif (strlen($newPassword)<$minPass) {
            $errorFieldArr[] = array(
                                        'id'  => "new".$idName."Error",
                                        'msg' => $msgFieldB . " " . $translations["E00137"][$language] /* cannot be less than */ . " " . $minLength . " " . $translations["E00138"][$language] /* or more than */ . " " . $maxLength . "."
                                    );
        }
        // Retrieve the encrypted password based on settings
        $newEncryptedPassword = $client->getEncryptedPassword($newPassword);
        $db->where('id', $memberId);
        $result = $db->getOne('client', $passwordType);
        if (empty($result[$passwordType])) 
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00139"][$language] /* Member not found */, 'data'=> "");

        if ($passwordEncryption == "bcrypt") {
            // We need to verify hash password by using this function
            if(password_verify($newPassword, $result[$passwordType])) {
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."Error",
                                            'msg' => $translations["E00140"][$language] /* Please enter different */ . " $msgFieldS."
                                        );
            }
        } else {
            if ($newEncryptedPassword == $result[$passwordType]) {
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."Error",
                                            'msg' => $translations["E00141"][$language] /* cannot be less than */ . " $msgFieldS."
                                        );
            }
        }
        $data['field'] = $errorFieldArr;
        if($errorFieldArr)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00142"][$language] /* Data does not meet requirements */, 'data'=>$data);

        $updateData = array($passwordType => $newEncryptedPassword);
        $db->where('id', $memberId);
        $updateResult = $db->update('client', $updateData);
        if(!$updateResult)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00143"][$language] /* Update failed */, 'data' => "");

        // insert activity log
        $activityData = array('user' => $username);

        $activityRes = $activity->insertActivity($transferType, $titleCode, $activityCode, $activityData, $memberId);
        // Failed to insert activity
        if(!$activityRes)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $passwordCode);
    }

    public function getRankMaintain($params) {

        $db             = $this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $tableName      = "mlm_bonus, rank";
        $column         = array(

            "mlm_bonus.name AS mlm_bonus_name",
            "rank.name AS rank_name"
        );

        $db->where("mlm_bonus.allow_rank_maintain", "1");
        $db->where("mlm_bonus.disabled", "0");
        $db->orderBy('rank.priority', 'ASC');
        $result = $db->get($tableName, NULL, $column);

        if (empty($result))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00107"][$language] /* No Results Found. */, 'data'=>"");

        $data = [];
        $check = [];
        foreach($result as $value){

            if (!in_array($value['mlm_bonus_name'], $check)) {
                $data['rankMaintain'][$value['mlm_bonus_name']][0] = $value['rank_name'];
                $check[] = $value['mlm_bonus_name'];
            }
            else{
                $data['rankMaintain'][$value['mlm_bonus_name']][] = $value['rank_name'];
            }
        }

        $memberDetails = $this->client->getCustomerServiceMemberDetails($params['clientId']);
        $data['memberDetails'] = $memberDetails['data']['memberDetails'];
        return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
    }

    public function updateRankMaintain($params) {

        $db                 = $this->db;
        $activity           = $this->activity;
        $language           = $this->general->getCurrentLanguage();
        $translations       = $this->general->getTranslations();
        $tableName          = "client_rank";
        $bonusNameAndRank   = $params['bonusNameAndRank'];
        $clientID           = trim($params['clientId']);
        
        if (empty($clientID) || !is_numeric($clientID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00145"][$language] /* Client not found. */, 'data'=>"");
        
        // Check client
        $db->where('id', $clientID);
        $username = $db->getValue('client', 'username');
        if(!$username)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00146"][$language] /* Client not found. */, 'data'=>"");

        if (empty($bonusNameAndRank))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00147"][$language] /* Invalid rank. */, 'data'=> "");
        
        foreach($bonusNameAndRank as $v) {
            $rankNameArr[]  = $v['rank'];
            $bonusNameArr[] = $v['bonusName'];
        }
        // Remove duplicate rank names
        $rankNameArr = array_unique($rankNameArr);
        
        // To be used when insert/update client rank
        // To map bonus names with rank id
        $db->where('name', $rankNameArr, 'IN');
        $rankResult = $db->get('rank', null, 'id, name');
        if (empty($rankResult))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00148"][$language] /* Invalid rank. */, 'data'=> "");
        
        foreach($rankResult as $v) {
            $rankName[$v['name']] = $v['id'];
            $rankIDArr[] = $v['id'];
        }
        
        // Remove duplicate rank id
        $rankIDArr = array_unique($rankIDArr);
        
        // To be used when updating client setting
        // To map bonus name with type and
        // map name with value
        $db->where('rank_id', $rankIDArr, 'IN');
        $rankResult = $db->get('rank_setting', null, 'rank_id, name, value, type');
        if (empty($rankResult))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00149"][$language] /* Invalid rank. */, 'data'=> "");
        
        foreach($rankResult as $v) {
            $rankSetting[$v['rank_id']][$v['name']]['type'] = $v['type'];
            $rankSetting[$v['rank_id']][$v['name']]['value'] = $v['value'];
        }
        
        $db->where('client_id', $clientID);
        $db->where('type', $bonusNameArr, 'IN');
        $clientRankResult = $db->get('client_rank');
        
        // If no data found, means can just insert into the client rank table
        if(empty($clientRankResult)) {
            foreach($bonusNameAndRank as $v) {
                
                $insertData = array(
                    'client_id' => $clientID,
                    'type'      => $v['bonusName'],
                    'rank_id'   => $rankName[$v['rank']],
                    'created_at'=> $db->now(),
                    'updated_at'=> $db->now()
                );
                
                $insertClientRankResult = $db->insert('client_rank', $insertData);
                if(!$insertClientRankResult)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00150"][$language] /* Failed to update clienk rank. */, 'data'=> "");
            }
            // Done inserting
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00108"][$language] /* Successfully update clienk rank. */, 'data'=> "");
        }
        
        // In the event that the data in client_rank table exist
        // Mapping of bonus name to rank
        foreach($clientRankResult as $v) {
            $bonusName[$v['type']] = $v['rank_id'];
        }
        
        // Mapping of bonus name to rank
        foreach($bonusNameAndRank as $v) {
            
            $rankID = $rankName[$v['rank']];
            
            // existing data, perform update
            if($bonusName[$v['bonusName']]) {
                
                $updateData = array(
                    'rank_id'   => $rankID,
                    'updated_at'=> $db->now()
                );
                
                $db->where('type', $v['bonusName']);
                $db->where('client_id', $clientID);
                $updateClientRankResult[] = $db->update('client_rank', $updateData);
                if(!$updateClientRankResult)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00151"][$language] /* Failed to update clienk rank. */, 'data'=> "");
            }
            // perform insert
            else {
                $insertData = array(
                    'client_id' => $clientID,
                    'type'      => $v['bonusName'],
                    'rank_id'   => $rankID,
                    'created_at'=> $db->now(),
                    'updated_at'=> $db->now()
                );
                
                $insertClientRankResult[] = $db->insert('client_rank', $insertData);
                if(!$insertClientRankResult)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00152"][$language] /* Failed to update clienk rank. */, 'data'=> "");
            }
            
            // update client setting
            
            // Update bonus rank in client setting
            $db->where('type', 'Bonus Rank');
            $db->where('name', $v['bonusName']);
            $db->where('client_id', $clientID);
            $updateClientRankResult = $db->update('client_setting', array('value' => $rankID));
            if(!$updateClientRankResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00153"][$language] /* Failed to update clienk rank. */, 'data'=> "");
            
            // Update bonus value in client setting
            $db->where('type', $rankSetting[$rankID][$v['bonusName']]['type']);
            $db->where('name', $v['bonusName']);
            $db->where('client_id', $clientID);
            $updateClientRankResult = $db->update('client_setting', array('value' => $rankSetting[$rankID][$v['bonusName']]['value']));
            if(!$updateClientRankResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00154"][$language] /* Failed to update clienk rank. */, 'data'=> "");
        }

        // insert activity log
        $titleCode      = 'T00008';
        $activityCode   = 'L00008';
        $transferType   = 'Change Rank';
        $activityData   = array('user' => $username);

        $activityRes = $activity->insertActivity($transferType, $titleCode, $activityCode, $activityData, $clientID);
        // Failed to insert activity
        if(!$activityRes)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00155"][$language] /* Failed to insert activity. */, 'data'=> "");
        
        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00109"][$language] /* Successfully update clienk rank. */, 'data'=> '');
    }

    public function getInvoiceList($params) {

        $db             = $this->db;
        $general        = $this->general;
        $setting        = $this->setting;
        $activity       = $this->activity;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $tableName      = "mlm_invoice";
        $searchData     = $params['searchData'];
        $offsetSecs     = trim($params['offsetSecs']);
        $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $limit          = $general->getLimit($pageNumber);
        $decimalPlaces  = $setting->getSystemDecimalPlaces();
        $column         = array(

            "id",
            "(SELECT username FROM client WHERE id = client_id) AS username",
            "(SELECT name FROM client WHERE id = client_id) AS fullname",
            "invoice_no",
            "total_amount",
            "created_at"
        );


        // Means the search params is there
        if (count($searchData) > 0) {
            foreach ($searchData as $k => $v) {
                $dataName = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);

                switch($dataName) {
                    case 'fullname':
                        $sq = $db->subQuery();
                        $sq->where("name", $dataValue);
                        $sq->get("client", NULL, "id");
                        $db->where("client_id", $sq, "in");

                        break;

                    case 'username':
                        $sq = $db->subQuery();
                        $sq->where("username", $dataValue);
                        $sq->getOne("client", "id");
                        $db->where("client_id", $sq);

                        break;

                    case 'transactionDate':
                        // Set db column here
                        $columnName = 'created_at';

                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            if($dateFrom < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                            $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
                        }
                        if(strlen($dateTo) > 0) {
                            if($dateTo < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00157"][$language] /* Invalid date. */, 'data'=>"");

                            if($dateTo < $dateFrom)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                            $dateTo += 86399;
                            $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
                        }

                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;

                    default:
                        $db->where($dataName, $dataValue);

                }
                unset($dataName);
                unset($dataValue);
            }
        }

        if ($activity->creatorType == "Member")
            $db->where("client_id", $activity->creatorID);

        $db->orderBy("created_at", "DESC");
        $copyDb = $db->copy();
        $totalRecord = $copyDb->getValue($tableName, "count(*)");

        $invoiceList = $db->get($tableName, $limit, $column);

        if (empty($invoiceList))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00110"][$language] /* No Results Found. */, 'data' => "");

        foreach ($invoiceList as $invoice) {

            if (!empty($invoice['id']))
                $invoiceListing['id']                   = $invoice['id'];
            else
                $invoiceListing['id']                   = "-";


            if (!empty($invoice['invoice_no']))
                $invoiceListing['invoiceNumber']        = $invoice['invoice_no'];
            else
                $invoiceListing['invoiceNumber']        = "-";

            if ($activity->creatorType == "Admin") {
                if (!empty($invoice['username']))
                    $invoiceListing['username']         = $invoice['username'];
                else
                    $invoiceListing['username']         = "-";

                if (!empty($invoice['fullname']))
                    $invoiceListing['fullname']         = $invoice['fullname'];
                else
                    $invoiceListing['fullname']         = "-";
            }

            if (!empty($invoice['total_amount']))
                $invoiceListing['totalAmount']          = number_format($invoice['total_amount'], $decimalPlaces, '.', '');
            else
                $invoiceListing['totalAmount']          = "-";

            if (!empty($invoice['created_at']))
                $invoiceListing['createdAt']            = $general->formatDateTimeString($offsetSecs, $invoice['created_at'], $format = "d/m/Y h:i:s A");
            else
                $invoiceListing['createdAt']            = "-";

            $invoicePageListing[] = $invoiceListing;
        }


        $data['invoicePageListing']     = $invoicePageListing;
        $data['totalPage']              = ceil($totalRecord/$limit[1]);
        $data['pageNumber']             = $pageNumber;
        $data['totalRecord']            = $totalRecord;
        $data['numRecord']              = $limit[1];

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00111"][$language] /* Invoice List successfully retrieved. */, 'data' => $data);
    }

    public function getPortfolioList($params) {

        $db             = $this->db;
        $general        = $this->general;
        $setting        = $this->setting;
        $activity       = $this->activity;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        
        $searchData     = $params['searchData'];
        $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $limit          = $general->getLimit($pageNumber);
        $decimalPlaces  = $setting->getSystemDecimalPlaces();
        
        $tableName      = "mlm_client_portfolio";
        $column         = array(

            "reference_no",
            "created_at",
            "(SELECT username FROM client WHERE id = client_id) AS username",
            "(SELECT name FROM client WHERE id = client_id) AS fullname",
            "(SELECT name FROM rank WHERE id = (SELECT value FROM mlm_product_setting WHERE mlm_product_setting.product_id = mlm_client_portfolio.product_id AND name = 'rankID')) AS package",
            "bonus_value",
            "(product_price * unit_price) AS product_price",
            "expire_at"
        );
        
        // Means the search params is there
        if (count($searchData) > 0) {
            foreach ($searchData as $k => $v) {
                $dataName = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);
                    
                switch($dataName) {
                    case 'fullName':
                        $sq = $db->subQuery();
                        $sq->where("name", $dataValue);
                        $sq->get("client", NULL, "id");
                        $db->where("client_id", $sq, "in");
                            
                        break;
                        
                    case 'username':
                        $sq = $db->subQuery();
                        $sq->where("username", $dataValue);
                        $sq->getOne("client", "id");
                        $db->where("client_id", $sq);
                        
                        break;
                        
                    case 'package':
                        $sq = $db->subQuery();
                        $sq->where("name", $dataValue);
                        $sq->getOne("rank", "id");
                        $db->where("product_id", $sq);
                            
                        break;
                        
                    case 'bonusValue':
                        $db->where("bonus_value", $dataValue);
                            
                        break;
                        
                    case 'entryDate':
                        // Set db column here
                        $columnName = 'created_at';
                            
                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            if($dateFrom < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00159"][$language] /* Invalid date. */, 'data'=>"");
                                
                            $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
                        }
                        if(strlen($dateTo) > 0) {
                            if($dateTo < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                
                            if($dateTo < $dateFrom)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00161"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                            $dateTo += 86399;
                            $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
                        }
                            
                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;
                        
                    case 'maturityDate':
                        // Set db column here
                        $columnName = 'expire_at';
                            
                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            if($dateFrom < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00162"][$language] /* Invalid date. */, 'data'=>"");
                                
                            $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
                        }
                        if(strlen($dateTo) > 0) {
                            if($dateTo < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00163"][$language] /* Invalid date. */, 'data'=>"");
                                
                            if($dateTo < $dateFrom)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00164"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                            $dateTo += 86399;
                            $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
                        }
                            
                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;

                    default:
                        $db->where($dataName, $dataValue);

                }
                unset($dataName);
                unset($dataValue);
            }
        }

        if ($activity->creatorType == "Member")
            $db->where("client_id", $activity->creatorID);

        $copyDb = $db->copy();
        $totalRecord = $copyDb->getValue($tableName, "count(*)");
        $portfolioList = $db->get($tableName, $limit, $column);

        if (empty($portfolioList))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

        foreach ($portfolioList as $portfolio) {
            $portfolioListing['reference_no']           = $portfolio['reference_no']?:'-';
            $portfolioListing['createdAt']              = $general->formatDateTimeToString($portfolio['created_at'])?:'-';
            $portfolioListing['username']               = $portfolio['username']?:'-';
            $portfolioListing['fullname']               = $portfolio['fullname']?:'-';
            $portfolioListing['package']                = $portfolio['package']?:'-';
            $portfolioListing['bonusValue']             = $portfolio['bonus_value']?number_format($portfolio['bonus_value'], 0, '.', ''):'-';
            $portfolioListing['productPrice']           = $portfolio['product_price']?number_format($portfolio['product_price'], $decimalPlaces, '.', ''):'-';
            $portfolioListing['expireAt']               = $general->formatDateTimeToString($portfolio['expire_at'])?:'-';
            
            $portfolioPageListing[] = $portfolioListing;
        }

        $data['portfolioPageListing']       = $portfolioPageListing;
        $data['totalPage']                  = ceil($totalRecord/$limit[1]);
        $data['pageNumber']                 = $pageNumber;
        $data['totalRecord']                = $totalRecord;
        $data['numRecord']                  = $limit[1];

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00113"][$language] /* Portfolio List successfully retrieved */, 'data' => $data);
    }

    public function getInvoiceDetail($params) {

        $db             = $this->db;
        $setting        = $this->setting;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        
        $decimalPlaces  = $setting->getSystemDecimalPlaces();
        
        $tableName      = "mlm_invoice_item";
        $invoiceId      = trim($params['invoiceId']);
        if(strlen($invoiceId) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00165"][$language] /* Invoice not found */, 'data' => "");
        
        $column         = array(

            "(SELECT username FROM client WHERE id = (SELECT client_id FROM mlm_invoice WHERE id = invoice_id)) AS username",
            "(SELECT name FROM client WHERE id = (SELECT client_id FROM mlm_invoice WHERE id = invoice_id)) AS name",
            "(SELECT invoice_no FROM mlm_invoice WHERE id = invoice_id) AS invoice_number",
            "(SELECT name FROM rank WHERE id = (SELECT value FROM mlm_product_setting WHERE name = 'rankID' AND mlm_product_setting.product_id = " . $tableName . ".product_id)) AS product_name",
            "(SELECT category FROM mlm_product WHERE mlm_product.id = " . $tableName . ".product_id) AS category",
            "bonus_value",
            "unit_price AS product_price",
            "count(*) AS quantity",
            "(bonus_value * count(*)) AS total_bonus_value",
            "(product_price * count(*)) AS total_product_price",
            "(SELECT total_amount FROM mlm_invoice WHERE id = invoice_id) AS grand_total",
            "(SELECT created_at FROM mlm_invoice WHERE id = invoice_id) AS transaction_date",

        );

        $db->groupBy("product_id");
        $db->where("invoice_id", $invoiceId);
        $result = $db->get($tableName, NULL, $column);

        if (empty($result))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00114"][$language] /* No Results Found. */, 'data' => "");

        foreach($result as $invoiceDetail){

            $invoicePageDetail['productName']           = $invoiceDetail['product_name'];
            $invoicePageDetail['bonusValue']            = $invoiceDetail['bonus_value']?number_format($invoiceDetail['bonus_value'], $decimalPlaces, '.', ''):0;
            $invoicePageDetail['productPrice']          = $invoiceDetail['product_price']?number_format($invoiceDetail['product_price'], $decimalPlaces, '.', ''):0;
            $invoicePageDetail['quantity']              = $invoiceDetail['quantity'];
            $invoicePageDetail['total_bonus_value']     = $invoiceDetail['total_bonus_value']?number_format($invoiceDetail['total_bonus_value'], $decimalPlaces, '.', ''):0;
            $invoicePageDetail['total_product_price']   = $invoiceDetail['total_product_price']?number_format($invoiceDetail['total_product_price'], $decimalPlaces, '.', ''):0;

            $data['invoicePageDetail'][]                = $invoicePageDetail;
            $data['name']                               = $invoiceDetail['name'];
            $data['username']                           = $invoiceDetail['username'];
            $data['invoiceNumber']                      = $invoiceDetail['invoice_number'];
            $data['grandTotal']                         = $invoiceDetail['grand_total']?number_format($invoiceDetail['grand_total'], $decimalPlaces, '.', ''):0;
            $data['transactionDate']                    = $invoiceDetail['transaction_date'];
            $data['category']                           = $invoiceDetail['category'];
        }

        //get the pin code, dont get the pin code if the product type is package

        if ($data['category'] == "Pin") {
            $column = array(

                "(SELECT name FROM rank WHERE id = (SELECT value FROM mlm_product_setting WHERE name = 'rankID' AND mlm_product_setting.product_id = " . $tableName . ".product_id)) AS product_name",
                "(SELECT code FROM mlm_pin WHERE mlm_pin.belong_id = " . $tableName . ".belong_id) AS pin_code"
            );
            $db->where("invoice_id", $invoiceId);
            $result = $db->get($tableName, NULL, $column);
            $data['productPin'] = $result;
        }

        //get the credit paid by client
        $tableName  = "mlm_invoice_item_payment";
        $column     = array(
            "credit_type",
            "SUM(amount) AS amount_paid"
        );
        $db->groupBy("credit_type");
        $db->where("invoice_id", $invoiceId);
        $result = $db->get($tableName, NULL, $column);

        if (!empty($result)){

            foreach($result as $key => &$value){
                $value['amount_paid'] = $value['amount_paid'] ? number_format($value['amount_paid'], $decimalPlaces, '.', ''):0;

                unset($value);
            }

            $data['credit'] = $result;

        }


        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00115"][$language] /* Invoice detail successfully retrieved */, 'data' => $data);
    }

    public function getProductDetail($params) {

        $db             = $this->db;
        $general        = $this->general;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $tableName      = "mlm_product";
        $searchData     = $params['searchData'];
        $offsetSecs     = trim($params['offsetSecs']);
        $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $limit          = $general->getLimit($pageNumber);
        $column         = array(

            "mlm_product.name AS product_name",
            "mlm_product.code",
            "mlm_product.category",
            "mlm_product.price",
            "mlm_product.status",
            "mlm_product.translation_code",
            "mlm_product.active_at",
            "mlm_product.expire_at",
            "mlm_product_setting.name AS setting_name",
            "mlm_product_setting.value"

        );

        if (count($searchData) > 0) {
            foreach ($searchData as $array) {
                foreach ($array as $key => $value) {
                    if ($key == 'dataName') {
                        $dbColumn = $tableName . "." .$value;
                    } else if ($key == 'dataValue') {
                        foreach ($value as $innerVal) {
                            $db->where($dbColumn, $innerVal);
                        }
                    }
                }
            }
        }

        $copyDb = $db->copy();
        $db->join("mlm_product_setting", "mlm_product_setting.product_id = mlm_product.id", "LEFT");
        $totalRecord = $copyDb->getValue($tableName, "count(*)");
        $productDetail = $db->get($tableName, null, $column);

        $newProductDetail       = [];
        $productArray           = [];
        $newKey                 = -1;
        foreach($productDetail as $productDetailKey => $product){

            if (!in_array($product["product_name"], $productArray)) {

                ++$newKey;
                $newProductDetail[$newKey]["product_name"]       = $product["product_name"];
                $newProductDetail[$newKey]["code"]               = $product["code"];
                $newProductDetail[$newKey]["category"]           = $product["category"];
                $newProductDetail[$newKey]["price"]              = $product["price"];
                $newProductDetail[$newKey]["status"]             = $product["status"];
                $newProductDetail[$newKey]["translation_code"]   = $product["translation_code"];
                $newProductDetail[$newKey]["active_at"]          = $product["active_at"];
                $newProductDetail[$newKey]["expire_at"]          = $product["expire_at"];
            }
            $newProductDetail[$newKey][$product["setting_name"]] = $product["value"];
            $productArray [] = $product["product_name"];

        }

        $data['productDetail']          = $newProductDetail;
        $data['totalPage']              = ceil($totalRecord/$limit[1]);
        $data['pageNumber']             = $pageNumber;
        $data['totalRecord']            = $totalRecord;
        $data['numRecord']              = $limit[1];

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00116"][$language] /* Successfully retrieved product detail */, 'data' => $data);
    }

    public function getActivityLogList($params) {
        $db             = $this->db;
        $general        = $this->general;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $searchData     = $params['searchData'];
        $memberId       = $params['memberId'] ? $params['memberId'] : "";
        $dateToday      = date("Ymd");

        //Get the limit.
        $limit = $general->getLimit($pageNumber);

        // Means the search params is there
        if (count($searchData) > 0) {
            foreach ($searchData as $k => $v) {
                $dataName = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);
                        
                switch($dataName) {
                    case 'username':
                        $searchMemberId = $db->subQuery();
                        $searchMemberId->where('username', $dataValue);
                        $searchMemberId->getOne('client', "id");
                        $db->where('client_id', $searchMemberId); 
                        break;

                    case 'creatorUsername':
                        $searchAdminId = $db->subQuery();
                        $searchAdminId->where('username', $dataValue);
                        $searchAdminId->getOne('admin', "id");
                        $db->where('creator_id', $searchAdminId);
                        break;
                        
                    case 'clientId':
                        $db->where('client_id', $dataValue);  
                        break;
                            
                    case 'activityType':
                        $db->where('title', $dataValue);
                        break;

                    case 'searchDate':
                        if(strlen($dataValue) == 0)
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00166"][$language] /* Please specify a date */, 'data'=>"");
                            
                        if($dataValue < 0)
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00167"][$language] /* Invalid date. */, 'data'=>"");
                            
                            $dateToday = date('Ymd', $dataValue);
                    
                            
                    case 'searchTime':
                        // Set db column here
                        $columnName = 'created_at';

                        if(strlen($dataValue) == 0)
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00168"][$language] /* Please specify a date */, 'data'=>"");

                        if($dataValue < 0)
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00169"][$language] /* Invalid date. */, 'data'=>"");

                        $dataValue = date('Y-m-d', $dataValue);

                        $dateFrom = trim($v['timeFrom']);
                        $dateTo = trim($v['timeTo']);
                        if(strlen($dateFrom) > 0) {
                            $dateFrom = strtotime($dataValue.' '.$dateFrom);
                        if($dateFrom < 0)
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00170"][$language] /* Invalid date. */, 'data'=>"");

                            $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
                        }
                        if(strlen($dateTo) > 0) {
                            $dateTo = strtotime($dataValue.' '.$dateTo);
                        if($dateTo < 0)
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00171"][$language] /* Invalid date. */, 'data'=>"");

                        if($dateTo < $dateFrom)
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00172"][$language] /* Time from cannot be later than time to */, 'data'=>$data);

                            $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
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

        if (!empty($memberId))
            $db->where("a.client_id", $memberId);


        $db->orderBy("created_at", "DESC");
        $copyDb = $db->copy();

        $getAdminId        = '(SELECT id FROM admin WHERE a.creator_id = admin.id) as adminId';
        $getMemberId       = '(SELECT id FROM client WHERE a.client_id = client.id) as memberId';
        $getAdminUsername  = '(SELECT username FROM admin WHERE a.creator_id = admin.id) as adminUsername';
        $getMemberUsername = '(SELECT username FROM client WHERE a.client_id = client.id) as clientUsername';

        try {
            $result = $db->get('activity_log_'.$dateToday." a", $limit, $getMemberUsername. "," .$getAdminUsername. "," .$getMemberId. "," .$getAdminId. ", client_id, title, translation_code, data, creator_id, creator_type, created_at");
        }
        catch (Exception $e) {
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00117"][$language] /* No Results Found. */, 'data' => "");
        }

        if ($result) {
            foreach($result as $value) {

                $activity['activityType'] = $value['title'];
                $translationCode          = $value['translation_code'];
                $activityData             = (array) json_decode($value['data'], true);

                $db->where('code', $translationCode);
                $content     = $db->getValue('language_translation', 'content');

                foreach($activityData as $key => $val) {
                    $oriKeyWord = '%%'.$key.'%%';
                    $content = str_replace($oriKeyWord, $val, $content);                       
                }
                //pieces chop content where ' %%' is at.
                //pieces2 chop pieces from array position [1] onwards where '%%' is at.
                //pieces3 chop pieces at array position [0] only where '%%' is at.
                //pieces3 is using to detect if %% is the first word.
                $pieces = explode(" %%", $content);

                if(isset($pieces[1])) {
                    $pieces3 = explode("%%", $pieces[0]);
                    if(isset($pieces3[1]))
                        $piecesList[] = $pieces3[1];

                    foreach(array_slice($pieces, 1) as $val) {
                        $pieces2 = explode("%%", $val);
                        $piecesList[] = $pieces2[0];
                    }
                            
                    foreach($piecesList as $key) {
                        $oriKeyWord = '%%'.$key.'%%';
                        $content = str_replace($oriKeyWord, '', $content);                       
                    }
                }

                $activity['description'] = $content;
                $activity['created_at']  = $general->formatDateTimeToString($value['created_at'], "d/m/Y h:i:s A");

                if ($value['creator_type'] == "Admin")
                    $activity['doneBy']  = $value['adminUsername'];
                else if ($value['creator_type'] == "Member")
                    $activity['doneBy']  = $value['clientUsername'];
                else
                    $activity['doneBy']  = "-";

                $activity['memberID']    = $value['memberId'];
                $activity['username']    = $value['clientUsername'];

                $activityList[]          = $activity;
            }

            // This is to get the title for the search select option
            $dropDownResult = $db->get('activity_log_'.$dateToday, null, "title");
            if(empty($dropDownResult))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00173"][$language] /* Failed to get title for search option */, 'data' => '');
                
            foreach($dropDownResult as $value) {
                $searchBarData['activityType'] = $value['title'];
                $searchBarDataList[]           = $searchBarData;
            }

            $totalRecord = $copyDb->getValue ('activity_log_'.$dateToday . " a", "count(id)");

            // remove duplicate command. Then sort it alphabetically
            $searchBarDataList = array_map("unserialize", array_unique(array_map("serialize", $searchBarDataList)));
            sort($searchBarDataList);

            $data['activityLogList']  = $activityList;
            $data['activityTypeList'] = $searchBarDataList;
            $data['totalPage']        = ceil($totalRecord/$limit[1]);
            $data['pageNumber']       = $pageNumber;
            $data['totalRecord']      = $totalRecord;
            $data['numRecord']        = $limit[1];
                    
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        } else {
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00118"][$language] /* No Results Found. */, 'data'=> "");
        }
    }

    public function getLanguageTranslationList($params) {
        $db          = $this->db;
        $general     = $this->general;
            
        $pageNumber  = $params['pageNumber'] ? $params['pageNumber'] : 1;

        //Get the limit.
        $limit       = $general->getLimit($pageNumber);

        $searchData  = json_decode($languageCodeParams['searchData']);
        if (count($searchData) > 0) {
            foreach ($searchData as $array) {                  
                foreach ($array as $key => $value) {
                    if ($key == 'dataName') {
                        $dbColumn = $value;
                    } else if ($key == 'dataValue') {
                        foreach ($value as $innerVal) {
                            $db->where($dbColumn, $innerVal);
                        }
                    }
                }
            }
        }
        $copyDb = $db->copy();
        $db->orderBy("id", "DESC");
        $result = $db->get("language_translation", $limit);

        $totalRecord = $copyDb->getValue ("language_translation", "count(id)");
            foreach($result as $value) {
                $language['id']           = $value['id'];
                $language['contentCode']  = $value['code'];
                $language['language']     = $value['language'];
                $language['module']       = $value['module'];
                $language['site']         = $value['site'];
                $language['category']     = $value['type'];
                $language['content']      = $value['content'];

                $languageList[] = $language;
                    
            }


                $data['languageCodeList'] = $languageList;
                $data['totalPage']        = ceil($totalRecord/$limit[1]);
                $data['pageNumber']       = $pageNumber;
                $data['totalRecord']      = $totalRecord;
                $data['numRecord']        = $limit[1];
                
        return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
    }

    public function getLanguageTranslationData($params) {
        $db             = $this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $id             = trim($params['id']);

        if(strlen($id) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00174"][$language] /* Please Select A Language Code */, 'data'=> '');
            
        $db->where('id', $id);
        $result = $db->getOne("language_translation");

        if (!empty($result)) {
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $result);
        } else {
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00175"][$language] /* Invalid Language */, 'data'=>"");
        }
    }

    public function editLanguageTranslationData($params) {
        $db           = $this->db;
        $language     = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $id           = trim($params['id']);
        $contentCode  = trim($params['contentCode']);
        $module       = trim($params['module']);
        $language     = trim($params['language']);
        $site         = trim($params['site']);
        $category     = trim($params['category']);
        $content      = trim($params['content']);

        if(strlen($contentCode) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00176"][$language] /* Please Enter Language Name. */, 'data' => "");

        if(strlen($language) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00177"][$language] /* Please Enter Language Code. */, 'data' => "");

        $updatedAt = $db->now();

        $fields    = array("code", "module", "language", "site", "type", "content", "updated_at");
        $values    = array($contentCode, $module, $language, $site, $category, $content, $updatedAt);
        $arrayData = array_combine($fields, $values);
        $db->where('id', $id);
        $result    = $db->update("language_translation", $arrayData);

        if($result) {
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00119"][$language] /* Permission Successfully Updated. */);
        } else {
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00178"][$language] /* Invalid Permission. */, 'data' => "");
        }
    }

    public function getExchangeRateList($params) {

        $db             = $this->db;
        $general        = $this->general;
        $setting        = $this->setting;
        $activity       = $this->activity;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $tableName      = "mlm_currency_exchange_rate";
        $joinTable      = "country";
        $offsetSecs     = trim($params['offsetSecs']);
        $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $limit          = $general->getLimit($pageNumber);
        $decimalPlaces  = $setting->getSystemDecimalPlaces();
        $column = array(

            $tableName . ".id",
            $joinTable . ".name",
            $tableName . ".currency_code",
            $tableName . ".exchange_rate"
        );

        $db->join($joinTable, $joinTable . ".id = " . $tableName . ".country_id", "LEFT");
        $copyDb = $db->copy();
        $totalRecord = $copyDb->getValue($tableName, "count(*)");
        $exchangeRateList = $db->get($tableName, $limit, $column);

        if (empty($exchangeRateList))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00120"][$language] /* No Result Found. */, 'data'=> "");

        foreach ($exchangeRateList as $exchangeRate) {

            if ($activity->creatorType == "Admin") {
                if (!empty($exchangeRate['id']))
                    $exchangeRateListing['id']              = $exchangeRate['id'];
                else
                    $exchangeRateListing['id']              = "-";
            }

            if (!empty($exchangeRate['name']))
                $exchangeRateListing['name']                = $exchangeRate['name'];
            else
                $exchangeRateListing['name']                = "-";

            if (!empty($exchangeRate['currency_code']))
                $exchangeRateListing['currencyCode']        = $exchangeRate['currency_code'];
            else
                $exchangeRateListing['currencyCode']        = "-";

            if (!empty($exchangeRate['exchange_rate']))
                $exchangeRateListing['exchangeRate']        = number_format($exchangeRate['exchange_rate'], $decimalPlaces, '.', '');
            else
                $exchangeRateListing['exchangeRate']        = "-";

            $exchangeRatePageListing[] = $exchangeRateListing;
        }


        $data['exchangeRatePageListing']    = $exchangeRatePageListing;
        $data['totalPage']                  = ceil($totalRecord/$limit[1]);
        $data['pageNumber']                 = $pageNumber;
        $data['totalRecord']                = $totalRecord;
        $data['numRecord']                  = $limit[1];

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00121"][$language] /* Exchange rate list successfully retrieved. */, 'data'=> $data);
    }

    //not used so commented, can open back and add to webservice if needed
//    public function addExchangeRate($params) {
//
//        $db             = $this->db;
//        $tableName      = "mlm_currency_exchange_rate";
//        $countryName    = $params['countryName'];
//        $exchangeRate   = $params['exchangeRate'];
//        $buyRate        = $params['buyRate'];
//
//        if (empty($countryName) || !ctype_alpha($countryName))
//            return array('status' => "error", 'code' => 1, 'statusMsg' => "Data is invalid", 'data'=> "");
//
//        if (empty($exchangeRate) || !is_numeric($exchangeRate) || $exchangeRate < 0)
//            return array('status' => "error", 'code' => 1, 'statusMsg' => "Data is invalid", 'data'=> "");
//
//        if (empty($buyRate) || !is_numeric($buyRate) || $buyRate < 0)
//            return array('status' => "error", 'code' => 1, 'statusMsg' => "Data is invalid", 'data'=> "");
//
//        $db->orderBy("priority", "DESC");
//        $priority = $db->getValue($tableName, "priority", 1) + 1;
//
//        $sq = $db->subQuery();
//        $sq->where("name", $countryName);
//        $sq->getOne("country", "id");
//        $sq2 = $db->subQuery();
//        $sq2->where("name", $countryName);
//        $sq2->getOne("country", "currency_code");
//
//
//        $insertData     = array(
//
//            "country_id"    => $sq,
//            "currency_code" => $sq2,
//            "exchange_rate" => $exchangeRate,
//            "buy_rate"      => $buyRate,
//            "created_at"    => $db->now(),
//            "updated_at"    => $db->now(),
//            "status"        => "Active",
//            "priority"      => $priority
//
//        );
//
//        $id = $db->insert($tableName, $insertData);
//
//        if (empty($id))
//            return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to insert data", 'data'=> "");
//
//        return array('status' => "ok", 'code' => 0, 'statusMsg' => "Successfully insert exchange rate", 'data'=> "");
//    }

    public function editExchangeRate($params) {

        $db             = $this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $tableName      = "mlm_currency_exchange_rate";
        $exchangeRateId = trim($params['exchangeRateId']);
        $exchangeRate   = trim($params['exchangeRate']);

        if (empty($exchangeRate) || !is_numeric($exchangeRate) || $exchangeRate < 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

        $updateData = array(

            "exchange_rate" => $exchangeRate
        );

        $db->where("id", $exchangeRateId);
        if (!$db->update($tableName, $updateData))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00180"][$language] /* Failed to update data. */, 'data'=> "");

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00122"][$language] /* Successfully update exchange rate. */, 'data'=> "");
    }

    //not used so commented, can open back and add to webservice if needed
//    public function deleteExchangeRate($params) {
//
//        $db             = $this->db;
//        $tableName      = "mlm_currency_exchange_rate";
//        $exchangeRateId = $params['exchangeRateId'];
//
//        if (empty($exchangeRateId) || !is_numeric($exchangeRateId))
//            return array('status' => "error", 'code' => 1, 'statusMsg' => "Data is invalid", 'data'=> "");
//
//        $db->where("id", $exchangeRateId);
//
//        if (!$db->delete($tableName))
//            return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to delete data", 'data'=> "");
//
//        return array('status' => "ok", 'code' => 0, 'statusMsg' => "Successfully delete exchange rate", 'data'=> "");
//    }

    public function getUnitPriceList($params) {

        $db             = $this->db;
        $general        = $this->general;
        $setting        = $this->setting;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $tableName      = "mlm_unit_price";
        $offsetSecs     = trim($params['offsetSecs']);
        $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $limit          = $general->getLimit($pageNumber);
        $decimalPlaces  = $setting->getSystemDecimalPlaces();
        $column         = array(

            "id",
            "unit_price",
            "(SELECT name FROM admin WHERE id = creator_id) AS creator_name",
            "created_at"
        );

        $db->orderBy("created_at", "DESC");
        $copyDb = $db->copy();
        $unitPriceList = $db->get($tableName, null, $column);
        $totalRecord = $copyDb->getValue($tableName, "count(*)");

        if (empty($unitPriceList))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00123"][$language] /* No Result Found. */, 'data'=> "");

        foreach ($unitPriceList as $unitPrice) {

            if (!empty($unitPrice['id']))
                $unitPriceListing['id']                     = $unitPrice['id'];
            else
                $unitPriceListing['id']                     = "-";

            if (!empty($unitPrice['unit_price']))
                $unitPriceListing['unitPrice']              = number_format($unitPrice['unit_price'], $decimalPlaces, '.', '');
            else
                $unitPriceListing['unitPrice']              = "-";

            if (!empty($unitPrice['created_at']))
                $unitPriceListing['createdAt']              = $general->formatDateTimeString($offsetSecs, $unitPrice['created_at'], $format = "d/m/Y h:i:s A");
            else
                $unitPriceListing['createdAt']              = "-";

            if (!empty($unitPrice['creator_name']))
                $unitPriceListing['creatorName']            = $unitPrice['creator_name'];
            else
                $unitPriceListing['creatorName']            = "-";

            $unitPricePageListing[] = $unitPriceListing;
        }


        $data['unitPricePageListing']           = $unitPricePageListing;
        $data['totalPage']                      = ceil($totalRecord/$limit[1]);
        $data['pageNumber']                     = $pageNumber;
        $data['totalRecord']                    = $totalRecord;
        $data['numRecord']                      = $limit[1];

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00124"][$language] /* Unit price list successfully retrieved */, 'data'=> $data);
    }

    public function addUnitPrice($params) {

        $db             = $this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $tableName      = "mlm_unit_price";
        $unitPrice      = trim($params['unitPrice']);
        $creatorId      = trim($params['creatorId']);

        if (empty($unitPrice) || !is_numeric($unitPrice) || $unitPrice < 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00181"][$language] /* Successfully insert unit price */, 'data'=> "");

        if (empty($creatorId) || !is_numeric($creatorId) || $creatorId < 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00182"][$language] /* Successfully insert unit price */, 'data'=> "");

        $insertData     = array(

            "unit_price"        => $unitPrice,
            "type"              => "purchase",
            "creator_id"        => $creatorId,
            "creator_type"      => "Admin",
            "created_at"        => $db->now()
        );

        $id = $db->insert($tableName, $insertData);

        if(empty($id))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00183"][$language] /* Failed to insert unit price */, 'data'=> "");

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00125"][$language] /* Successfully insert unit price */, 'data'=> "");
    }

    public function getAdminWithdrawalList($params) {

        $db             = $this->db;
        $general        = $this->general;
        $setting        = $this->setting;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $decimalPlaces  = $setting->getSystemDecimalPlaces();
        $offsetSecs     = trim($params['offsetSecs']);
        $tableName      = "mlm_withdrawal";
        $searchData     = $params['searchData'];
        $column         = array(

            $tableName . ".id AS withdrawal_id",
            "client.id AS client_id",
            "client.name AS client_name",
            $tableName . ".amount",
            "mlm_bank.name AS bank_name",
            $tableName . ".status",
            $tableName . ".created_at",
            $tableName . ".credit_type"
        );

        $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $limit          = $general->getLimit($pageNumber);

        $withdrawalList         = array();
        $withdrawalListDetails  = array();
        $clientIdListDetails    = array();
        $clientIdList           = array();

        foreach ($searchData as $array) {
            foreach ($array as $key => $value) {
                if ($key == 'dataName') {
                    if ($value == "name")
                        $dbColumn = "client." . $value;
                    else
                        $dbColumn = $value;
                }
                else if ($key == 'dataValue') {
                    foreach ($value as $innerVal) {
                        if (!strcmp($innerVal, "All") == 0)
                            $db->where($dbColumn, $innerVal);
                    }
                }

            }
        }

        $db->join("mlm_bank", "mlm_bank.id = " . $tableName . ".bank_id", "LEFT");
        $db->join("client", "client.id = " . $tableName . ".client_id", "LEFT");
        $copyDb = $db->copy();
        $totalRecord = $copyDb->getValue($tableName, "count(*)");
        $withdrawalListResult = $db->get($tableName, $limit, $column);

        if (empty($withdrawalListResult))
            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations["B00126"][$language] /* No Results Found */, 'data' => "");

        foreach ($withdrawalListResult as $withdrawalResult){

            if (!empty($withdrawalResult['withdrawal_id']))
                $withdrawalListDetails['id']            = $withdrawalResult['withdrawal_id'];
            else
                $withdrawalListDetails['id']            = "-";

            if (!empty($withdrawalResult['client_name']))
                $withdrawalListDetails['clientName']    = $withdrawalResult['client_name'];
            else
                $withdrawalListDetails['clientName']    = "-";

            if (!empty($withdrawalResult['credit_type']))
                $withdrawalListDetails['creditType']    = $withdrawalResult['credit_type'];
            else
                $withdrawalListDetails['creditType']    = "-";

            if (!empty($withdrawalResult['amount']))
                $withdrawalListDetails['amount']        = number_format($withdrawalResult['amount'], $decimalPlaces, '.', '');
            else
                $withdrawalListDetails['amount']        = "-";

            if (!empty($withdrawalResult['bank_name']))
                $withdrawalListDetails['bankName']      = $withdrawalResult['bank_name'];
            else
                $withdrawalListDetails['bankName']      = "-";

            if (!empty($withdrawalResult['created_at']))
                $withdrawalListDetails['createdAt']     = $general->formatDateTimeString($offsetSecs, $withdrawalResult['created_at'], $format = "d/m/Y h:i:s A");
            else
                $withdrawalListDetails['createdAt']     = "-";

            if (!empty($withdrawalResult['status']))
                $withdrawalListDetails['status']        = $withdrawalResult['status'];
            else
                $withdrawalListDetails['status']        = "-";

            if (!empty($withdrawalResult['client_id']))
                $clientIdListDetails['clientId']        = $withdrawalResult['client_id'];
            else
                $clientIdListDetails['clientId']        = "-";

            $withdrawalList[] = $withdrawalListDetails;
            $clientIdList[]   = $clientIdListDetails;

        }

        $data['withdrawalList']     = $withdrawalList;
        $data['clientIdList']       = $clientIdList;
        $data['totalPage']          = ceil($totalRecord/$limit[1]);
        $data['pageNumber']         = $pageNumber;
        $data['totalRecord']        = $totalRecord;
        $data['numRecord']          = $limit[1];

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
    }

    public function adminCancelWithdrawal($params) {

        $db                 = $this->db;
        $cash               = $this->cash;
        $language           = $this->general->getCurrentLanguage();
        $translations       = $this->general->getTranslations();
        $tableName          = "mlm_withdrawal";
        $clientId           = trim($params['clientId']);

        $withdrawalDClient  = "withdrawal"; //default client in client table for withdrawal purpose
        $withdrawalId       = trim($params['withdrawalId']);
        $adjustmentType     = "Withdrawal return";
        $updateData         = array(
            'status' => "Cancel"
        );

        if (empty($withdrawalId))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00184"][$language] /* Data is invalid */, 'data' => "");

        $db->where("id", $withdrawalId);

        if ($db->update($tableName, $updateData)) {

            $db->where("id", $withdrawalId);
            $creditType = $db->getValue($tableName, "credit_type");

            $db->where("username", $withdrawalDClient);
            $result = $db->getValue("client", "id");

            if (!empty($result)) {
                $accountId = $result;
            }
            //select amount and batchid from trd_withdrawal table
            $db->where("id", $withdrawalId);
            $result = $db->getOne($tableName, "amount, batch_id");

            if (empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00185"][$language] /* Withdrawal request failed to cancel */, 'data' => "");

            $amount     = $result['amount'];
            $batchId    = $result['batch_id'];
            $belongId   = $db->getNewID();

            //TODO might need add checking for withdrawal return before cancel withdrawal request

            //insert transaction into acc_credit table
            $data = $cash->insertTAccount($accountId, $clientId, $creditType, $amount, $adjustmentType, $belongId, "", $db->now(), $batchId, $clientId);

            $withdrawalListParams = array(
                "searchData" => ""
            );
            $data = $this->getAdminWithdrawalList($withdrawalListParams);
            $data['statusMsg'] = $translations["B00127"][$language] /* Withdrawal request cancelled successfully */;

            return $data;

        }
        else
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00186"][$language] /* Withdrawal request failed to cancel */, 'data' => "");
    }

    public function getAdminClientWithdrawalDetail($params) {

        $db             = $this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $decimalPlaces  = $this->setting->getSystemDecimalPlaces();
        $tableName      = "mlm_withdrawal";
        $column         = array(

            $tableName . ".amount",
            $tableName . ".status",
            $tableName . ".account_no",
            $tableName . ".branch",
            $tableName . ".created_at",
            $tableName . ".remark",
            $tableName . ".charges",
            $tableName . ".credit_type",
            "mlm_bank" . ".name"
        );

        $withdrawalId = $params['withdrawalId'];

        if (empty($withdrawalId))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00187"][$language] /* Data is invalid */, 'data' => "");

        $db->join("mlm_bank", "mlm_bank.id = " . $tableName . ".bank_id", "LEFT");
        $db->where($tableName . ".id", $withdrawalId);
        $result = $db->getOne($tableName, $column);

        if (empty($result))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00128"][$language] /* No results found */, 'data' => "");


        $data['amount']         = $result['amount'];
        $data['status']         = $result['status'];
        $data['creditType']     = $result['credit_type'];
        $data['accountNumber']  = $result['account_no'];
        $data['branch']         = $result['branch'];
        $data['remark']         = $result['remark'];
        $data['charges']        = number_format($result['charges'], $decimalPlaces, '.', '');
        $data['withdrawalDate'] = $result['created_at'];
        $data['bankName']       = $result['name'];

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00129"][$language] /* Withdrawal list detail successfully retrieved */, 'data' => $data);
    }

    public function approveWithdrawal($params) {
        $db             = $this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $tableName      = "mlm_withdrawal";
        $withdrawalId   = trim($params['withdrawalId']);
        $status         = trim($params['status']);
        $charges        = trim($params['charges']);
        $remark         = trim($params['remark']);
        $adminId        = trim($params['adminId']);
        $adminName      = trim($params['adminName']);
        $updateData     = array();

        if (empty($withdrawalId))
            return array('status' => "error", 'code' => 0, 'statusMsg' => $translations["E00188"][$language] /* Data is invalid */, 'data' => "");

        if (empty($status))
            return array('status' => "error", 'code' => 0, 'statusMsg' => $translations["E00189"][$language] /* Data is invalid */, 'data' => "");

        if ($charges < 0 || !is_numeric($charges))
            return array('status' => "error", 'code' => 0, 'statusMsg' => $translations["E00190"][$language] /* Data is invalid */, 'data' => "");

        if (empty($adminId))
            return array('status' => "error", 'code' => 0, 'statusMsg' => $translations["E00191"][$language] /* Data is invalid */, 'data' => "");

        if (empty($adminName))
            return array('status' => "error", 'code' => 0, 'statusMsg' => $translations["E00192"][$language] /* Data is invalid */, 'data' => "");

        $db->where("id", $withdrawalId);
        $result   = $db->getOne($tableName, "amount, currency_rate");

        $amount             = $result['amount'];
        $currencyRate       = $result['currency_rate'];

        if (empty($amount))
            return array('status' => "error", 'code' => 0, 'statusMsg' => $translations["E00193"][$language] /* Withdrawal amount is invalid */, 'data' => "");

        if (empty($currencyRate) || $currencyRate <= 0)
            $currencyRate = 1;

        $convertedAmount    = $amount * $currencyRate;
        $receivableAmount   = $convertedAmount - $charges;

        $updateData['charges']              = $charges;
        $updateData['remark']               = $remark;
        $updateData['approved_at']          = $db->now();
        $updateData['status']               = $status;
        $updateData['updater_id']           = $adminId;
        $updateData['updater_username']     = $adminName;
        $updateData['converted_amount']     = $convertedAmount;
        $updateData['receivable_amount']    = $receivableAmount;

        $db->where("id", $withdrawalId);
        if ($db->update ($tableName, $updateData))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00130"][$language] /* Withdrawal request approved */, 'data' => "");
        else
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00194"][$language] /* Withdrawal request update failed */, 'data' => "");
    }

    public function getMemberAccList($params) {
        $db             = $this->db;
        $general        = $this->general;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        if(empty($params['creditType']))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid credit type */, 'data' => "");

        $creditType = $params['creditType'];
        $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

        $creditID = $db->subQuery();
        $creditID->where("name", $creditType);
        $creditID->get("credit", null, "id");
        $db->where("credit_id", $creditID, "in");
        $db->where("name", "isWallet");
        $result = $db->getValue("credit_setting", "value");

        if(empty($result) || $result == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00196"][$language] /* Invalid credit type */, 'data' => "");
        unset($result);

        //Get the limit.
        $limit      = $general->getLimit($pageNumber);
        $searchData = $params['searchData'];
        
        // Means the search params is there
        if (count($searchData) > 0) {
            foreach ($searchData as $k => $v) {
                $dataName = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);
                    
                switch($dataName) {
                    case 'name':
                        $db->where('name', $dataValue);
                            
                        break;
                        
                    case 'username':
                        $db->where('username', $dataValue);
                            
                        break;
                        
                    case 'disabled':
                        $db->where('disabled', $dataValue);
                            
                        break;
                }
                unset($dataName);
                unset($dataValue);
            }
        }

        $db->where("type", "Client");
        $copyDb = $db->copy();

        $db->orderBy("id", "DESC");

        $result = $db->get("client", $limit, "id, username, name, email, disabled");

        $totalRecords = $copyDb->getValue("client", "count(*)");

        if (!empty($result)) {
            foreach($result as $value) {
                $client['id']           = $value['id'];
                $client['username']     = $value['username'];
                $client['name']         = $value['name'];
                $client['email']        = $value['email'];
                $client['disabled']     = ($value['disabled'] == 1)? "Disabled":"Active";

                $clientList[] = $client;
            }

            $data['memberList']  = $clientList;
            $data['totalPage']   = ceil($totalRecords/$limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord']   = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        } else {
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00131"][$language] /* No Results Found */, 'data'=>"");
        }
    }

    public function getMemberDetailsList($params) {
        $db             = $this->db;
        $cash           = $this->cash;
        $general        = $this->general;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $decimalPlaces  = $this->setting->getSystemDecimalPlaces();

        $memberID       = $params['id'];
        $creditType     = $params['creditType'];
        $searchData     = $params['searchData'];

        //Get the limit.
        $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $limit = $general->getLimit($pageNumber);
        
        if(empty($creditType))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00197"][$language] /* Credit type no found */, 'data' => "");
        if(empty($memberID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00198"][$language] /* ID no found */, 'data' => "");

        // Checking whether credit type is wallet
        $creditID = $db->subQuery();
        $creditID->where("name", $creditType);
        $creditID->get("credit", null, "id");
        $db->where("credit_id", $creditID, "in");
        $result = $db->get("credit_setting", null, "name, value,".strtolower($db->userType)." AS permission");

        if(empty($result))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00199"][$language] /* Invalid credit type. */, 'data' => "");

        foreach($result as $value) {
            if($value['name'] == "isWallet") {
                if($value['value'] == 0)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00200"][$language] /* Invalid credit type. */, 'data' => "");
            }

            $permissions[$value['name']] = $value['permission'];
        }
        $data['permissions'] = $permissions;
        unset($result);

        if(count($searchData) > 0) {
            foreach($searchData as $k => $v) {
                $dataName = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);

                switch($dataName) {

                    case 'createdAt':
                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            if($dateFrom < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00201"][$language] /* Invalid date. */, 'data'=>"");
                                
                            $db->where('created_at', date('Y-m-d H:i:s', $dateFrom), '>=');
                        }
                        if(strlen($dateTo) > 0) {
                            if($dateTo < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00202"][$language] /* Invalid date. */, 'data'=>"");
                                
                            if($dateTo < $dateFrom)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00203"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

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
        $db->where('client_id', $memberID);
        $db->where('type', $creditType);
        $copyDb = $db->copy();
        $db->orderBy("created_at", "DESC");
        $result = $db->get("credit_transaction", $limit, "client_id, subject, from_id, to_id, amount, remark, batch_id, creator_id, creator_type, created_at");

        if(empty($result))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00132"][$language] /* No Results Found */, 'data'=> $data);

        foreach($result as $value) {
            if($value['creator_type'] == 'SuperAdmin')
                $superAdminID[] = $value['creator_id'];
            else if($value['creator_type'] == 'Admin')
                $adminID[] = $value['creator_id'];
            else if ($value['creator_type'] == 'Member')
                $clientID[] = $value['creator_id'];
        }
        if(!empty($superAdminID)) {
            $db->where('id', $superAdminID, 'IN');
            $dbResult = $db->get('users', null, 'id, username');
            foreach($dbResult as $value) {
                $usernameList['SuperAdmin'][$value['id']] = $value['username'];
            }
        }
        if(!empty($adminID)) {
            $db->where('id', $adminID, 'IN');
            $dbResult = $db->get('admin', null, 'id, username');
            foreach($dbResult as $value) {
                $usernameList['Admin'][$value['id']] = $value['username'];
            }
        }
        if(!empty($clientID)) {
            $db->where('id', $clientID, 'IN');
            $dbResult = $db->get('client', null, 'id, username');
            foreach($dbResult as $value) {
                $usernameList['Member'][$value['id']] = $value['username'];
            }
        }

        foreach($result as $value) {
            if($value['subject'] == "Transfer In" || $value['subject'] == "Transfer Out");
                $batch[] = $value['batch_id'];
        }
        if(!empty($batch)) {
            $db->where('batch_id', $batch, 'IN');
            $db->where('subject', array("Transfer In", "Transfer Out"), 'IN');
            $getUsername = "(SELECT username FROM client WHERE client.id=client_id) AS username";
            $batchDetail = $db->get('credit_transaction', null, 'subject, batch_id, '.$getUsername);
        }
        if(!empty($batchDetail)) {
            foreach($batchDetail as $value) {
                $batchUsername[$value['batch_id']][$value['subject']] = $value['username'];
            }
        }

        $currentBalance = $cash->getBalance($memberID, $creditType);

        foreach($result as $value) {
            $transaction['created_at']      = $general->formatDateTimeToString($value['created_at'], "d/m/Y H:i:s");
            $transaction['subject']         = $value['subject'];

            if($value['subject'] == "Transfer Out") {
                $transaction['to_from']     = $batchUsername[$value['batch_id']]["Transfer In"] ? $batchUsername[$value['batch_id']]["Transfer In"] : "-";
            }
            else if($value['subject'] == "Transfer In") {
                $transaction['to_from']     = $batchUsername[$value['batch_id']]["Transfer Out"] ? $batchUsername[$value['batch_id']]["Transfer Out"] : "-";
            }
            else if($value['from_id'] == "9")
                $transaction['to_from']     = "bonusPayout";
            else
                $transaction['to_from']     = "-";

            if($value['from_id'] >= "1000000") {
                $transaction['credit_in']   = "-";
                $transaction['credit_out']  = $value['amount'];
                $transaction['balance']     = number_format($currentBalance, $decimalPlaces, '.', '');
                $currentBalance             += $value['amount'];
            }
            else {
                $transaction['credit_in']   = $value['amount'];
                $transaction['credit_out']  = "-";
                $transaction['balance']     = number_format($currentBalance, $decimalPlaces, '.', '');
                $currentBalance             -= $value['amount'];
            }

            $transaction['creator_id']  = $usernameList[$value['creator_type']][$value['creator_id']];
            $transaction['remark']      = $value['remark'] ? $value['remark'] : "-";

            $transactionList[] = $transaction;
            unset($transaction);
        }

        $totalRecord             = $copyDb->getValue("credit_transaction", "count(*)");
        $balance                 = $cash->getClientCacheBalance($memberID, $creditType);
        $data['balance']         = $balance;
        $data['transactionList'] = $transactionList;
        $data['totalPage']       = ceil($totalRecord/$limit[1]);
        $data['pageNumber']      = $pageNumber;
        $data['totalRecord']     = $totalRecord;
        $data['numRecord']       = $limit[1];

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
    }

    public function getMemberCreditsTransaction($params) {
        $db             = $this->db;
        $client         = $this->client;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $creditType     = $params['creditType'];
        $pageNumber     = $params['pageNumber']?$params['pageNumber']:1;
        $clientID       = $params['clientID'];

        if(empty($clientID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00204"][$language] /* Failed to load credit transaction listing. */, 'data'=> "");

        if(empty($creditType)) {
            $creditTypes = $client->getValidCreditType();

            if(empty($creditTypes))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00205"][$language] /* Failed to load credit transaction listing. */, 'data'=> "");

            $creditType = $creditTypes[0];
        }

        $passParams = array (
            'id' => $clientID,
            'creditType' => $creditType
        );
        $creditTransactionList = $this->getMemberDetailsList($passParams);

        if($creditTransactionList['status'] == "error")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00206"][$language] /* Failed to load credit transaction listing. */, 'data'=> "");

        if($creditTransactionList['statusMsg'] == "No result found.")
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00133"][$language] /* No Results Found */, 'data'=> "");

        $data['transactionList'] = $creditTransactionList['data']['transactionList'];
        $data['totalPage']       = $creditTransactionList['data']['totalPage'];
        $data['pageNumber']      = $creditTransactionList['data']['pageNumber'];
        $data['totalRecord']     = $creditTransactionList['data']['totalRecord'];
        $data['numRecord']       = $creditTransactionList['data']['numRecord'];
        $memberDetails           = $this->client->getCustomerServiceMemberDetails($clientID);
        $data['memberDetails']   = $memberDetails['data']['memberDetails'];
        $data['creditTypes']     = $creditTypes;

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
    }

    public function getMemberBalance($params) {
        $db             = $this->db;
        $cash           = $this->cash;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $clientID       = $params['id'];
        $creditType     = $params['creditType'];

        if(empty($creditType))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00207"][$language] /* Invalid credit type. */, 'data' => "");
        $creditID = $db->subQuery();
        $creditID->where("name", $creditType);
        $creditID->get("credit", null, "id");
        $db->where("credit_id", $creditID, "in");
        $result = $db->get("credit_setting", null, "name,".strtolower($db->userType)." AS permission");

        if(empty($result))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00208"][$language] /* Invalid credit type. */, 'data' => "");

        foreach($result as $value) {
            $permissions[$value['name']] = $value['permission'];
        }
        $data['permissions'] = $permissions;
        unset($result);

        $data['balance']        = $cash->getClientCacheBalance($clientID, $creditType);

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00134"][$language] /* Successfully get detail */, 'data'=>$data);
    }

    public function editAdjustmentDetail($params) {
        $db                 = $this->db;
        $cash               = $this->cash;
        $language           = $this->general->getCurrentLanguage();
        $translations       = $this->general->getTranslations();
        $activity           = $this->activity;

        $clientId           = $params['id'];
        $creditType         = $params['creditType'];
        $adjustmentType     = $params['adjustmentType'];
        $adjustmentAmount   = $params['adjustmentAmount'];
        $remark             = $params['remark'];

        if (empty($clientId)) {
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00209"][$language] /* Client does not exist. */, 'data'=> "");
        }
        // checking client ID 
        $db->where('id', $clientId);
        $clientDetails = $db->getValue('client', 'username');
        if(empty($clientDetails))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00210"][$language] /* Sender no found */, 'data' => "");

        $clientName    = $clientDetails;

        if (empty($creditType)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00211"][$language] /* Credit type is required */, 'data'=> "");
        }
        if (empty($adjustmentType)){
            $errorFieldArr[] = array(
                                        'id'  => 'adjustmentTypeError',
                                        'msg' => $translations["E00212"][$language] /* Adjustment type is required */
                                    );
        }
        if (empty($adjustmentAmount) || !is_numeric($adjustmentAmount)){
            $errorFieldArr[] = array(
                                        'id'  => 'adjustmentAmountError',
                                        'msg' => $translations["E00213"][$language] /* Adjustment amount is required or invalid. */
                                    );
        }
        if (empty($remark)) {
            if (strlen($remark) > 255) {
                $errorFieldArr[] = array(
                                            'id'    => 'remarkError',
                                            'msg'   => $translations["E00214"][$language] /* Text length is over limit. */
                                        );
            }
        }
        if ($errorFieldArr) {
            $data['field'] = $errorFieldArr;
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00215"][$language] /* Data does not meet requirements. */, 'data' => $data);
        }

        if ($adjustmentType == "Adjustment Out") {

            $db->where("name", "creditAdjustment");
            $result     = $db->getValue ("client", "id");
            $accountID  = $clientId;
            $receiverID = $result;
            
            $activityCode = 'L00005';
            $titleCode = 'T00005';
            
        } else if ($adjustmentType == "Adjustment In") {
            $db->where("name", "creditRefund");
            $result     = $db->getValue ("client", "id");
            $accountID  = $result;
            $receiverID = $clientId;
            
            $activityCode = 'L00004';
            $titleCode = 'T00004';
        }

        $batchID        = $db->getNewID();
        $belongID       = $db->getNewID();
        
        $data = $cash->insertTAccount($accountID, $receiverID, $creditType, $adjustmentAmount, $adjustmentType, $belongID, "", $db->now(), $batchID, $clientId, $remark);
        if (!$data) {
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00216"][$language] /* Adjustment failed. */, 'data' => "");
        } else {
            $activityData = array('user'   => $clientName,
                                  'credit' => $creditType
                                 );
            $activityRes = $activity->insertActivity($adjustmentType, $titleCode, $activityCode, $activityData, $clientId);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00217"][$language] /* Failed to insert activity. */, 'data'=> "");
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00135"][$language] /* Adjustment success */, 'data' => "");
        }
    }

    public function transferCredit($params) {
        $db                  = $this->db;
        $cash                = $this->cash;
        $setting             = $this->setting;
        $activity            = $this->activity;
        $client              = $this->client;
        $language            = $this->general->getCurrentLanguage();
        $translations        = $this->general->getTranslations();

        $creditType          = trim($params['creditType']);
        $receiverUsername    = trim($params['receiverUsername']);
        $transferAmount      = trim($params['transferAmount']);
        $remark              = trim($params['remark']);
        $transferId          = trim($params['transferID']);
        $transactionPassword = trim($params['transactionPassword']);

        if($activity->creatorType == "Member") {
            if(empty($transactionPassword)) {
                $errorFieldArr[] = array(
                                            'id'  => 'transactionPasswordError',
                                            'msg' => $translations["E00218"][$language] /* This field cannot be empty */
                                        );
            }
            else {
                $result = $client->verifyTransactionPassword($transferId, $transactionPassword);
                if($result['status'] != "ok") {
                    $errorFieldArr[] = array(
                                                'id'  => 'transactionPasswordError',
                                                'msg' => $translations["E00219"][$language] /* Invalid password */
                                            );
                }
            }
        }

        if(empty($creditType))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00220"][$language] /* Invalid credit type */, 'data' => "");

        if(empty($transferId))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty */, 'data' => "");

        if(empty($receiverUsername)) {
            $errorFieldArr[] = array(
                                        'id'  => 'receiverUsernameError',
                                        'msg' => $translations["E00222"][$language] /* This field cannot be empty */
                                    );
        }

        $db->where('id', $transferId);
        $transferID = $db->getValue('client', 'id');
        if(empty($transferID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00223"][$language] /* No result found */, 'data' => "");

        if(empty($transferAmount) || !is_numeric($transferAmount)) {
            $errorFieldArr[] = array(
                                        'id'  => 'transferAmountError',
                                        'msg' => $translations["E00224"][$language] /* Invalid amount */
                                    );
        } else { 
            $balance = $cash->getClientCacheBalance($transferID, $creditType);
            if($transferAmount > $balance) {
                $errorFieldArr[] = array(
                                            'id'  => 'transferAmountError',
                                            'msg' => $translations["E00225"][$language] /* Insufficient credit */
                                        );
            }
        }

        if($errorFieldArr) {
            $data['field'] = $errorFieldArr;
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00226"][$language] /* Data does not meet requirements */, 'data' => $data);
        }

        $db->where('username', $receiverUsername);
        $receiverID = $db->getValue('client', 'id');
        if(empty($receiverID)) {
            $errorFieldArr[] = array(
                                        'id'  => 'receiverUsernameError',
                                        'msg' => $translations["E00227"][$language] /* Invalid username */
                                    );
        }
        else if($transferID == $receiverID) {
            $errorFieldArr[] = array(
                                        'id'  => 'receiverUsernameError',
                                        'msg' => $translations["E00228"][$language] /* Receiver cannot be yourself */
                                    );
        }

        if($errorFieldArr) {
            $data['field'] = $errorFieldArr;
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00229"][$language] /* Data does not meet requirements */, 'data' => $data);
        }

        $creditID = $db->subQuery();
        $creditID->where('name', $creditType);
        $creditID->get('credit', null, 'id');

        $db->where('credit_id', $creditID, 'IN');
        $db->where('name', "transferByTree");
        $transferByTreeValue = $db->getValue('credit_setting', 'value');
        if(empty($transferByTreeValue))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00230"][$language] /* No Result Found */, 'data' => "");

        if($transferByTreeValue == "sponsorTree") {
            $db->where('client_id', $transferID);
            $transferTraceKey = $db->getValue('tree_sponsor', 'trace_key');

            $db->where('client_id', $receiverID);
            $db->where('trace_key', $transferTraceKey."%", 'LIKE');
            $result = $db->getValue('tree_sponsor', 'client_id');
            if(empty($result)) {
                $errorFieldArr[] = array(
                                            'id'  => 'receiverUsernameError',
                                            'msg' => $translations["E00231"][$language] /* Receiver is not in the same sponsor tree. */
                                        );
            }
        }
        else if($transferByTreeValue == "placementTree") {
            $db->where('client_id', $transferID);
            $transferTraceKey = $db->getValue('tree_placement', 'trace_key');

            $db->where('client_id', $receiverID);
            $db->where('trace_key', $transferTraceKey."%", 'LIKE');
            $result = $db->getValue('tree_placement', 'client_id');
            if(empty($result)) {
                $errorFieldArr[] = array(
                                            'id'  => 'receiverUsernameError',
                                            'msg' => $translations["E00232"][$language] /* Receiver is not in the same placement tree. */
                                        );
            }
        }
        else {
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00233"][$language] /* Invalid credit type */, 'data' => "");
        }

        if($errorFieldArr) {
            $data['field'] = $errorFieldArr;
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00234"][$language] /* Data does not meet requirements. */, 'data' => $data);
        }

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
    }

    public function transferCreditConfirmation($params) {
        $db                 = $this->db;
        $cash               = $this->cash;
        $setting            = $this->setting;
        $activity           = $this->activity;
        $client             = $this->client;
        $language           = $this->general->getCurrentLanguage();
        $translations       = $this->general->getTranslations();

        $creditType         = trim($params['creditType']);
        $receiverUsername   = trim($params['receiverUsername']);
        $transferAmount     = trim($params['transferAmount']);
        $remark             = trim($params['remark']);
        $transferId         = trim($params['transferID']);
        $transactionPassword = trim($params['transactionPassword']);

        if($activity->creatorType == "Member") {
            if(empty($transactionPassword)) {
                $errorFieldArr[] = array(
                                            'id'  => 'transactionPasswordError',
                                            'msg' => $translations["E00235"][$language] /* This field cannot be empty */
                                        );
            }
            else {
                $result = $client->verifyTransactionPassword($transferId, $transactionPassword);
                if($result['status'] != "ok") {
                    $errorFieldArr[] = array(
                                                'id'  => 'transactionPasswordError',
                                                'msg' => $translations["E00236"][$language] /* Invalid Password */
                                            );
                }
            }
        }

        if(empty($creditType))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00237"][$language] /* Invalid credit type */, 'data' => "");

        if(empty($transferId))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00238"][$language] /* Required field cannot be empty */, 'data' => "");

        if(empty($receiverUsername)) {
            $errorFieldArr[] = array(
                                        'id'  => 'receiverUsernameError',
                                        'msg' => $translations["E00239"][$language] /* This field cannot be empty */
                                    );
        }

        $db->where('id', $transferId);
        $clientDetails = $db->getOne('client', 'id, username');
        if(empty($clientDetails))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00240"][$language] /* sender no found */, 'data' => "");

        $transferID    = $clientDetails['id'];
        $senderName    = $clientDetails['username'];

        if(empty($client))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00241"][$language] /* No result found */, 'data' => "");

        if(empty($transferAmount) || !is_numeric($transferAmount)) {
            $errorFieldArr[] = array(
                                        'id'  => 'transferAmountError',
                                        'msg' => $translations["E00242"][$language] /* Invalid amount */
                                    );
        } else { 
            $balance = $cash->getClientCacheBalance($transferID, $creditType);
            if($transferAmount > $balance) {
                $errorFieldArr[] = array(
                                            'id'  => 'transferAmountError',
                                            'msg' => $translations["E00243"][$language] /* Insufficient credit */
                                        );
            }
        }

        if($errorFieldArr) {
            $data['field'] = $errorFieldArr;
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00244"][$language] /* Data does not meet requirements */, 'data' => $data);
        }

        $db->where('username', $receiverUsername);
        $memberDetails = $db->getValue('client', 'id');
        $receiverID    = $memberDetails;
        $receiverName  = $receiverUsername;

        if(empty($receiverID)) {
            $errorFieldArr[] = array(
                                        'id'  => 'receiverUsernameError',
                                        'msg' => $translations["E00245"][$language] /* Invalid username*/
                                    );
        }
        else if($transferID == $receiverID) {
            $errorFieldArr[] = array(
                                        'id'  => 'receiverUsernameError',
                                        'msg' => $translations["E00246"][$language] /* Receiver cannot be yourself */
                                    );
        }

        if($errorFieldArr) {
            $data['field'] = $errorFieldArr;
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00247"][$language] /* Data does not meet requirements. */, 'data' => $data);
        }

        $creditID = $db->subQuery();
        $creditID->where('name', $creditType);
        $creditID->get('credit', null, 'id');

        $db->where('credit_id', $creditID, 'IN');
        $db->where('name', "transferByTree");
        $transferByTreeValue = $db->getValue('credit_setting', 'value');
        if(empty($transferByTreeValue))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00248"][$language] /* No result found */, 'data' => "");

        if($transferByTreeValue == "sponsorTree") {
            $db->where('client_id', $transferID);
            $transferTraceKey = $db->getValue('tree_sponsor', 'trace_key');

            $db->where('client_id', $receiverID);
            $db->where('trace_key', $transferTraceKey."%", 'LIKE');
            $result = $db->getValue('tree_sponsor', 'client_id');
            if(empty($result)) {
                $errorFieldArr[] = array(
                                            'id'  => 'receiverUsernameError',
                                            'msg' => $translations["E00249"][$language] /* Receiver is not in the same sponsor tree. */
                                        );
            }
        }
        else if($transferByTreeValue == "placementTree") {
            $db->where('client_id', $transferID);
            $transferTraceKey = $db->getValue('tree_placement', 'trace_key');

            $db->where('client_id', $receiverID);
            $db->where('trace_key', $transferTraceKey."%", 'LIKE');
            $result = $db->getValue('tree_placement', 'client_id');
            if(empty($result)) {
                $errorFieldArr[] = array(
                                            'id'  => 'receiverUsernameError',
                                            'msg' => $translations["E00250"][$language] /* Receiver is not in the same placement tree. */
                                        );
            }
        }
        else {
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00251"][$language] /* Invalid credit type */, 'data' => "");
        }

        if($errorFieldArr) {
            $data['field'] = $errorFieldArr;
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00252"][$language] /* Data does not meet requirements */, 'data' => $data);
        }
        
        $batchID  = $db->getNewID();
        $belongID = $db->getNewID();

        $db->where('username', "transfer");
        $db->where('type', "Internal");
        $internalID = $db->getValue('client', 'id');
        if(empty($internalID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00253"][$language] /* No result found */, 'data' => "");

        // Sender to internal
        $result = $cash->insertTAccount($transferID, $internalID, $creditType, $transferAmount, "Transfer Out", $belongID, "", $db->now(), $batchID, $transferID);
        if(!$result)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00254"][$language] /* Credit transfer failed */, 'data' => "");
        
        // Internal to receiver
        $result = $cash->insertTAccount($internalID, $receiverID, $creditType, $transferAmount, "Transfer In", $belongID, "", $db->now(), $batchID, $receiverID);
        if(!$result)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00255"][$language] /* Credit transfer failed */, 'data' => "");

        // insert activity log
        $titleCode    = 'T00002';
        $activityCode = 'L00002';
        $transferType = 'Transfer';
        $activityData = array('sender'   => $senderName,
                              'credit'   => $creditType,
                              'receiver' => $receiverName
                             );

        $activityRes = $activity->insertActivity($transferType, $titleCode, $activityCode, $activityData, $transferID);
        // Failed to insert activity
        if(!$activityRes)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00256"][$language] /* Failed to insert activity */, 'data'=> "");
        
        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00136"][$language] /* Credit has been successfully transferred. */, 'data' => "");
    }

    public function getWithdrawalBankList($params) {
        $db        = $this->db;
        $tableName = "mlm_bank";
        $column    = array(
            "id",
            "name",
            "country_id"
        );
        $countryId = $params['countryId'];

        if (!empty($countryId))
            $db->where("country_id", $countryId);
        $data = $db->get($tableName, NULL, $column);

        if ($data)
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
    }

    public function getWithdrawalDetail($params) {
        $db             = $this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $tableName      = "client";
        $joinTableName  = "client_setting";
        $creditType     = $params['creditType'];
        $column         = array(
                                "client_setting.value",
                                "client.name",
                                "client.username"
                               );
        $userId         = $params['clientId'];
        $country        = $this->country;
        if (empty($userId))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00257"][$language] /* User id is invalid */, 'data' => "");

        if(empty($creditType))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00258"][$language] /* Invalid credit type */, 'data' => "");
        $creditID = $db->subQuery();
        $creditID->where("name", $creditType);
        $creditID->get("credit", null, "id");
        $db->where("credit_id", $creditID, "in");
        $result = $db->get("credit_setting", null, "name,".strtolower($db->userType)." AS permission");

        if(empty($result))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00258"][$language] /* Invalid credit type */, 'data' => "");

        foreach($result as $value) {
            $permissions[$value['name']] = $value['permission'];
        }
        $data['permissions'] = $permissions;
        unset($result);

        $db->join($joinTableName, $joinTableName . ".client_id = " . $tableName . ".id", "LEFT");
        $db->where($tableName . ".id", $userId);
        $db->where($joinTableName . ".name", $creditType);
        $result = $db->get($tableName, NULL, $column);

        if (empty($result))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00137"][$language] /* No results found */, 'data' => "");

        $data['fullname']   = $result[0]['name'];
        $data['username']   = $result[0]['username'];
        $data['balance']    = $result[0]['value'];
        $countryParam       = array('pagination' => "No");
        $countryList        = $country->getCountriesList($countryParam);
        $bankParam          = array();
        $bankList           = $this->getWithdrawalBankList($bankParam);

        if (!empty($countryList))
            $data['countryList'] = $countryList['data']['countriesList'];

        if (!empty($bankList))
            $data['bankList'] = $bankList['data'];

        return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
    }

    public function addNewWithdrawal($params) {

        $db                     = $this->db;
        $setting                = $this->setting;
        $cash                   = $this->cash;
        $client                 = $this->client;
        $activity               = $this->activity;
        $language               = $this->general->getCurrentLanguage();
        $translations           = $this->general->getTranslations();

        $amount                 = $params['amount'];
        $countryID              = $params['countryID'];
        $bankID                 = $params['bankId'];
        $accountNumber          = $params['accountNumber'];
        $branch                 = $params['branch'];
        $transactionPassword    = $params['transactionPassword'];
        $clientID               = $params['clientId'];
        $creditType             = $params['creditType'];

        if(empty($clientID) || empty($creditType))
            return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00259"][$language] /* Required fields cannot be empty */, 'data' => '');

        $db->where('id', $clientID);
        $clientDetails = $db->getValue('client', 'username');
        if(empty($clientDetails))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00260"][$language] /* Client no found */, 'data' => "");

        $username = $clientDetails;

        if(empty($amount)) {
            $errorFieldArr[] = array(
                                        'id'  => 'amountError',
                                        'msg' => $translations["E00261"][$language] /* This field cannot be empty */
                                    );
        }
        else if($amount <= 0 || !is_numeric($amount)) {
            $errorFieldArr[] = array(
                                        'id'  => 'amountError',
                                        'msg' => $translations["E00262"][$language] /* Invalid amount */
                                    );
        }

        if(empty($countryID)) {
            $errorFieldArr[] = array(
                                        'id'  => 'countryError',
                                        'msg' => $translations["E00261"][$language] /* This field cannot be empty */
                                    );
        }

        if(empty($bankID)) {
            $errorFieldArr[] = array(
                                        'id'  => 'bankError',
                                        'msg' => $translations["E00261"][$language] /* This field cannot be empty */
                                    );
        }

        if(empty($accountNumber)) {
            $errorFieldArr[] = array(
                                        'id'  => 'accountNoError',
                                        'msg' => $translations["E00261"][$language] /* This field cannot be empty */
                                    );
        }

        if(empty($branch)) {
            $errorFieldArr[] = array(
                                        'id'  => 'branchError',
                                        'msg' => $translations["E00261"][$language] /* This field cannot be empty */
                                    );
        }

        if($activity->creatorType == "Member") {
            if(empty($transactionPassword)) {
                $errorFieldArr[] = array(
                                            'id'  => 'transactionPasswordError',
                                            'msg' => $translations["E00261"][$language] /* This field cannot be empty */
                                        );
            }
            else {
                $result = $client->verifyTransactionPassword($clientID, $transactionPassword);
                if($result['status'] != "ok") {
                    $errorFieldArr[] = array(
                                                'id'  => 'transactionPasswordError',
                                                'msg' => $translations["E00263"][$language] /* Invalid password */
                                            );
                }
            }
        }

        if($errorFieldArr) {
            $data['field'] = $errorFieldArr;
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00264"][$language] /* Data does not meet the requirements */, 'data' => $data);
        }
        
        // Check whether the credit type is withdrawable
        $creditID = $db->subQuery();
        $creditID->where('name', $creditType);
        $creditID->get('credit', null, 'id');
        $db->where('credit_id', $creditID, 'IN');
        $db->where('name', "isWithdrawable");
        $withdrawable = $db->getValue('credit_setting', strtolower($db->userType));
        if($withdrawable != "1")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00265"][$language] /* Withdrawal failed */, 'data' => "");

        // Validation if the amount entered is greater than the balance user has
        $balance = $cash->getBalance($clientID, $creditType);

        if($amount > $balance)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00266"][$language] /* Insufficient balance */, 'data' => "");

        // Get Internal ID
        $db->where('type', "Internal");
        $db->where("username", "withdrawal");
        $internalID = $db->getValue("client", "id");
        if(empty($internalID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00267"][$language] /* No result found */, 'data' => "");

        // Get currency_rate
        $db->where("id", $bankID);
        $db->where('country_id', $countryID);
        $countryID = $db->getValue("mlm_bank", "country_id");
        if(empty($countryID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00268"][$language] /* Invalid bank details */, 'data' => "");

        $db->where("country_id", $countryID);
        $currencyRate = $db->getValue("mlm_currency_exchange_rate", "exchange_rate");
        if(empty($currencyRate))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00267"][$language] /* No result found */, 'data' => "");

        $belongID = $db->getNewID();
        $batchID  = $db->getNewID();

        if($activity->creatorType == "Member") {
            $db->where('name', 'withdrawalAdminFee');
            $chargePercentage = $db->getValue('credit_setting', 'value');
            $chargePercentage = $chargePercentage / 100;

            $insertData = array (
                                    "client_id"         => $clientID,
                                    "amount"            => $amount,
                                    "status"            => "Pending",
                                    "created_at"        => $db->now(),
                                    "bank_id"           => $bankID,
                                    "account_no"        => $accountNumber,
                                    "credit_type"       => $creditType,
                                    "receivable_amount" => $amount * (1 - $chargePercentage),
                                    "charges"           => $amount * $chargePercentage,
                                    "currency_rate"     => $currencyRate,
                                    "converted_amount"  => $currencyRate * ($amount * (1 - $chargePercentage)),
                                    "branch"            => $branch,
                                    "belong_id"         => $belongID,
                                    "batch_id"          => $batchID
                                );
        }
        else if($activity->creatorType == "Admin") {
            $insertData = array (
                                    "client_id"         => $clientID,
                                    "amount"            => $amount,
                                    "status"            => "Pending",
                                    "created_at"        => $db->now(),
                                    "bank_id"           => $bankID,
                                    "account_no"        => $accountNumber,
                                    "credit_type"       => $creditType,
                                    "currency_rate"     => $currencyRate,
                                    "branch"            => $branch,
                                    "belong_id"         => $belongID,
                                    "batch_id"          => $batchID
                                );
        }
        // Insert transaction into mlm_withdrawal table
        $id = $db->insert('mlm_withdrawal', $insertData);
        if(empty($id))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00265"][$language] /* Withdrawal failed */, 'data' => "");

        // Insert transaction into acc_credit table
        $result = $cash->insertTAccount($clientID, $internalID, $creditType, $amount, "Withdrawal", $belongID, "", $db->now(), $batchID, $clientID);

        // Failed to insert table
        if($result) {
            // insert activity log
            $titleCode    = 'T00003';
            $activityCode = 'L00003';
            $transferType = 'Withdraw';
            $activityData = array('user'   => $username,
                                  'credit' => $creditType
                                 );
            $activityRes = $activity->insertActivity($transferType, $titleCode, $activityCode, $activityData, $clientID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00269"][$language] /* Failed to insert activity */, 'data'=> "");
        }
        else {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Withdrawal failed", 'data' => "");
        }
         
        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00138"][$language] /* Successfully submitted withdrawal request */, 'data' => "");
    }

    public function getTicketList($params) {

        $db             = $this->db;
        $general        = $this->general;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $tableName      = "mlm_ticket";
        $searchData     = $params['searchData'];
        $offsetSecs     = trim($params['offsetSecs']);
        $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $limit          = $general->getLimit($pageNumber);
        $column         = array(

            "ticket_no",
            "subject",
            "status",
            "(SELECT name FROM client WHERE id = creator_id) AS name",
            "member_unread",
            "updated_at"
        );

        if (count($searchData) > 0) {
            foreach ($searchData as $array) {
                foreach ($array as $key => $value) {
                    if ($key == 'dataName') {
                        $dbColumn = $tableName . "." .$value;
                    } else if ($key == 'dataValue') {
                        foreach ($value as $innerVal) {
                            $db->where($dbColumn, $innerVal);
                        }
                    }
                }
            }
        }

        $copyDb = $this->db;
        $ticketList = $db->get($tableName, $limit, $column);
        $totalRecord = $copyDb->getValue($tableName, "count(*)");

        if (empty($ticketList))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00139"][$language] /* No result found */, 'data'=>"");

        foreach ($ticketList as $ticket) {

            if (!empty($ticket['ticket_no']))
                $ticketListing['ticketNo']              = $ticket['ticket_no'];
            else
                $ticketListing['ticketNo']              = "-";

            if (!empty($ticket['subject']))
                $ticketListing['subject']               = $ticket['subject'];
            else
                $ticketListing['subject']               = "-";

            if (!empty($ticket['status']))
                $ticketListing['status']                = $ticket['status'];
            else
                $ticketListing['status']                = "-";

            if (!empty($ticket['name']))
                $ticketListing['name']                  = $ticket['name'];
            else
                $ticketListing['name']                  = "-";

            if (!empty($ticket['member_unread']))
                $ticketListing['memberUnread']          = $ticket['member_unread'];
            else
                $ticketListing['memberUnread']          = "-";

            if (!empty($ticket['updated_at']))
                $ticketListing['updatedAt']             = $general->formatDateTimeString($offsetSecs, $ticket['updated_at'], $format = "d/m/Y h:i:s A");
            else
                $ticketListing['updatedAt']             = "-";

            $ticketPageListing[] = $ticketListing;
        }


        $data['ticketPageListing']          = $ticketPageListing;
        $data['totalPage']                  = ceil($totalRecord/$limit[1]);
        $data['pageNumber']                 = $pageNumber;
        $data['totalRecord']                = $totalRecord;
        $data['numRecord']                  = $limit[1];

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00140"][$language] /* Successfully retrieved ticket list. */, 'data'=> $data);
    }

    public function getTicketDetail($params) {

        $db             = $this->db;
        $general        = $this->general;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $tableName      = "mlm_ticket_details";
        $offsetSecs     = trim($params['offsetSecs']);
        $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $limit          = $general->getLimit($pageNumber);
        $ticketId       = trim($params['ticketId']);

        if (empty($ticketId) || !is_numeric($ticketId))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00270"][$language] /* Data is invalid */, 'data'=>"");

        $column         = array(

            "(SELECT name FROM client WHERE id = sender_id) AS sender_name",
            "sender_type",
            "message",
            "created_at"
        );

        $db->where("ticket_id", $ticketId);
        $data = $db->get($tableName, null, $column);

        if (empty($data))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00141"][$language] /* No result found */, 'data'=>"");

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00142"][$language] /* Successfully retrieved ticket detail. */, 'data'=> $data);
    }

    public function replyTicket($params,$site) {

        $db             = $this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $tableName      = "mlm_ticket_details";
        $senderId       = trim($params['senderId']);
        $ticketId       = trim($params['ticketId']);
        $message        = trim($params['message']);

        if (empty($senderId) || !is_numeric($senderId))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00271"][$language] /* Data is invalid */, 'data'=>"");

        if (empty($ticketId) || !is_numeric($ticketId))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00271"][$language] /* Data is invalid */, 'data'=>"");

        if (empty($message))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00271"][$language] /* Data is invalid */, 'data'=>"");

        $insertData = array(

            "ticket_id"         => $ticketId,
            "sender_id"         => $senderId,
            "sender_type"       => $site,
            "message"           => $message,
            "created_at"        => $db->now()
        );

        $id = $db->insert($tableName, $insertData);

        if (empty($id))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00272"][$language] /* Failed to reply ticket */, 'data'=>"");


        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00143"][$language] /* ticket replied */, 'data'=>"");
    }

    public function updateTicketStatus($params) {

        $db             = $this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $tableName      = "mlm_ticket";
        $status         = trim($params['status']);
        $ticketId       = trim($params['ticketId']);

        if (empty($ticketId) || !is_numeric($ticketId))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00273"][$language] /* Data is invalid */, 'data'=>"");

        if (empty($status) || !ctype_alpha($status))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00273"][$language] /* Data is invalid */, 'data'=>"");

        $updateData = array(

            "status"    => $status
        );

        $db->where("id", $ticketId);
        if ($db->update($tableName, $updateData))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00144"][$language] /* Successfully update ticket */, 'data'=> "");
        else
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00274"][$language] /* Failed to update ticket */, 'data'=> "");
    }

//    public function getPackageList($params) {
//
//        $db             = $this->db;
//        $general        = $this->general;
//        $setting        = $this->setting;
//        $tableName      = "mlm_product";
//        $searchData     = $params['searchData'];
//        $offsetSecs     = trim($params['offsetSecs']);
//        $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
//        $limit          = $general->getLimit($pageNumber);
//        $decimalPlaces  = $setting->getSystemDecimalPlaces();
//        $column = array(
//
//            "translation_code",
//            "price",
//            "(SELECT value FROM mlm_product_setting WHERE product_id = ". $tableName .".id AND name = 'bonusValue') AS bonus_value"
//
//        );
//
//        if (count($searchData) > 0) {
//            foreach ($searchData as $array) {
//                foreach ($array as $key => $value) {
//                    if ($key == 'dataName') {
//                        $dbColumn = $tableName . "." .$value;
//                    } else if ($key == 'dataValue') {
//                        foreach ($value as $innerVal) {
//                            $db->where($dbColumn, $innerVal);
//                        }
//                    }
//                }
//            }
//        }
//
//        $db->where("category", "Package");
//        $db->where("status", "Active");
//        $copyDb = $db->copy();
//        $packageList = $db->get($tableName, $limit, $column);
//        $totalRecord = $copyDb->getValue($tableName, "count(*)");
//
//        if (empty($packageList))
//            return array('status' => "ok", 'code' => 0, 'statusMsg' => "No result found", 'data'=> "");
//
//        foreach ($packageList as $package) {
//
//            if (!empty($package['translation_code']))
//                //TODO need to convert to word after translation class done
//                $packageListing['translation_code']     = $package['translation_code'];
//            else
//                $packageListing['translation_code']     = "-";
//
//            if (!empty($package['price']))
//                $packageListing['price']                = number_format($package['price'], $decimalPlaces, '.', '');
//            else
//                $packageListing['price']                = "-";
//
//            if (!empty($package['bonus_value']))
//                $packageListing['bonusValue']           = number_format($package['bonus_value'], $decimalPlaces, '.', '');
//            else
//                $packageListing['bonusValue']           = "-";
//
//            $packagePageListing[] = $packageListing;
//        }
//
//
//        $data['packagePageListing']        = $packagePageListing;
//        $data['totalPage']                  = ceil($totalRecord/$limit[1]);
//        $data['pageNumber']                 = $pageNumber;
//        $data['totalRecord']                = $totalRecord;
//        $data['numRecord']                  = $limit[1];
//
//        return array('status' => "ok", 'code' => 0, 'statusMsg' => "Package list successfully retrieved", 'data'=> $data);
//    }

    public function getMemberLoginDetail($params){

        $db             = $this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $id             = $params['memberId'];
        $url            = $params['loginToMemberURL'];
        $tableName      = "client";
        $column         = Array(
            "id",
            "username"
        );

        $db->where("id", $id);

        $result = $db->getOne($tableName, $column);

        if (empty($result))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00275"][$language] /* User is invalid */, 'data'=> "");

        $result['url'] = $url;
        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $result);

    }

    public function getWhoIsOnlineList($params){

        $db             = $this->db;
        $setting        = $this->setting;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $adminTimeOut   = $setting->getAdminTimeOut();
        $memberTimeOut  = $setting->getMemberTimeout();
        $data           = array();
        $count          = 0;
        $currentTime    = time();
        $tableName      = "admin";
        $column         = array(
            "username",
            "name",
            "last_login",
            "last_activity"
        );

        $result = $db->get($tableName, NULL, $column);

        foreach($result as $row){

            $lastActivity = strtotime($row['last_activity']);

            if ($currentTime - $lastActivity < $adminTimeOut){
                $client['username']     = $row['username'];
                $client['fullname']     = $row['name'];
                $client['last_login']   = $row['last_login'];

                $count++;
                $onlineUserList[]       = $client;
            }
        }

        $tableName = "client";
        $result = $db->get($tableName, NULL, $column);

        foreach($result as $row){

            $lastActivity = strtotime($row['last_activity']);

            if ($currentTime - $lastActivity < $memberTimeOut){
                $client['username']     = $row['username'];
                $client['fullname']     = $row['name'];
                $client['last_login']   = $row['last_login'];

                $count++;
                $onlineUserList[]       = $client;
            }
        }

        $data['onlineUserList'] = $onlineUserList;
        $data['totalUserOnline'] = $count;

        if (empty($data))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "No user is online", 'data'=> "");

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);

    }

    public function getClientRightsList($params){

        $db         = $this->db;
        $tableName  = "mlm_client_rights";
        $clientId   = trim($params['clientId']);
        $column     = array(
            "id",
            "name",
            "(SELECT count(*) FROM mlm_client_blocked_rights WHERE client_id = " . $clientId . " AND rights_id = " . $tableName . ".id) AS blocked"
        );

        if (empty($clientId))
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Client not found", 'data'=> "");


        $db->orderBy("priority");
        $result = $db->get($tableName, NULL, $column);

        if (empty($result))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "No client rights found", 'data'=> "");

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $result);
    }

    public function lockAccount($params){

        $db             = $this->db;
        $tableName      = "mlm_client_blocked_rights";
        $clientId       = trim($params['clientId']);
        $blockedList    = $params['blockedList'];

        foreach($blockedList as $rights){

            if ($rights['blocked'] == "1"){

                $db->rawQuery("INSERT INTO " . $tableName . " (client_id, rights_id, created_at)
                               SELECT * FROM (SELECT " . $clientId . ", " . $rights['rightsId'] . ", NOW()) AS tmp
                               WHERE NOT EXISTS (
                               SELECT client_id FROM " . $tableName . " WHERE client_id = " . $clientId . " AND rights_id = " . $rights['rightsId'] . ")
                               LIMIT 1");
            }
            else if ($rights['blocked'] == "0"){

                $db->where("client_id", $clientId);
                $db->where("rights_id", $rights['rightsId']);

                if (!$db->delete($tableName))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to update account rights", 'data'=> "");

            }
        }

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> "");

    }

    public function getPaymentMethodList($params){
        
        $db             = $this->db;
        $setting        = $this->setting;
        $general        = $this->general;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $tableName      = "mlm_payment_method";

        $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

        // Get the limit.
        $limit              = $general->getLimit($pageNumber);
        $searchData         = $params['inputData'];
        
        // Means the search params is there
        if (count($searchData) > 0) {
            foreach ($searchData as $k => $v) {
                $dataName = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);
                    
                switch($dataName) {
                    case 'paymentType':
                        if($dataValue != "all"){
                            $db->where('payment_type', $dataValue);
                        }
                        break;
                        
                    case 'status':
                        if($dataValue != ""){
                            $db->where('status', $dataValue);
                        }   
                        break;
                        
                }
                unset($dataName);
                unset($dataValue);
            }
        }

        $copyDb = $db->copy();
        $db->orderBy("ID", "ASC");
        $result = $db->get($tableName, $limit, "ID, credit_type, status, min_percentage, max_percentage, payment_type");

        $totalRecord = $copyDb->getValue($tableName, "count(*)");

        if (!empty($result)) {
            foreach($result as $value) {
                $temp['ID']             = $value['ID'];
                $temp['paymentType']    = $value['payment_type'];
                $temp['creditType']     = $value['credit_type'];
                $temp['minPercentage']  = $value['min_percentage'];
                $temp['maxPercentage']  = $value['max_percentage'];
                $temp['status']         = $value['status'];
                // $temp['createdAt']      = $value['created_at'];

                $paymentSetting[] = $temp;
            }

            // $totalRecords = $copyDb->getValue($tableName, "count(*)");
            $data['settingList']  = $paymentSetting;
            $data['totalPage']   = ceil($totalRecord/$limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord']   = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        else
        {
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");
        }
      
    }

    public function getPaymentMethodDetails($params) {
        $db             = $this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $id             = trim($params['id']);

        if(strlen($id) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00104"][$language] /* Please Select Payment Setting */, 'data'=> '');

        $db->where('ID', $id);
        $result = $db->getOne("mlm_payment_method", "ID, status, credit_type, min_percentage, max_percentage, payment_type"); //, role_id as roleID

        if (empty($result)) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language] /* Invalid Setting. */, 'data'=>"");

        foreach ($result as $key => $value) {
            $settingDetail[$key] = $value;
        }

        $data['settingDetail'] = $settingDetail;

        return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
    }

    public function editPaymentMethod($params) {
        $db             = $this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $id       = trim($params['id']);
        $credit_type    = trim($params['credit_type']);
        $payment_type = trim($params['payment_type']);
        $min_percentage = trim($params['min_percentage']);
        $max_percentage   = trim($params['max_percentage']);
        $status   = trim($params['status']);

        if(strlen($id) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Method does not exist!" /* method ID does not exist */, 'data'=>"");

        if(strlen($credit_type) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Credit Cannot be empty" /* Credit cannot be empty */, 'data'=>"");

        if(strlen($payment_type) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Payment Type cannot be empty" /* Payment Type cannot be empty */, 'data'=>"");

        if(strlen($min_percentage) == 0 || !is_numeric($min_percentage))
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Min Percentage"/* Please Enter Min Percentage */, 'data'=>"");

        if(strlen($max_percentage) == 0 || !is_numeric($max_percentage))
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Plase Enter Max Percentage" /* Please Enter Max Percentage */, 'data'=>"");

        if(strlen($status) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00117"][$language] /* Please Select a Status */, 'data'=>"");

        $db->where('id', $id);
        $result = $db->getOne('mlm_payment_method');

        if (!empty($result)) {
            $fields    = array("credit_type", "status", "min_percentage", "max_percentage", "payment_type");
            $values    = array($credit_type, $status, $min_percentage, $max_percentage, $payment_type);

            $arrayData = array_combine($fields, $values);
            $db->where('id', $id);
            $db->update("mlm_payment_method", $arrayData);

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00103"][$language] /* Admin Profile Successfully Updated */, 'data' => "");

        }
        else{
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00118"][$language] /* Invalid Admin */, 'data'=>"");
        }
    }

    public function deletePaymentMethod($params){
        $db = $this->db;

        $id = trim($params['id']);

        if(strlen($id) == 0)
            return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Select Method", 'data'=>"");
        
        $db->where('id', $id);
        $result = $db->get('mlm_payment_method', 1);
        
        if (!empty($result)) {
            $db->where('id', $id);
            $result = $db->delete('mlm_payment_method');
            
            if($result) {
                return $this->getPaymentMethodList();
            }
            else
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete method', 'data' => '');
        }
        else{
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Method not found", 'data'=>"");
        }
    }

    public function getPaymentSettingDetails() {
        $db             = $this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $creditID = $db->subQuery();
        $creditID->where('name', 'isWallet');
        $creditID->where('value', 1);
        $creditID->getValue('credit_setting', 'credit_id', null);

        $db->where('id', $creditID, 'IN');
        $creditResult = $db->getValue("credit", "name", null);

        if(empty($creditResult)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00278"][$language] /* Invalid credit type */, 'data' => "");
        }

        $data["creditData"] = $creditResult;

        $db->where('payment', 1);
        $paymentTypeResult = $db->getValue("mlm_modules", "name", null);

        if(empty($paymentTypeResult)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Modules" /* Invalid modules */, 'data' => "");
        }

        $data["paymentType"] = $paymentTypeResult;

        return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
    }

    public function addPaymentMethod($params) {
        $db             = $this->db;
        $setting        = $this->setting;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $payment_type   = trim($params['paymentType']);
        $credit_type    = trim($params['creditType']);
        $min_percentage = trim($params['minPercentage']);
        $max_percentage = trim($params['maxPercentage']);
        $status         = trim($params['status']);

        if(strlen($payment_type) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Payment type cannot be empty" /* Payment type cannot be empty */, 'data'=>"");

        if(strlen($credit_type) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Credit type cannot be empty" /* Credit type cannot be empty */, 'data'=>"");

        if(strlen($min_percentage) == 0 || !is_numeric($min_percentage))
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Min Percentage"/* Please Enter Min Percentage */, 'data'=>"");

        if(strlen($max_percentage) == 0 || !is_numeric($max_percentage))
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Plase Enter Max Percentage" /* Please Enter Max Percentage */, 'data'=>"");

        if(strlen($status) == 0)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00110"][$language] /* Please Choose a Status */, 'data'=>"");

        $db->where('payment_type', $payment_type);
        $db->where('credit_type', $credit_type);
        
        $result = $db->get('mlm_payment_method');
        if (!empty($result)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Setting already exist" /* Setting already exist */, 'data'=>"");
        }else{

            $fields = array("credit_type", "status", "min_percentage","max_percentage", "payment_type", "created_at");
            $values = array($credit_type, $status, $min_percentage, $max_percentage, $payment_type, date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);
            try{
                $result = $db->insert("mlm_payment_method", $arrayData);
            }
            catch (Exception $e) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to add new payment method" /* Failed to add new payment method */, 'data'=>"");
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00102"][$language] /* Successfully Added */, 'data'=>"");
        }
    }

    public function cancelSale($params){

        $db             = $this->db;
        $bonus          = $this->bonus;
        $cash           = $this->cash;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $tableName      = "mlm_client_portfolio";
        $referenceNumber= $params['referenceNumber'];

        //for acc_credit table, get today's date
        $tblDate        = date('Ymd');

        //get portfolio details
        $db->where("reference_no", $referenceNumber);
        $db->where("status", "Active");
        $portfolioDetails = $db->getOne($tableName);

        if (empty($portfolioDetails))
            return array('status' => "error", 'code' => 1, 'statusMsg'=> "Portfolio not found or canceled" /* Portfolio not found */, 'data'=>"");

        //get the data of portfolio which is the foreign key for other tables
        $batchID        = $portfolioDetails['batch_id'];
        $belongID       = $portfolioDetails['belong_id'];
        $clientID       = $portfolioDetails['client_id'];
        $bonusValue     = $portfolioDetails['bonus_value'];
        $portfolioType  = $portfolioDetails['portfolio_type'];


        //update portfolio status to canceled
        $update = array(
            "status" => "Canceled"
        );

        $db->where("reference_no", $referenceNumber);
        if (!($db->update($tableName, $update)))
            return array('status' => "error", 'code' => 1, 'statusMsg'=> "Failed to cancel sale" /* Failed to cancel sale */, 'data'=>"");


        //update bonus_in table set the bonus to deleted = 1
        $update = array(
            "deleted" => 1
        );

        $db->where("batch_id", $batchID);
        $db->where("belong_id", $belongID);
        if (!($db->update("mlm_bonus_in", $update)))
            return array('status' => "error", 'code' => 1, 'statusMsg'=> "Failed to cancel sale" /* Failed to cancel sale */, 'data'=>"");

        //revert the placement bonus passed to upline when register, re-entry pin and re-purchase package
        $success = $bonus->cancelSalesPlacementBonusRevert($clientID, $bonusValue);

        if (!$success)
            return array('status' => "error", 'code' => 1, 'statusMsg'=> "Failed to cancel sale" /* Failed to cancel sale */, 'data'=>"");

        //revert bonusValue and tierValue in acc_credit_{date} table
        $column = array(
            "account_id",
            "receiver_id",
            "type",
            "debit"
        );

        $db->where("batch_id", $batchID);
        $db->where("belong_id", $belongID);
        $db->where("subject", $portfolioType);
        $db->groupBy("type");

        $transactions = $db->get("acc_credit_" . $tblDate, NULL, $column);

        foreach ($transactions as $transaction){

            $receiverID = $transaction['account_id'];
            $accountID  = $transaction['receiver_id'];
            $creditType = $transaction['type'];
            $amount     = $transaction['debit'];
            $subject    = $portfolioType . " Refund";

            if (!$cash->insertTAccount($accountID, $receiverID, $creditType, $amount, $subject, $db->getNewID(), "", $db->now(), $batchID, $accountID))
                return array('status' => "error", 'code' => 1, 'statusMsg'=> "Failed to cancel sale" /* Failed to cancel sale */, 'data'=>"");

            // Update the cache balance
            $cash->getBalance($clientID, $creditType);
        }


        //portfolio is inserted by reentry pin
        if ($portfolioType == "Pin Re-entry"){

            // Update pin status back to new
            $update = array(
                "status" => "New"
            );

            $db->where("batch_id", $batchID);
            $db->where("belong_id", $belongID);
            if (!($db->update("mlm_pin", $update)))
                return array('status' => "error", 'code' => 1, 'statusMsg'=> "Failed to cancel sale" /* Failed to cancel sale */, 'data'=>"");

        }
        //portfolio is inserted by repurchase package
        else if ($portfolioType == "Package Re-entry"){


            //get the transaction of the package re-purchase of the portfolio
            $column = array(
                "account_id",
                "receiver_id",
                "type",
                "debit"
            );

            $db->where("batch_id", $batchID);
            $db->where("subject", "Package Purchase");
            $db->groupBy("type");

            $transactions = $db->get("acc_credit_" . $tblDate, NULL, $column);

            foreach ($transactions as $transaction){

                $receiverID = $transaction['account_id'];
                $accountID  = $transaction['receiver_id'];
                $creditType = $transaction['type'];
                $amount     = $transaction['debit'];
                $subject    = $portfolioType . " Refund";

                if (!$cash->insertTAccount($accountID, $receiverID, $creditType, $amount, $subject, $db->getNewID(), "", $db->now(), $batchID, $accountID))
                    return array('status' => "error", 'code' => 1, 'statusMsg'=> "Failed to cancel sale" /* Failed to cancel sale */, 'data'=>"");

                // Update the cache balance
                $cash->getBalance($clientID, $creditType);
            }

        }

        $data = $this->getClientPortfolioList(array("clientId" => $clientID));

        return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Successfully cancel sale" /* Successfully cancel sale */, 'data'=> $data['data']);

    }

    public function admin_reseller_listing($params, $userID) {

        $db = $this->db;
        $setting = $this->setting;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $admin_page_limit= $setting->getAdminPageLimit();
        $reseller           = $params["reseller"];
        $reseller_name      = $params["reseller_name"];
        $reseller_email     = $params["reseller_email"];
        $reseller_number    = $params["reseller_number"];
        $reseller_site      = $params["reseller_site"];
        $distributor        = $params["distributor"];
        $site               = $params['site'];
        $page_number        = $params["page"];
        $page_size          = $params["page_size"] ? $params["page_size"] : $admin_page_limit;

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $db->where("id", $userID);
        $resellerDetail = $db->getOne("reseller", "source, type");
        $rsource = $resellerDetail['source'];
        $rtype = $resellerDetail['type'];

        //$distributor_id = $this->get_distributor($userID);

        if($reseller) {
            $db->where("r.username", "%$reseller%", 'LIKE');
        }

        if($reseller_name) {
            $db->where("r.name", "%$reseller_name%", "LIKE");
        }

        if($reseller_email) {
            $db->where("r.email", "%$reseller_email%", "LIKE");
        }

        if($reseller_number) {
            $db->where("u.username", "%$reseller_number%", "LIKE");
        }

        if($reseller_site) {
            $db->where("r.source", "%$reseller_site%", "LIKE");
        }

        if($distributor) {
            $db->where("r2.username", "%$distributor%", "LIKE");
        }

        if($site) {
            $db->where("r.source", "%$site%", "LIKE");
        }

        //DISTRIBUTOR
        // $db->where("r.distributor_id", $distributor_id, "IN");
        $db->where("r.deleted", 0);
        $db->where("r.type", "reseller");
        //$db->where("r.source", $rsource);
        $db->join("xun_user u", "r.user_id=u.id", "inner");

        //if($rtype=="siteadmin") {
        $db->join("reseller r2", "r.distributor_id=r2.id", "left");
        //} else {
        //    $db->join("reseller r2", "r.distributor_id=r2.id", "inner");
        //}
        
        // $resellerList = $db->get("reseller r", $limit, "r.id, r.username, r.name, r.email, r.created_at, IF(r2.username is null, '-', r2.username) as distributor_username, r.source as site_name");
        $resellerList = $db->get("reseller r", $limit,  "r.id, r.username, r.name, r.email, r.created_at, r.source, IF(r2.username is null, '-', r2.username) as distributor_username, IF(u.username = ' ', '-', u.username) as xun_user_username");

        $db->where("reseller_id", 0, ">");
        $db->where("type", "business");
        $xunuserList = $db->get("xun_user", null, "reseller_id");


            foreach($resellerList as $resellerKey => $resellerValue){
                $totalMerchant = 0;
                // RESELLER TABLE - RESELLER ID
                $reseller_id = $resellerValue["id"];
  
                foreach($xunuserList as $xuKey => $xuValue){
                    // XUN_USER TABLE - RESELLER ID
                    $xuReseller_id = $xuValue["reseller_id"];
                    
                    if($reseller_id === $xuReseller_id){

                        $totalMerchant++;
                    }
                    
                }

                $reseller_id = $resellerValue["id"];
                $reseller_username = $resellerValue["username"];
                $reseller_name = $resellerValue["name"];
                $reseller_email = $resellerValue["email"];
                $reseller_createdAt = $resellerValue["created_at"];
                $reseller_source = $resellerValue["source"];
                $reseller_distributorName = $resellerValue["distributor_username"];
                $reseller_xuUsername = $resellerValue["xun_user_username"];

            
                $totalMerch_arr = array(
                    "reseller_id" => $reseller_id,
                    "reseller_username" => $reseller_username,
                    "reseller_name" => $reseller_name,
                    "reseller_email" => $reseller_email,
                    "reseller_createdAt" => $reseller_createdAt,
                    "reseller_source" => $reseller_source,
                    "reseller_distributorName" => $reseller_distributorName,
                    "reseller_mobileNumber" => $reseller_xuUsername,
                    "total_merchant" => $totalMerchant,
                );
                $newResellerList[$reseller_id] = $totalMerch_arr;
               

            }

        $returnData["admin_reseller_listing"]    = $newResellerList;
        $returnData["totalRecord"]      = $totalRecord;
        $returnData["numRecord"]        = $page_size;
        $returnData["totalPage"]        = ceil($totalRecord/$page_size);
        $returnData["pageNumber"]       = $page_number;
        
        // $test = array("code" => 0, "status" => "ok", "statusMsg" => $translation['B00307'][$language] /*Admin Reseller Listing*/, 'data' => $returnData);
        //         echo json_encode($test);
        // return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00281') /*Admin Listing.*/, 'data' => $returnData);
        return array("code" => 0, "status" => "ok", "statusMsg" => $translations["B00306"][$language] /*Reseller Listing*/, 'data' => $returnData, 'distributor_id'=>$distributor_id);


    }

    public function admin_distributor_listing($params, $userID) {

        $db = $this->db;
        $setting = $this->setting;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $reseller_page_limit= $setting->getAdminPageLimit();
        $distributor_Name       = $params["name"];
        $distributor_Username   = $params["username"];
        $email                  = $params["email"];
        $mobile_number          = $params["mobile"];
        $distributor        = $params["distributor"];
        $site        = $params["site"];
        $page_number        = $params["page"];
        $page_size          = $params["page_size"] ? $params["page_size"] : $reseller_page_limit;

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        if($distributor) {
            $db->where("username", $distributor);
        }

        if($site) {
            $db->where("source", $site);
        }

        if($distributor_Name){
            $db->where("r.name", "%$distributor_Name%" , 'LIKE');
        } 
        
        if($distributor_Username){
            $db->where("r.username", "%$distributor_Username%" , 'LIKE');
        }

        if($email){
            $db->where("r.email", "%$email%" , 'LIKE');
        } 

        if($mobile_number){
            $db->where("u.username", "%$mobile_number%" , 'LIKE');
        }

        if($site){
            $db->where("r.source", "%$site%" , 'LIKE');
        } 

        //$db->where("source", $source);
        $db->where("r.type", "distributor");
        $db->where("r.deleted", 0);
        $db->join("xun_user u", "r.user_id = u.id", "INNER");
        $distributorListing = $db->get("reseller r", $limit, "r.id, r.username, r.distributor_id, r.name, r.email, r.source, r.created_at, r.username as distributor_username, IF(u.username = ' ', '-', u.username) as xun_user_username");
        $totalRecord = count($distributorListing);

        foreach($distributorListing as $db_key => $db_value){
            //DISTRIBUTOR ID
            $distributor_id = $db_value["id"];
            $distributor_reseller = $db_value["r.distributor_id"];
            
            //get  reseller id
            $db->where("type", "reseller");
            $db->where("distributor_id", $distributor_id);
            $reseller_id = $db->get("reseller", null, "id");
           
            $sum_reseller = count($reseller_id);
            $totalEveryResellerMerchants = 0;
            foreach($reseller_id as $user_key => $user_value){
                //get total merch
                $total_merch =0;
                $resellerID = $user_value["id"];

                $db->where("type", "business");
                $db->where("reseller_id", $resellerID);
                $total_merch = $db->getValue("xun_user", "count(reseller_id)");
                //get total merch from each seller
                $totalEveryResellerMerchants += $total_merch;
            }
            $distributor_username = $db_value["distributor_username"];
            $distributor_name = $db_value["name"];
            $distributor_email = $db_value["email"];
            $distributor_source = $db_value["source"];
            $distributor_created_at = $db_value["created_at"];
            $distributor_mobile = $db_value["xun_user_username"];
            $distributor_arr = array(
                "distributor_id" => $distributor_id,
                "distributor_site" => $distributor_source,
                "distributor_username" => $distributor_username,
                "distributor_name" => $distributor_name,
                "distributor_mobile" => $distributor_mobile,
                "distributor_email" => $distributor_email,
                "total_reseller" => $sum_reseller,
                "total_merch" => $totalEveryResellerMerchants
            );
            
            $distributorList[$distributor_id] = $distributor_arr;
        }

        $returnData["admin_distributor_listing"] = $distributorList;
        //$returnData["reseller_listing"]    = $distributorListing;
        $returnData["totalRecord"]      = $totalRecord;
        $returnData["numRecord"]        = $page_size;
        $returnData["totalPage"]        = ceil($totalRecord/$page_size);
        $returnData["pageNumber"]       = $page_number;
        //post        
        // $test = array("code" => 0, "status" => "ok", "statusMsg" => $translations['B00307'][$language] /*Reseller Details*/, 'data' => $returnData);
        // echo json_encode($test);

        return array("code" => 0, "status" => "ok", "statusMsg" => $translations["B00306"][$language] /*Reseller Listing*/, 'data' => $returnData);


    }

    public function get_distributor_details($params) {

        $db =$this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $id = $params['id'];
        
        $db->where("id", $id);
        $resellerDetail = $db->getOne("reseller", "id, username, name, email, created_at");

        if(!$resellerDetail){
            return array("code" => 0, "status" => "ok", "statusMsg" => $translations["B00246"][$language] /*No Results Found*/);
        } else {
            return array("code" => 0, "status" => "ok", "statusMsg" => $translations["B00307"][$language] /*Reseller Details*/, 'data' => $resellerDetail);
        }

    }

    public function edit_distributor_details($params) {

        $db= $this->db;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $id = $params['id'];
        $username = $params['username'];
        $nickname = $params['nickname'];
        $email = $params['email'];

        if($id == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00006"][$language] /*ID cannot be empty.*/, 'developer_msg' => 'ID cannot be empty.');
        }

        if($username == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => 'Username cannot be empty' /*Username cannot be empty.*/, 'developer_msg' => 'Username cannot be empty.');
        }

        if($nickname == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => 'Nickname cannot be empty.' /*Nickname cannot be empty.*/, 'developer_msg' => 'Nickname cannot be empty.');
        }

        $db->where("id", $id);
        $db->where("username", $username);
        $db->update("reseller", array("name"=>$nickname, "email"=>$email, "updated_at"=>date("Y-m-d H:i:s")) );


        return array("code" => 0, "status" => "ok", "statusMsg" => 'Edit User Details Successful.' /*Edit User Details Successful.*/);

    }

    public function create_distributor_user($params, $userID) {

        global $config, $post;
        $db = $this->db;
        $general = $this->general;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $setting = $this->setting;
       
        $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);

        $username = trim($params['username']);            
        $password = trim($params['password']);
        $confirm_password = trim($params['confirm_password']);
        $nickname = $params['nickname'];
        $email = $params['email']; 
        $mobileNo = $params['mobile_no'];
        $site = $params['site'];

        // Param validations
        if($username == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00404"][$language] /*Username cannot be empty.*/, 'developer_msg' => 'Username cannot be empty.');
        }

        if($password == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00014"][$language] /*Password cannot be empty.*/, 'developer_msg' => 'Password cannot be empty.');
        }

        if($confirm_password == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00068"][$language] /*Confirm password cannot be empty*/, 'developer_msg' => 'Confirm password cannot be empty');
        }
        
        if($nickname == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00506"][$language] /*Name cannot be empty.*/, 'developer_msg' => 'Name cannot be empty.');
        }            

        if($site == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => 'Site cannot be empty.', 'developer_msg' => 'Site cannot be empty.');
        }   

        if($email == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00211"][$language] /*Email cannot be empty.*/, 'developer_msg' => 'Email cannot be empty.');
        }

        if($password != $confirm_password){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00280"][$language] /*Password does not match.*/, 'developer_msg' => 'Password does not match.');
        }

        if($email != ""){
            if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
                return array('code' => 1, 'status' => "error", 'statusMsg' =>  $translations["E00212"][$language] /*Please enter a valid email address.*/);
            }

            $db->where('type', array('distributor', 'reseller'), 'IN');
            $db->where("source", $site);
            $db->where('disabled', 0);
            $db->where('email', $email);
            $reseller_email_detail = $db->getOne('reseller');

            if($reseller_email_detail){
                return array('code' => 1, 'message' => "error", 'statusMsg' => $translations["E00212"][$language] /*An account already exists with this email address.*/, 'developer_msg' => 'An account already exists with this email address.');
            }
        }
  

        if ($mobileNo != ""){
            $mobileNumberInfo = $general->mobileNumberInfo($mobileNo, null);

            if ($mobileNumberInfo["isValid"] == 0){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $translations['E00046'][$language] /*Please enter a valid mobile number.*/, 'developer_msg' => 'Please enter a valid mobile number');
            }
            $mobileNo = "+".$mobileNumberInfo["mobileNumberWithoutFormat"];

            $db->where('type', array('distributor', 'reseller'), 'IN');
            $db->where("username", $mobileNo);
            $db->where("register_site", $site);
            $db->where('disabled', 0);
            $mobileNoExists = $db->getOne("xun_user");

            if ($mobileNoExists){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $translations['E00536'][$language] /*An account already exists with this mobile number. Please select another mobile number.*/, 'developer_msg' => 'An account already exists with this mobile number. Please select another mobile number.');
            }
        }

        $hash_password = password_hash($password, PASSWORD_BCRYPT);

        $db->where("source", $site);
        $db->where("deleted", 0);
        $siteDetail = $db->getOne("site");

        if(!$siteDetail) {
            return array('code' => 1, 'status' => "error", 'statusMsg' => 'Invalid site.', 'developer_msg' => 'Invalid site.');
        } else {
            $register_site = $siteDetail['source'];
        }

        $db->where("username", $username);
        $resellerDetail = $db->getOne("reseller");
        
        if($resellerDetail) {
            return array('code' => 1, 'message' => "error", 'statusMsg' => $translations["B00314"][$language] /* This username is not available.*/, "developer_msg" => '');
        }


        //INSERT XUN USER
        $userData['server_host'] = $config["server"];
        $userData['type'] = "distributor";
        $userData['register_site'] = $register_site;
        $userData['register_through'] = "SuperAdmin Register";
        $userData['nickname'] = $nickname;
        $userData['created_at'] = date("Y-m-d H:i:s");
        $userData['username'] = $mobileNo;
        $reseller_user_id = $db->insert("xun_user", $userData);


        while (1) {
            $referral_code = $general->generateAlpaNumeric(6, 'referral_code');

            $db->where('referral_code', $referral_code);
            $result = $db->get('reseller');

            if (!$result) {
                break;
            }
        }

        //INSERT RESELLER
        $resellerData['user_id'] = $reseller_user_id;
        //$resellerData['marketer_id'] = $marketer_id;
        //$resellerData['distributor_id'] = $userID;
        $resellerData['username'] = $username;
        $resellerData['name'] = $nickname;
        $resellerData['password'] = $hash_password;
        $resellerData['email'] = $email;
        $resellerData['source'] = $register_site;
        $resellerData['referral_code'] = $referral_code;
        $resellerData['status'] = 'approved';
        $resellerData['type'] = "distributor";
        $resellerData['role_id'] = "7";
        $resellerData['created_at'] = date("Y-m-d H:i:s");
        $reseller_id = $db->insert("reseller", $resellerData);


        //UPDATE XUN USER RESELLER ID
        $db->where("id", $reseller_user_id);
        $db->update("xun_user", array("reseller_id" => $reseller_id) );

        $wallet_return = $xunCompanyWallet->createUserServerWallet($reseller_user_id, 'nuxpay_wallet', '');

        $internal_address = $wallet_return['data']['address'];
        
        $insertAddress = array(
            "address" => $internal_address,
            "user_id" => $reseller_user_id,
            "address_type" => 'nuxpay_wallet',
            "active" => '1',
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        );

        $inserted = $db->insert('xun_crypto_user_address', $insertAddress);

        if(!$inserted){
            return array('code' => 0, 'status' => 'error', 'statusMsg' => $this->get_translation_message('E00242')/*Something went wrong.*/, "developer_msg" => $db->getLastQuery());
        }

        return array("code" => 0, "status" => "ok", "statusMsg" => $translations["B00321"][$language] /*Distributor Created Successfully.*/);


    }

    public function create_reseller_user($params, $userID) {

        global $config, $post;
        $db = $this->db;
        $general = $this->general;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();
        $setting = $this->setting;
        
        $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);

        $username = trim($params['username']);            
        $password = trim($params['password']);
        $confirm_password = trim($params['confirm_password']);
        $nickname = $params['nickname'];
        $email = $params['email']; 
        $distributor = $params['distributor'];
        $mobileNo = $params['mobile_no'];
        $site = $params['site'];

        // Param validations
        if($username == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00404"][$language] /*Username cannot be empty.*/, 'developer_msg' => 'Username cannot be empty.');
        }

        if($password == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00014"][$language] /*Password cannot be empty.*/, 'developer_msg' => 'Password cannot be empty.');
        }

        if($confirm_password == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00068"][$language] /*Confirm password cannot be empty*/, 'developer_msg' => 'Confirm password cannot be empty');
        }
        
        if($nickname == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00506"][$language] /*Name cannot be empty.*/, 'developer_msg' => 'Name cannot be empty.');
        }
        
        if($email == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00211"][$language] /*Email cannot be empty.*/, 'developer_msg' => 'Email cannot be empty.');
        }

        if($site == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => 'Site cannot be empty.', 'developer_msg' => 'Site cannot be empty.');
        }

        if($password != $confirm_password){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00280"][$language] /*Password does not match.*/, 'developer_msg' => 'Password does not match.');
        }

        if($email != ""){
            // validate email
            if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
                return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00212"][$language] /*Please enter a valid email address.*/);
            }
            $db->where('type', array('distributor', 'reseller'), 'IN');
            $db->where("source", $site);
            $db->where('disabled', 0);
            $db->where('email', $email);
            $reseller_email_detail = $db->getOne('reseller');

            if($reseller_email_detail){
                return array('code' => 1, 'message' => "error", 'statusMsg' => $translations['E00558'][$language]  /*An account already exists with this email address.*/, 'developer_msg' => 'An account already exists with this email address.');
            }
        }
    


        if ($mobileNo != ""){
            $mobileNumberInfo = $general->mobileNumberInfo($mobileNo, null);

            if ($mobileNumberInfo["isValid"] == 0){
                return array('code' => 1, 'message' => "error", 'statusMsg' => $translations['E00046'][$language] /*Please enter a valid mobile number.*/, 'developer_msg' => 'Please enter a valid mobile number');
            }
            $mobileNo = "+".$mobileNumberInfo["mobileNumberWithoutFormat"];

            $db->where('type', array('distributor', 'reseller'), 'IN');
            $db->where("username", $mobileNo);
            $db->where("register_site", $site);
            $db->where('disabled', 0);
            $mobileNoExists = $db->getOne("xun_user");

            if ($mobileNoExists){
                return array('code' => 1, 'message' => "error", 'statusMsg' => $translations['E00536'][$language] /*An account already exists with this mobile number. Please select another mobile number.*/, 'developer_msg' => 'An account already exists with this mobile number. Please select another mobile number.');
            }
        }

        $db->where("source", $site);
        $db->where("deleted", 0);
        $siteDetail = $db->getOne("site");

        if(!$siteDetail) {
            return array('code' => 1, 'status' => "error", 'statusMsg' => 'Invalid site.', 'developer_msg' => 'Invalid site.');
        } else {
            $register_site = $siteDetail['source'];
        }

        if($distributor != "") {

            $db->where("deleted", 0);
            $db->where("source", $register_site);
            $db->where("username", $distributor);
            $db->where("type", "distributor");
            $distributorDetail = $db->getOne("reseller");

            if($distributorDetail) {
                $distributor_id = $distributorDetail['id'];
            } else {
                return array('code' => 1, 'message' => "error", 'statusMsg' => 'Invalid distributor username', "developer_msg" => '');
            }

        } else {
            $distributor_id = 0;
        }

        $hash_password = password_hash($password, PASSWORD_BCRYPT);


        $db->where("username", $username);
        $db->where('deleted', '0');
        $resellerDetail = $db->getOne("reseller");
        
        if($resellerDetail) {
            return array('code' => 1, 'message' => "error", 'statusMsg' => $translations["B00314"][$language] /* This username is not available.*/, "developer_msg" => '');
        }

        
        //INSERT MARKETER
        $marketerData['name'] = $username;
        $marketerData['created_at'] = date("Y-m-d H:i:s");
        $marketer_id = $db->insert("xun_marketer", $marketerData);


        //INSERT XUN USER
        $userData['server_host'] = $config["server"];
        $userData['type'] = "reseller";
        $userData['register_site'] = $register_site;
        $userData['register_through'] = "SuperAdmin Register";
        $userData['nickname'] = $nickname;
        $userData['created_at'] = date("Y-m-d H:i:s");
        $userData['username'] = $mobileNo;
        $reseller_user_id = $db->insert("xun_user", $userData);

        while (1) {
            $referral_code = $general->generateAlpaNumeric(6, 'referral_code');

            $db->where('referral_code', $referral_code);
            $result = $db->get('reseller');

            if (!$result) {
                break;
            }
        }


        //INSERT RESELLER
        $resellerData['user_id'] = $reseller_user_id;
        $resellerData['marketer_id'] = $marketer_id;
        $resellerData['distributor_id'] = $distributor_id;
        $resellerData['username'] = $username;
        $resellerData['name'] = $nickname;
        $resellerData['password'] = $hash_password;
        $resellerData['email'] = $email;
        $resellerData['source'] = $register_site;
        $resellerData['referral_code'] = $referral_code;
        $resellerData['status'] = 'approved';
        $resellerData['type'] = "reseller";
        $resellerData['role_id'] = "6";
        $resellerData['created_at'] = date("Y-m-d H:i:s");
        $reseller_id = $db->insert("reseller", $resellerData);


        //UPDATE XUN USER RESELLER ID
        $db->where("id", $reseller_user_id);
        $db->update("xun_user", array("reseller_id" => $reseller_id) );

        $wallet_return = $xunCompanyWallet->createUserServerWallet($reseller_user_id, 'nuxpay_wallet', '');

        $internal_address = $wallet_return['data']['address'];
        
        $insertAddress = array(
            "address" => $internal_address,
            "user_id" => $reseller_user_id,
            "address_type" => 'nuxpay_wallet',
            "active" => '1',
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        );

        $inserted = $db->insert('xun_crypto_user_address', $insertAddress);

        if(!$inserted){
            return array('code' => 0, 'status' => 'error', 'statusMsg' => $this->get_translation_message('E00242')/*Something went wrong.*/, "developer_msg" => $db->getLastQuery());
        }

        return array("code" => 0, "status" => "ok", "statusMsg" => $translations["B00308"][$language] /*Reseller Created Successfully.*/);


    }


    public function create_reseller_merchant($params,$userID){
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        global $config, $xunBusiness, $post, $xunUser, $xunXmpp, $xunPaymentGateway;    

        $username = trim($params['mobile_no']);            
        $password = trim($params['password']);
        $confirm_password = trim($params['confirm_password']);
        $nickname = $params['nickname'];
        $email = $params['email'];            
        $status = $params['status'];
        $reseller = $params['reseller'];
        $site = $params['site'];
        $country = $params['country'];
        $distributor = $params['distributor'];

        // Param validations
        if($username == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00005"][$language] /*Mobile number cannot be empty.*/, 'developer_msg' => 'Mobile number cannot be empty.');
        }

        if($password == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00014"][$language] /*Password cannot be empty.*/, 'developer_msg' => 'Password cannot be empty.');
        }

        if($confirm_password == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00068"][$language] /*Confirm password cannot be empty*/, 'developer_msg' => 'Confirm password cannot be empty');
        }
        
        if($nickname == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00506"][$language] /*Name cannot be empty.*/, 'developer_msg' => 'Name cannot be empty.');
        } 

        if($site == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => 'Site cannot be empty.', 'developer_msg' => 'Site cannot be empty.');
        }  

        if($status == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00012"][$language] /*Status cannot be empty.*/, 'developer_msg' => 'Status cannot be empty.');
        }

        if($password != $confirm_password){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00280"][$language] /*Password does not match.*/, 'developer_msg' => 'Password does not match.');
        }

        if($reseller != ''){
            $db->where('username', $reseller);
            $db->where("source", $site);
            $db->where("type", "reseller");
            $resellerDetail = $db->getOne('reseller');
            $resellerID = $resellerDetail["id"];

            if(!$resellerDetail){
                return array('code' => 1, 'message' => "error", 'statusMsg' => 'Reseller not found', "developer_msg" => 'Reseller not found.');
            }
        }

        if($distributor != ''){
            $db->where("type", "distributor");
            $db->where("source", $site);
            $db->where("username", $distributor);
            $distributorDetail = $db->getOne("reseller");

            if(!$distributorDetail){
                return array('code' => 1, 'message' => "error", 'statusMsg' => 'Distributor not found', "developer_msg" => 'Distributor not found.');
            }

            if($reseller != ''){
                if ($distributorDetail["id"] != $resellerDetail["distributor_id"]){
                    return array('code' => 1, 'status' => "error", 'statusMsg' => 'Reseller and distributor do not match.', 'developer_msg' => 'Reseller and distributor do not match.');     
                }
            } else {
                return array('code' => 1, 'status' => "error", 'statusMsg' => 'Reseller cannot be empty.', 'developer_msg' => 'Reseller cannot be empty.');
            }

        } else {
            if($reseller != '' && $resellerDetail["distributor_id"] != 0){
                return array('code' => 1, 'status' => "error", 'statusMsg' => 'Reseller and distributor do not match.', 'developer_msg' => 'Reseller and distributor do not match.');  
            }
        }

        $db->where('name', $country);
        $countryExists = $db->getOne('country');
        if(!$countryExists){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations['E00538'][$language] /*Country does not exist.*/, 'developer_msg' => 'Country does not exist.');
        }

        $mobileNumberInfo = $general->mobileNumberInfo($username, null);
        if ($mobileNumberInfo["isValid"] == 0) {

            return array('code' => 1, 'message' => "error", 'statusMsg' => $translations["E00046"][$language] /*Please enter a valid mobile number.*/, 'developer_msg' => 'Please enter a valid mobile number');

        }


        $db->where("source", $site);
        $db->where("deleted", 0);
        $siteDetail = $db->getOne("site");

        if(!$siteDetail) {
            return array('code' => 1, 'status' => "error", 'statusMsg' => 'Invalid site.', 'developer_msg' => 'Invalid site.');
        } else {
            $source = $siteDetail['source'];
        }

        
        $username = "+".$mobileNumberInfo["mobileNumberWithoutFormat"];

        if($reseller != ""){
            $db->where("type", "reseller");
            $db->where("source", $source);
            $db->where("username", $reseller);
            $resellerDetail = $db->getOne("reseller");
            
            if(!$resellerDetail) {
                return array('code' => 1, 'message' => "error", 'statusMsg' => 'Reseller not found', "developer_msg" => '');
            } 
            else {
                $resellerId = $resellerDetail['id'];
                $marketerId = $resellerDetail['marketer_id'];
            }
        } else {
            $resellerId = 0;
            $marketerId = 0;
        }

        
        $db->where("register_site", $source);
        $db->where('username', $username);
        $user = $db->getOne('xun_user', 'id, username');

        if($user){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00536"][$language] /*An account already exists with this mobile number. Please select another mobile number..*/, 'developer_msg' => 'An account already exists with this mobile number. Please select another mobile number.');
        }

        // $username = $general->mobileNumberInfo($username, null);
        //$username = str_replace("-", "", $username);
        
        $hash_password = password_hash($password, PASSWORD_BCRYPT);

        $date = date("Y-m-d H:i:s");
        $server = $config["server"];
        $email = $email ? $email : '';
        $service_charge_rate = $setting->systemSetting["theNuxCommissionFeePct"];
        
        $insert_user = array(
            "username" => $username,
            "server_host" => $server,
            "nickname" => $nickname,
            "email" => $email,
            "web_password" => $hash_password,
            "register_site" => $source,
            // "role_id" => $role_id,
            "disabled" => $status == 'disable' ? 1 : 0,
            // "suspended" => $status == 'suspended' ? 1 : 0,
            "created_at" => $date,
            "updated_at" => $date,
            "register_through" => "SuperAdmin Register",
            "reseller_id" => $resellerId,
            "type" => "business",
            "service_charge_rate" => $service_charge_rate
        );

        // create nuxpay user
        $user_id = $db->insert('xun_user', $insert_user);

        if(!$user_id){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00200"][$language] /*Something went wrong. Please try again.*/, 'developer_msg' => 'Something went wrong. Please try again.');
        }

        // xun_business_account            
        $fields = array("user_id", "email" ,"password", "main_mobile", "main_mobile_verified", "created_at", "updated_at");
        $values = array($user_id, $email, $hash_password, $username, 1, $date, $date);
        $arrayData = array_combine($fields, $values);
        $db->insert("xun_business_account", $arrayData);
        $lq = $db->getLastQuery();
        // create business
        $insertBusinessData = array(
            "user_id" => $user_id,
            "name" => $nickname,
            "created_at" => $created_at,
            "updated_at" => $created_at,
            "email" => $email ? $email : ' ',
            "country" => $country
        );

        $business_details_id = $db->insert("xun_business", $insertBusinessData);
        if (!$business_details_id)
            return array('code' => 1, 'message' => "error", 'statusMsg' => $translations["E00242"][$language] /*Something went wrong.*/, "developer_msg" => $db->getLastError());
            
       
        if($marketerId > 0){
            $db->where("marketer_id", $marketerId);
            $marketerDetail = $db->get("xun_marketer_destination_address");                
    
            foreach($marketerDetail as $mDetail) {
    
                $marketerSchemeData['business_id'] = $user_id;
                $marketerSchemeData['marketer_id'] = $marketerId;
                $marketerSchemeData['destination_address'] = $mDetail['destination_address'];
                $marketerSchemeData['wallet_type'] = $mDetail['wallet_type'];
                $marketerSchemeData['commission_rate'] = $mDetail['commission_rate'];
                $marketerSchemeData['transaction_type'] = $mDetail['transaction_type'];
                $marketerSchemeData['disabled'] = 0;
                $marketerSchemeData['created_at'] = date("Y-m-d H:i:s");
    
                $db->insert("xun_business_marketer_commission_scheme", $marketerSchemeData);
    
            }
        }


        return array("code" => 0, "status" => "ok", "statusMsg" => $translations["B00313"][$language] /*Merchant Created Successfully.*/);
    }

}
?>
