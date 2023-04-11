<?php
/**
 * Date 19/08/2020
 */

 class Reseller {
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

    public function resellerLogin($params) {

        $db = $this->db;
        $setting = $this->setting;

        //Language Translations.
        $language        = $this->general->getCurrentLanguage();
        $translations    = $this->general->getTranslations();

        // Get the stored password type.
        $passwordEncryption = $setting->getResellerPasswordEncryption();

        // $username = trim($params['username']);
        $email    = trim($params['email']);
        $password = trim($params['password']);
        $site     = trim($params['site']);

        // $db->where('username', $username);
        $db->where('email', $email);
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
        $db->where('status', 'approved');
        $db->where("source", $site);
        $db->where('deleted', 0);
        $result = $db->get('reseller');

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
            $db->where('a.site', 'Reseller');
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
            $db->update('reseller', array_combine($fields, $values));

            // This is to get the Pages from the permissions table
            $ids = $db->subQuery();
            $ids->where('disabled', 0);
            $ids->get('roles_permission', null, 'permission_id');

            $db->where('id', $ids, 'in');
            $db->where('type', 'Page');
            $db->where('site', 'Reseller');
            $db->where('disabled', 0);
            $pageResults = $db->get('permissions');
            foreach ($pageResults as $array) {
                $data['pages'][] = $array;
            }

            // This is to get the hidden submenu from the permissions table
            $db->where('type', 'Hidden');
            $db->where('site', 'Reseller');
            $db->where('disabled', 0);
            $hiddenResults = $db->get('permissions');
            foreach ($hiddenResults as $array){
                $data['hidden'][] = $array;
            }

            $db->where("id", $result[0]['distributor_id']);
            $distributor_username = $db->getValue("reseller", "username");

            $reseller['distributor_username']  = $distributor_username; 
            $reseller['userID']                = $id;
            $reseller['username']              = $result[0]['username'];
            $reseller['name']                  = $result[0]['name'];
            $reseller['userEmail']             = $result[0]['email'];
            $reseller['userRoleID']            = $result[0]['role_id'];
            $reseller['userType']              = $result[0]['type'];
            $reseller['lastActivity']          = $result[0]['last_activity'];
            $reseller['referralCode']          = $result[0]['referral_code'];
            $reseller['sessionID']             = $sessionID;
            $reseller['timeOutFlag']           = $setting->getResellerTimeOut();
            $reseller['pagingCount']           = $setting->getResellerPageLimit();
            $reseller['decimalPlaces']         = $setting->getSystemDecimalPlaces();

            $data['userDetails'] = $reseller;

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }
        else
            return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00183"][$language] /* Invalid Login */, 'data' => "");
    }

 }
?>