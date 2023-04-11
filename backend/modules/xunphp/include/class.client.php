<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the Database functionality for Client.
     * Date  16/03/2018.
    **/

    class Client {
        
        function __construct($db, $setting, $general, $country, $tree, $cash, $activity, $product, $invoice, $bonus) {
            $this->db          = $db;
            $this->setting     = $setting;
            $this->general     = $general;
            $this->country     = $country;
            $this->tree        = $tree;
            $this->cash        = $cash;
            $this->activity    = $activity;
            $this->product     = $product;
            $this->invoice     = $invoice;
            $this->bonus       = $bonus;
        }

        public function memberLogin($params) {
            $db             = $this->db;
            $setting        = $this->setting;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();

            //get the stored password type.
            $passwordEncryption = $setting->getMemberPasswordEncryption();

            $id       = trim($params['id']);
            $username = trim($params['username']);
            $password = trim($params['password']);
            
            $db->where('username', $username);

            //for admin login from admin site to member site
            if (!empty($id)) {
                $db->where("id", $id);
            }
            else {
                if ($passwordEncryption == "bcrypt") {
                    // Bcrypt encryption
                    // Hash can only be checked from the raw values
                } else if ($passwordEncryption == "mysql") {
                    // Mysql DB encryption
                    $db->where('password', $db->encrypt($password));
                } else {
                    // No encryption
                    $db->where('password', $password);
                }
            }

            $result = $db->get('client');

            if(empty($result))
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00276"][$language] /* Invalid Login */, 'data' => "");

            //if doesn't have id means it is not login from admin site
            if (empty($id)) {
                if ($passwordEncryption == "bcrypt") {
                    // We need to verify hash password by using this function
                    if (!password_verify($password, $result[0]['password']))
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00276"][$language] /* Invalid Login */, 'data' => "");
                }
            }
            
            $id = $result[0]['id'];
            
            if($result[0]['disabled'] == 1) {
                // Return error if account is disabled
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00277"][$language] /* Invalid Login */, 'data' => '');
            }

            if($result[0]['suspended'] == 1) {
                // Return error if account is suspended
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00471"][$language] /* Your account is suspended */, 'data' => '');
            }

            if($result[0]['freezed'] == 1) {
                // Return error if account is freezed
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00472"][$language] /* Your account is freezed */, 'data' => '');
            }

            if($result[0]['terminated'] == 1) {
               // Return error if account is terminated
               return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00473"][$language] /* Your account is terminated */, 'data' => '');
            }

            $sessionID = md5($result[0]['username'] . time());
            
            $fields = array('session_id', 'last_login', 'updated_at');
            $values = array($sessionID, date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));
            $db->where('id', $id);
            $db->update('client', array_combine($fields, $values));
            
            // Get client credit
            // $db->where('type', 'Credit Balance');
            // $db->where('client_id', $id);
            // $creditResult = $db->get('client_setting', null, 'name');
            // if(!empty($creditResult)) {
            //     foreach($creditResult as $val) {
            //         $creditType[] = $val['name'];
            //     }
            //     $data['memberCreditType'] = $creditType;
            // }

            //get client blocked rights
            $column = array(
                "(SELECT name FROM mlm_client_rights WHERE id = mlm_client_blocked_rights.rights_id) AS blocked_rights"
            );
            $db->where('client_id', $id);
            $blockedRightsResult = $db->get("mlm_client_blocked_rights", NULL, $column);

            $blockedRights = array();
            foreach ($blockedRightsResult as $row){
                $blockedRights[] = $row['blocked_rights'];
            }

            $memo = $this->getPopUpMemo();

            $member['memo'] = $memo;
            $member['timeOutFlag'] = $setting->getMemberTimeout();
            $member['userID'] = $id;
            $member['username'] = $result[0]['name'];
            $member['userEmail'] = $result[0]['email'];
            $member['userRoleID'] = $result[0]['role_id'];
            $member['sessionID'] = $sessionID;
            $member['pagingCount'] = $setting->getMemberPageLimit();
            $member['decimalPlaces'] = $setting->getSystemDecimalPlaces();
            $member['blockedRights'] = $blockedRights;

            //for mobile apps
            $db->where("id", $result[0]["country_id"]);
            $countryName = $db->getValue("country", "name");

            $member['countryName'] = $countryName;
            $member['createdOn'] = $result[0]['created_at'];

            $data['userDetails'] = $member;
            
            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getPopUpMemo() {
            $db = $this->db;

            $db->where('status', "Active");
            $db->orderBy('priority', 'asc');
            $getBase64 = "(SELECT data FROM uploads WHERE uploads.id=image_id) AS base_64";
            $getFileType = "(SELECT type FROM uploads WHERE uploads.id=image_id) AS file_type";
            $memo = $db->get('mlm_memo', null, $getBase64.', '.$getFileType);

            return $memo ? $memo : "";
        }
        
        public function getEncryptedPassword($password) {
            $db = $this->db;
            $setting = $this->setting;
            
            // Get the stored password type.
            $passwordEncryption = $setting->getMemberPasswordEncryption();
            if ($passwordEncryption == "bcrypt") {
                return password_hash($password, PASSWORD_BCRYPT);
            }
            else if ($passwordEncryption == "mysql") {
                return $db->encrypt($password);
            }
            else return $password;
        }

        public function getValidCreditType() {

            $db             = $this->db;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();

            $creditID = $db->subQuery();
            $creditID->where('name', 'isWallet');
            $creditID->where('value', 1);
            $creditID->getValue('credit_setting', 'credit_id', null);

            $db->where('id', $creditID, 'IN');
            $creditName = $db->getValue("credit", "name", null);

            if(empty($creditName))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00278"][$language] /* Invalid credit type */, 'data' => "");

            return $creditName;
        }

        public function getViewMemberDetails($params) {
            $db              = $this->db;
            $language        = $this->general->getCurrentLanguage();
            $translations    = $this->general->getTranslations();

            $memberId = $params['memberId'];

            $db->join("country c", "m.country_id=c.id", "LEFT");
            $db->join("client s", "m.sponsor_id=s.id", "LEFT");
            $db->where("m.id", $memberId);
            $member = $db->getOne("client m", "m.name, m.email, m.phone, m.address, c.name AS country, m.disabled, m.suspended, m.freezed, s.username AS sponsorUsername");

            $data['member'] = $member;
            if(empty($member))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00279"][$language] /* No result found */, 'data' => "");
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getRegistrationDetails($params) {
            $db             = $this->db;
            $country        = $this->country;
            $setting        = $this->setting;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();

            $position = $setting->systemSetting['maxPlacementPositions'];

            // p is mlm_product table, s is mlm_product_setting table
            $db->join("mlm_product_setting s", "p.id = s.product_id", "LEFT");
            $db->where("s.name","bonusValue");
            $db->where("p.status", "Active");
            $db->where("p.category","Package");
            $copyDb = $db->copy();
            $result = $db->get("mlm_product p", null, "p.id, p.name, p.price, s.value");

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00280"][$language] /* No have any package */, 'data' => "");

            $decimalPlaces = $setting->getSystemDecimalPlaces();
            foreach($result as $value) {
                $package['id']      = $value['id'];
                $package['name']    = $value['name'];
                $package['price']   = number_format($value['price'], $decimalPlaces, ".", "");
                $package['value']   = $value['value'];

                $pacDetail[] = $package;
            }

            $countryParams = array("pagination" => "No");
            $resultCountryList = $country->getCountriesList($countryParams);
                if (!$resultCountryList) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00281"][$language] /* No result found */, 'data' => "");
                }
            $countryList    = $resultCountryList['data']['countriesList'];
            $stateList      = $country->getState();
            $cityList       = $country->getCity();
            $countyList     = $country->getCounty();



            $data['countriesList']     = $countryList;
            $data['stateList']         = $stateList;
            $data['cityList']          = $cityList;
            $data['countyList']        = $countyList;
            $data['placementPosition'] = $position;
            $data['pacDetails']        = $pacDetail;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function verifyTransactionPassword($clientID, $transactionPassword){
            $db             = $this->db;
            $setting        = $this->setting;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();

            //get the stored password type.
            $passwordEncryption = $setting->getMemberPasswordEncryption();

            $db->where('id', $clientID);
            if($passwordEncryption == "bcrypt") {
                // Bcrypt encryption
                // Hash can only be checked from the raw values
            }
            else if ($passwordEncryption == "mysql") {
                // Mysql DB encryption
                $db->where('transaction_password', $db->encrypt($transactionPassword));
            }
            else {
                // No encryption
                $db->where('transaction_password', $transactionPassword);
            }
            $result = $db->getValue('client', 'transaction_password');

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00282"][$language] /* Invalid transaction password */, 'data' => "");
            
            if($passwordEncryption == "bcrypt") {
                // We need to verify hash password by using this function
                if(!password_verify($transactionPassword, $result))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00282"][$language] /* Invalid transaction password */, 'data' => "");
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getRegistrationPaymentDetails($params) {
            $db             = $this->db;
            $cash           = $this->cash;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $setting = $this->setting;
            $product = $this->product;

            $sponsorUsername  = $params['sponsorUsername'];
            $codeNum          = $params['codeNum'];

            // Get latest unit price
            $unitPrice = $this->getLatestUnitPrice();
            // Get valid credit type 
            $creditName = $this->getValidCreditType();
            // Get decimal Placse
            $decimalPlaces = $setting->getSystemDecimalPlaces();

            if (empty($sponsorUsername)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00283"][$language] /* Sponsor no found */, 'data'=> "");
            } else {
                $db->where("username", $sponsorUsername);
                $sponsorID = $db->getValue("client", "id");
            }
            if (empty($codeNum)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00284"][$language] /* Package no found */, 'data'=> "");
            } else {
                // p is mlm_product table, s is mlm_product_setting table
                $db->join("mlm_product_setting s", "p.id = s.product_id", "LEFT");
                $db->where("s.name","bonusValue");
                $db->where("p.status", "Active");
                $db->where("p.category","Package");
                $db->where("p.id", $codeNum);
                $copyDb        = $db->copy();
                $resultPackage = $db->getOne("mlm_product p", "p.price, p.name, s.value");
                if (empty($resultPackage)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00284"][$language] /* Sponsor no found */, 'data'=> "");
                }
            }

            foreach ($creditName as $value) {
                // Get min/max payment method
                $paymentMethod = $product->getMinMaxPaymentMethod(number_format($resultPackage['price'] * $unitPrice, $decimalPlaces, ".", ""), $value, "Registration");

                if($paymentMethod[$value]){
                    $wallet[] = array("name" => $value, "value" => $cash->getClientCacheBalance($sponsorID, $value), "payment" => $paymentMethod[$value]);
                }
            }
            
            $data['sponsorID']              = $sponsorID;
            $data['wallet']                 = $wallet;
            $data['resultPackage']['price'] = number_format($resultPackage['price'] * $unitPrice, $decimalPlaces, ".", "");
            $data['resultPackage']['name']  = $resultPackage['name'];
            $data['resultPackage']['value'] = $resultPackage['value'];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function getRegistrationPackageDetails($params) {
            $db               = $this->db;
            $cash             = $this->cash;
            $setting          = $this->setting;
            $language         = $this->general->getCurrentLanguage();
            $translations     = $this->general->getTranslations();

            $type             = $params['type'];
            $codeNum          = $params['codeNum'];
            $status           = $params['status'];
            $sponsorUsername  = $params['sponsorUsername'];
            
            // Get latest unit price
            $unitPrice = $this->getLatestUnitPrice();
            // Get valid credit type 
            $creditName = $this->getValidCreditType();
            // Get decimal place
            $decimalPlaces = $setting->getSystemDecimalPlaces();
            
            if(empty($sponsorUsername))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00285"][$language] /* Sponsor no found */, 'data'=> "");
            else {
                $db->where("username", $sponsorUsername);
                $sponsorID = $db->getValue("client", "id");
            }
            
            foreach($creditName as $value) {
                $credit[] = array("name" => $value, "value" => $cash->getClientCacheBalance($sponsorID, $value));
            }

            $data['credit'] = $credit;
 
            if ($type == 'package') {
                if (empty($codeNum)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00286"][$language] /* Package no found */, 'data'=> "");
                } else {
                    // p is mlm_product table, s is mlm_product_setting table
                    $db->join("mlm_product_setting s", "p.id = s.product_id", "LEFT");
                    $db->where("s.name","bonusValue");
                    $db->where("p.status", "Active");
                    $db->where("p.category","Package");
                    $db->where("p.id", $codeNum);
                    $copyDb        = $db->copy();
                    $resultPackage = $db->getOne("mlm_product p", "p.price, p.name, s.value");
                    if (empty($resultPackage)) {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00286"][$language] /* Package no found */, 'data'=> "");
                    }
                    $data['result']['price'] = number_format($resultPackage['price'] * $unitPrice, $decimalPlaces, '.', '');
                    $data['result']['name']  = $resultPackage['name'];
                    $data['result']['value'] = $resultPackage['value'];
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
                }
            } elseif ($type == 'pin') {
                if (empty($codeNum)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00287"][$language] /* Pin no found */, 'data'=> "");
                } else {
                    // a is mlm_product table, c is mlm_pin table
                    $db->join("mlm_product a", "a.id = c.product_id", "LEFT");
                    $db->where("c.code", $codeNum);
                    $db->where('c.status', $status);
                    $copyDb     = $db->copy();
                    $resultPin  = $db->get("mlm_pin c", 1,  "c.code, a.name, c.bonus_value as bonusValue");
                    if (empty($resultPin)) {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00287"][$language] /* Pin no found */, 'data'=> "");
                    }
                    $data['result'] = $resultPin;
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
                }
            } elseif ($type == 'free') {
                if (empty($codeNum)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00286"][$language] /* Package no found */, 'data'=> "");
                } else {
                    // p is mlm_product table, s is mlm_product_setting table
                    $db->join("mlm_product_setting s", "p.id = s.product_id", "LEFT");
                    $db->where("s.name","bonusValue");
                    $db->where("p.status", "Active");
                    $db->where("p.category","Package");
                    $db->where("p.id", $codeNum);
                    $copyDb        = $db->copy();
                    $resultPackage = $db->get("mlm_product p", NULL, "p.name");
                    if (empty($resultPackage)) {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00286"][$language] /* Package no found */, 'data'=> "");
                    }
                    $data['result'] = $resultPackage;
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
                }
            }
        }

        public function memberRegistration($params) {
            $db                 = $this->db;
            $setting            = $this->setting;
            $language           = $this->general->getCurrentLanguage();
            $translations       = $this->general->getTranslations();

            $registerType       = trim($params['registerType']);
            $fullName           = trim($params['fullName']);
            $username           = trim($params['username']);
            $address            = empty(trim($params['address'])) ? "" : trim($params['address']);
            $country            = trim($params['country']);
            $state              = empty(trim($params['state'])) ? "" : trim($params['state']);
            $county             = empty(trim($params['county'])) ? "" : trim($params['county']);
            $city               = empty(trim($params['city'])) ? "" : trim($params['city']);
            $dialingArea        = trim($params['dialingArea']);
            $phone              = trim($params['phone']);
            $email              = trim($params['email']);
            $password           = trim($params['password']);
            $checkPassword      = trim($params['checkPassword']);
            $tPassword          = trim($params['tPassword']);
            $checkTPassword     = trim($params['checkTPassword']);
            $sponsorName        = trim($params['sponsorName']);
            $placementUsername  = trim($params['placementUsername']);
            $placementPosition  = trim($params['placementPosition']);
            //codeNum is store any registerType of productID
            $codeNum            = trim($params['codeNum']);

            $maxFName = $setting->systemSetting['maxFullnameLength'];
            $minFName = $setting->systemSetting['minFullnameLength'];
            $maxUName = $setting->systemSetting['maxUsernameLength'];
            $minUName = $setting->systemSetting['minUsernameLength'];
            $maxPass  = $setting->systemSetting['maxPasswordLength'];
            $minPass  = $setting->systemSetting['minPasswordLength'];
            $maxTPass = $setting->systemSetting['maxTransactionPasswordLength'];
            $minTPass = $setting->systemSetting['minTransactionPasswordLength'];
            
            //checking register type
            if (empty($registerType)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00288"][$language] /* Registration not found */, 'data'=> "");
            } else {
                $defaultRegType = array('free', 'package', 'pin');
                if(!in_array($registerType, $defaultRegType))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00289"][$language] /* Invalid registration. */, 'data'=>'');
            }
            //checking register type
            if ($registerType == 'pin') {
                if (empty($codeNum)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'pinError',
                                                'msg' => $translations["E00290"][$language] /* Please fill in pin number. */
                                            );
                } else {
                    $db->where('code', $codeNum);
                    $pinData = $db->getOne("mlm_pin");
                    if(empty($pinData)) {
                        $errorFieldArr[] = array(
                                                    'id'  => 'pinError',
                                                    'msg' => $translations["E00291"][$language] /* Pin invalid */
                                                );
                    } else {
                        if ($pinData['status'] != 'New') {
                            $errorFieldArr[] = array(
                                                        'id'  => 'pinError',
                                                        'msg' => $translations["E00292"][$language] /* Pin are expired */
                                                    );
                        }
                    }
                }
            } elseif ($registerType == 'package') {
                if (empty($codeNum)) {
                     $errorFieldArr[] = array(
                                                'id'  => 'packageError',
                                                'msg' => $translations["E00293"][$language] /* Please select a package */
                                            );
                } else {
                    $db->where('id', $codeNum);
                    $packageData = $db->getOne("mlm_product");
                    if (empty($packageData)) {
                        $errorFieldArr[] = array(
                                                    'id'  => 'packageError',
                                                    'msg' => $translations["E00294"][$language] /* Package Invalid */
                                                );
                    } else {
                        if ($packageData['status'] != 'Active') {
                            $errorFieldArr[] = array(
                                                        'id'  => 'packageError',
                                                        'msg' => $translations["E00295"][$language] /* Package are expired */
                                                    );
                        }
                    }
                }
            } elseif ($registerType == 'free') {
                if (empty($codeNum)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'packageError',
                                                'msg' => $translations["E00293"][$language] /* Please select a package */
                                            );
                } else {
                    $db->where('id', $codeNum);
                    $freePackageData = $db->getOne("mlm_product");
                    if (empty($freePackageData)) {
                        $errorFieldArr[] = array(
                                                    'id'  => 'packageError',
                                                    'msg' => $translations["E00294"][$language] /* Package Invalid */
                                                );
                    } else {
                        if ($freePackageData['status'] != 'Active') {
                            $errorFieldArr[] = array(
                                                        'id'  => 'packageError',
                                                        'msg' => $translations["E00295"][$language] /* Package are expired */
                                                    );
                        }
                    }
                }
            }
            //checking full name
            if (empty($fullName)) {
                $errorFieldArr[] = array(
                                            'id'    => 'fullNameError',
                                            'msg'   => $translations["E00296"][$language] /* Please fill in full name */
                                        );
            } else {
                if (strlen($fullName)<$minFName || strlen($fullName)>$maxFName) {
                    $errorFieldArr[] = array(
                                                'id'    => 'fullNameError',
                                                'msg'   => $translations["E00297"][$language] /* Full name cannot be less than  */ . $minFName . $translations["E00298"][$language] /*  or more than  */ . $maxFName . '.'
                                            );
                }
            }
            //checking username
            if (empty($username)) {
                $errorFieldArr[] = array(
                                            'id'    => 'usernameError',
                                            'msg'   => $translations["E00299"][$language] /* Please fill in username */
                                        );
            } else {
                if (strlen($username)<$minUName || strlen($username)>$maxUName) {
                    $errorFieldArr[] = array(
                                                'id'  => 'usernameError',
                                                'msg' => $translations["E00300"][$language] /* Username cannot be less than */ . $minUName . $translations["E00301"][$language] /*  or more than  */ . $maxUName . '.'
                                            );
                } else {
                    $db->where("username", $username);
                    $result = $db->getOne("client");
                    if(!empty($result)) {
                        $errorFieldArr[] = array(
                                                    'id'  => 'usernameError',
                                                    'msg' => $translations["E00302"][$language] /* Username unavailable */
                                                );
                    }
                }
            }
            //checking country id
            if (empty($country) || !ctype_digit($country)) {
                $errorFieldArr[] = array(
                                            'id'    => 'countryError',
                                            'msg'   => $translations["E00303"][$language] /* Please select country */
                                        );
            }
            //checking country name
            // if (empty($countryName)) {
            //     $errorFieldArr[] = array(
            //                                 'id'    => 'countryError',
            //                                 'msg'   => $translations["E00303"][$language] /* Please select country */
            //                             );
            // } else {
            //     $checkCountryName = str_replace(" ", "", $countryName);
            //     if (!ctype_alpha($checkCountryName)) {
            //          $errorFieldArr[] = array(
            //                                 'id'    => 'countryError',
            //                                 'msg'   => $translations["E00304"][$language] /* Country no found */
            //                             );
            //     }
            // }
            //checking phone number
            if (empty($dialingArea) && empty($phone)) {
                $errorFieldArr[] = array(
                                            'id'    => 'phoneError',
                                            'msg'   => $translations["E00305"][$language] /* Please fill in phone number */
                                        );
            }
            //checking password
            if (empty($password)) {
                $errorFieldArr[] = array(
                                            'id'    => 'passwordError',
                                            'msg'   => $translations["E00306"][$language] /* Please fill in password */
                                        );
            } else {
                if (strlen($password)<$minPass || strlen($password)>$maxPass) {
                    $errorFieldArr[] = array(
                                                'id'  => 'passwordError',
                                                'msg' => $translations["E00307"][$language] /* Password cannot be less than  */ . $minPass . $translations["E00308"][$language] /*  or more than  */ . $maxPass . '.'
                                            );
                }
            }
            //checking re-type password
            if (empty($checkPassword)) {
                $errorFieldArr[] = array(
                                            'id'    => 'checkPasswordError',
                                            'msg'   => $translations["E00306"][$language] /* Please fill in password */
                                        );
            } else {
                if ($checkPassword != $password) {
                    $errorFieldArr[] = array(
                                                'id'  => 'checkPasswordError',
                                                'msg' => $translations["E00309"][$language] /* Password not match */
                                            );
                }
            }
            //checking transaction password
            if (empty($tPassword)) {
                $errorFieldArr[] = array(
                                            'id'    => 'tPasswordError',
                                            'msg'   => $translations["E00310"][$language] /* Please fill in transaction password */
                                        );
            } else {
                if (strlen($tPassword)<$minTPass || strlen($tPassword)>$maxTPass) {
                    $errorFieldArr[] = array(
                                                'id'  => 'tPasswordError',
                                                'msg' => $translations["E00311"][$language] /* Transaction password cannot be less than */ . $minTPass . $translations["E00312"][$language] /*  or more than  */ . $maxTPass . '.'
                                            );
                }
            }
            //checking re-type transaction password
            if (empty($checkTPassword)) {
                $errorFieldArr[] = array(
                                            'id'    => 'checkTPasswordError',
                                            'msg'   => $translations["E00310"][$language] /* Please fill in transaction password */
                                        );
            } else {
                if ($checkTPassword != $tPassword) {
                    $errorFieldArr[] = array(
                                                'id'  => 'checkTPasswordError',
                                                'msg' => $translations["E00313"][$language] /* Transaction password not match */
                                            );
                }
            }
            //checking address
            if (empty($address)) {
                $errorFieldArr[] = array(
                                            'id'  => 'addressError',
                                            'msg' => $translations["E00314"][$language] /* Please fill in address */
                                        );
            }
            //checking state
            // if (empty($state)) {
            //     $errorFieldArr[] = array(
            //                                 'id'  => 'stateError',
            //                                 'msg' => $translations["E00315"][$language] /* Please fill in state */
            //                             );
            // }
            //checking county
            // if (empty($county)) {
            //     $errorFieldArr[] = array(
            //                                 'id'  => 'countyError',
            //                                 'msg' => $translations["E00316"][$language] /* Please fill in county */
            //                             );
            // }
            //checking city
            // if (empty($city)) {
            //     $errorFieldArr[] = array(
            //                                 'id'  => 'cityError',
            //                                 'msg' => $translations["E00317"][$language] /* Please fill in city */
            //                             );
            // }
            //checking email
            if (empty($email)) {
                $errorFieldArr[] = array(
                                            'id'  => 'emailError',
                                            'msg' => $translations["E00318"][$language] /* Please fill in email */
                                        );
            } else {
                if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'emailError',
                                                'msg' => $translations["E00319"][$language] /* Invalid email format. */
                                            ); 
                }
            }
            //checking sponsor
            $sponsorFlag = false;
            if (empty($sponsorName)) {
                $errorFieldArr[] = array(
                                            'id'  => 'sponsorUsernameError',
                                            'msg' => $translations["E00320"][$language] /* Please fill in sponsor */
                                        );
            } else {
                $db->where("username", $sponsorName);
                $sponsorID = $db->getValue("client", "id");
                if(empty($sponsorID)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'sponsorUsernameError',
                                                'msg' => $translations["E00321"][$language] /* Invalid sponsor */
                                            );
                }
                else
                    $sponsorFlag = true;
            }
             //checking placement username
            $placementFlag = false;
            if (empty($placementUsername)) {
                $errorFieldArr[] = array(
                                            'id'    => 'placementUsernameError',
                                            'msg'   => $translations["E00322"][$language] /* Please fill in placement username */
                                        );
            }
            else {
                $db->where('username', $placementUsername);
                $uplineID = $db->getValue('client', 'id');

                if(empty($uplineID)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'placementUsernameError',
                                                'msg' => $translations["E00323"][$language] /* Invalid username. */
                                            );
                }
                else
                    $placementFlag = true;
            }

            if($sponsorFlag && $placementFlag) {
                $result = $this->getTreePlacementPositionValidity($sponsorID, $uplineID);
                if($result['status'] == "error") {
                    $errorFieldArr[] = array(
                                                'id'  => 'placementUsernameError',
                                                'msg' => $translations["E00324"][$language] /* Placement no found */
                                            );
                }
            }
            //checking placement position
            if (empty($placementPosition)) {
                $errorFieldArr[] = array(
                                            'id'    => 'placementPositionError',
                                            'msg'   => $translations["E00325"][$language] /* Please select placement position */
                                        );
            } elseif ($sponsorFlag && $placementFlag) {
                $result = $this->getTreePlacementPositionAvailability($uplineID, $placementPosition);
                if($result['status'] == "error") {
                    $errorFieldArr[] = array(
                                            'id'    => 'placementPositionError',
                                            'msg'   => $translations["E00326"][$language] /* Placement position has been taken. Please choose a different position */
                                        );
                }
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;

                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00327"][$language] /* Data does not meet requirements. */, 'data'=>$data);
            }

            $data['resultType'] = $resultType;
            $data['sponsorID']  = $sponsorID;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function memberRegistrationConfirmation($params) {
            $db                 = $this->db;
            $setting            = $this->setting;
            $cash               = $this->cash;
            $tree               = $this->tree;
            $activity           = $this->activity;
            $invoice            = $this->invoice;
            $bonus              = $this->bonus;
            $language           = $this->general->getCurrentLanguage();
            $translations       = $this->general->getTranslations();

            $creditData         = $params['creditData'];
            $registerType       = trim($params['registerType']);
            $fullName           = trim($params['fullName']);
            $username           = trim($params['username']);
            $address            = empty(trim($params['address'])) ? "" : trim($params['address']);
            $country            = trim($params['country']);
            $state              = empty(trim($params['state'])) ? "" : trim($params['state']);
            $county             = empty(trim($params['county'])) ? "" : trim($params['county']);
            $countryName        = trim($params['countryName']);
            $city               = empty(trim($params['city'])) ? "" : trim($params['city']);
            $phone              = trim($params['phone']);
            $email              = trim($params['email']);
            $password           = trim($params['password']);
            $tPassword          = trim($params['tPassword']);
            $sponsorName        = trim($params['sponsorName']);
            $placementUsername  = trim($params['placementUsername']);
            $placementPosition  = trim($params['placementPosition']);
            $codeNum            = trim($params['codeNum']);

            $maxFName       = $setting->systemSetting['maxFullnameLength'];
            $minFName       = $setting->systemSetting['minFullnameLength'];
            $maxUName       = $setting->systemSetting['maxUsernameLength'];
            $minUName       = $setting->systemSetting['minUsernameLength'];
            $maxPass        = $setting->systemSetting['maxPasswordLength'];
            $minPass        = $setting->systemSetting['minPasswordLength'];
            $maxTPass       = $setting->systemSetting['maxTransactionPasswordLength'];
            $minTPass       = $setting->systemSetting['minTransactionPasswordLength'];

            //checking register type
            if (empty($registerType)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00288"][$language] /* Registration no found. */, 'data'=> "");
            } else {
                $defaultRegType = array('free', 'package', 'pin');
                if(!in_array($registerType, $defaultRegType))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00289"][$language] /* Invalid registration. */, 'data'=>'');
            }
            
            // Get latest unit price
            $unitPrice = $this->getLatestUnitPrice();

            //checking sponsor
            $sponsorFlag = false;
            if (empty($sponsorName)) {
                $errorFieldArr[] = array(
                                            'id'  => 'sponsorUsernameError',
                                            'msg' => $translations["E00320"][$language] /* Please fill in sponsor */
                                        );
            } else {
                $db->where("username", $sponsorName);
                $sponsorID = $db->getValue("client", "id");
                if(empty($sponsorID)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'sponsorUsernameError',
                                                'msg' => $translations["E00321"][$language] /* Invalid sponsor */
                                            );
                }
                else
                    $sponsorFlag = true;
            }
            //checking register type
            if ($registerType == 'pin') {
                if (empty($codeNum)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'pinError',
                                                'msg' => $translations["E00290"][$language] /* Please fill in pin number. */
                                            );
                } else {
                    $db->where('code', $codeNum);
                    $pinData        = $db->getOne("mlm_pin", "id, product_id, belong_id, status");
                    $productID      = $pinData['product_id'];
                    $pinID          = $pinData['id'];
                    $pinBelongID    = $pinData['belong_id'];
                    
                    $db->where('id', $productID);
                    $checkingPackageId = $db->getOne("mlm_product", "price, expire_at");
                    $price             = $checkingPackageId['price'];
                    $expireAt          = $checkingPackageId['expire_at'];
                    
                    if (empty($pinData)) {
                        $errorFieldArr[] = array(
                                                    'id'  => 'pinError',
                                                    'msg' => $translations["E00291"][$language] /* Pin invalid */
                                                );
                    } else {
                        if ($pinData['status'] != 'New') {
                            $errorFieldArr[] = array(
                                                        'id'  => 'pinError',
                                                        'msg' => $translations["E00291"][$language] /* Pin invalid */
                                                    );
                        }
                    }
                    if (empty($productID)) {
                        $errorFieldArr[] = array(
                                                    'id'  => 'pinError',
                                                    'msg' => $translations["E00291"][$language] /* Pin invalid */
                                                );
                    }
                }
            } elseif ($registerType == 'package') {
                $db->where("id", $sponsorID);
                $buyerID = $db->getValue("client", "id");
                if (empty($buyerID)) {
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00328"][$language] /* Invalid Buyer */, 'data' => '');
                }
                if (empty($codeNum)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'packageError',
                                                'msg' => $translations["E00293"][$language] /* Please select a package */
                                            );
                } else {
                    $db->where("id", $codeNum);
                    $checkingPackageId = $db->getOne("mlm_product", "price, status, expire_at");
                    $price             = $checkingPackageId['price'];
                    $status            = $checkingPackageId['status'];
                    $expireAt          = $checkingPackageId['expire_at'];

                    if (empty($checkingPackageId)) {
                        $errorFieldArr[] = array(
                                                    'id'    => 'packageIdError',
                                                    'msg'   => $translations["E00294"][$language] /* Package Invalid */
                                                );
                    } else {
                        if ($status != 'Active') {
                            $errorFieldArr[] = array(
                                                        'id'    => 'packageIdError',
                                                        'msg'   => $translations["E00294"][$language] /* Package Invalid */
                                                    );
                        }
                    }
                }
                // checking credit type and amount
                if (empty($creditData)) {
                    $errorFieldArr[] = array(
                                                'id'    => 'totalError',
                                                'msg'   => $translations["E00329"][$language] /* Please enter an amount */
                                            );
                } else {
                    $totalAmount = 0;
                    foreach ($creditData as $value) {
                        $balance      = $cash->getClientCacheBalance($sponsorID, $value['creditType']);
                        if ($value['paymentAmount'] > $balance || !is_numeric($value['paymentAmount']) || $value['paymentAmount'] < 0) {
                            $errorFieldArr[] = array(
                                                        'id'    => $value['creditType'].'Errror',
                                                        'msg'   => $translations["E00330"][$language] /* Amount is required or invalid */
                                                    );
                        }
                        $totalAmount = $totalAmount + $value['paymentAmount'];
                    }
                    //matching total amount with price 
                    if ($totalAmount == 0) {
                        $errorFieldArr[] = array(
                                                    'id'    => 'totalError',
                                                    'msg'   => $translations["E00331"][$language] /* Please enter an amount */
                                                );
                    }
                    if ($totalAmount < $price * $unitPrice) {
                        $errorFieldArr[] = array(
                                                    'id'    => 'totalError',
                                                    'msg'   => $translations["E00332"][$language] /* Insufficient credit. */
                                                );
                    } 
                    if ($totalAmount > $price * $unitPrice) {
                            $errorFieldArr[] = array(
                                                        'id'    => 'totalError',
                                                        'msg'   => $translations["E00333"][$language] /* Credit total does not match with total cost. */
                                                    );
                    }
                    $productID     = $codeNum;       
                }
            } elseif ($registerType == 'free') {
                if (empty($codeNum)) {
                    $errorFieldArr[] = array(
                                                        'id'    => 'packageIdError',
                                                        'msg'   => $translations["E00294"][$language] /* Package Invalid */
                                                    );
                } else {
                    $db->where("id", $codeNum);
                    $db->where("category", "Package");
                    $checkingPackageId = $db->getOne("mlm_product", "price, status, expire_at");
                    $price             = 0;
                    $status            = $checkingPackageId['status'];
                    $expireAt          = $checkingPackageId['expire_at'];

                    if (empty($checkingPackageId)) {
                        $errorFieldArr[] = array(
                                                    'id'    => 'packageIdError',
                                                    'msg'   => $translations["E00294"][$language] /* Package Invalid */
                                                );
                    } else {
                        if ($status != 'Active') {
                            $errorFieldArr[] = array(
                                                        'id'    => 'packageIdError',
                                                        'msg'   => $translations["E00294"][$language] /* Package Invalid */
                                                    );
                        }
                    }
                    $productID = $codeNum; 
                }
            }
            //checking full name
            if (empty($fullName)) {
                $errorFieldArr[] = array(
                                            'id'    => 'fullNameError',
                                            'msg'   => $translations["E00296"][$language] /* Please fill in full name */
                                        );
            } else {
                if (strlen($fullName)<$minFName || strlen($fullName)>$maxFName) {
                    $errorFieldArr[] = array(
                                                'id'    => 'fullNameError',
                                                'msg'   => $translations["E00297"][$language] /* Full name cannot be less than  */ . $minFName . $translations["E00298"][$language] /*  or more than  */ . $maxFName . '.'
                                            );
                }
            }
            //checking username
            if (empty($username)) {
                $errorFieldArr[] = array(
                                            'id'    => 'usernameError',
                                            'msg'   => $translations["E00299"][$language] /* Please fill in username */
                                        );
            } else {
                if (strlen($username)<$minUName || strlen($username)>$maxUName) {
                    $errorFieldArr[] = array(
                                                'id'  => 'usernameError',
                                                'msg' => $translations["E00300"][$language] /* Username cannot be less than */ . $minUName . $translations["E00301"][$language] /*  or more than  */ . $maxUName.'.'
                                            );
                } else {
                    $db->where("username", $username);
                    $result = $db->getOne("client");
                    if(!empty($result)) {
                        $errorFieldArr[] = array(
                                                    'id'  => 'usernameError',
                                                    'msg' => $translations["E00302"][$language] /* Username unavailable */
                                                );
                    }
                }
            }
            //checking country id
            if (empty($country) || !ctype_digit($country)) {
                $errorFieldArr[] = array(
                                            'id'    => 'countryError',
                                            'msg'   => $translations["E00303"][$language] /* Please select country */
                                        );
            }
            //checking country name
            // if (empty($countryName)) {
            //     $errorFieldArr[] = array(
            //                                 'id'    => 'countryError',
            //                                 'msg'   => $translations["E00303"][$language] /* Please select country */
            //                             );
            // } else {
            //     $checkCountryName = str_replace(" ", "", $countryName);
            //     if (!ctype_alpha($checkCountryName)) {
            //          $errorFieldArr[] = array(
            //                                 'id'    => 'countryError',
            //                                 'msg'   => $translations["E00304"][$language] /* Country no found */
            //                             );
            //     }
            // }
            //checking address
            if (empty($address)) {
                $errorFieldArr[] = array(
                                            'id'  => 'addressError',
                                            'msg' => $translations["E00314"][$language] /* Please fill in address */
                                        );
            }
            //checking state
            // if (empty($state)) {
            //     $errorFieldArr[] = array(
            //                                 'id'  => 'stateError',
            //                                 'msg' => $translations["E00315"][$language] /* Please fill in state */
            //                             );
            // }
            //checking county
            // if (empty($county)) {
            //     $errorFieldArr[] = array(
            //                                 'id'  => 'countyError',
            //                                 'msg' => $translations["E00316"][$language] /* Please fill in county */
            //                             );
            // }
            //checking city
            // if (empty($city)) {
            //     $errorFieldArr[] = array(
            //                                 'id'  => 'cityError',
            //                                 'msg' => $translations["E00317"][$language] /* Please fill in city */
            //                             );
            // }
            //checking phone number
            if (empty($phone)) {
                $errorFieldArr[] = array(
                                            'id'    => 'phoneError',
                                            'msg'   => $translations["E00305"][$language] /* Please fill in phone number */
                                        );
            }
            //checking password
            if (empty($password)) {
                $errorFieldArr[] = array(
                                            'id'    => 'passwordError',
                                            'msg'   => $translations["E00306"][$language] /* Please fill in password */
                                        );
            } else {
                if (strlen($password)<$minPass || strlen($password)>$maxPass) {
                    $errorFieldArr[] = array(
                                                'id'  => 'passwordError',
                                                'msg' => $translations["E00307"][$language] /* Password cannot be less than  */ . $minPass . $translations["E00308"][$language] /*  or more than  */ . $maxPass . '.'
                                            );
                }
            }
            //checking transaction password
            if (empty($tPassword)) {
                $errorFieldArr[] = array(
                                            'id'    => 'tPasswordError',
                                            'msg'   => $translations["E00310"][$language] /* Please fill in transaction password */
                                        );
            } else {
                if (strlen($tPassword)<$minTPass || strlen($tPassword)>$maxTPass) {
                    $errorFieldArr[] = array(
                                                'id'  => 'tPasswordError',
                                                'msg' => $translations["E00311"][$language] /* Transaction password cannot be less than */ . $minTPass . $translations["E00312"][$language] /*  or more than  */ . $maxTPass . '.'
                                            );
                }
            }
            //checking email
            if (empty($email)) {
                $errorFieldArr[] = array(
                                            'id'  => 'emailError',
                                            'msg' => $translations["E00318"][$language] /* Please fill in email */
                                        );
            } else {
                if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'emailError',
                                                'msg' => $translations["E00319"][$language] /* Invalid email format. */
                                            ); 
                }
            }
             //checking placement username
            $placementFlag = false;
            if (empty($placementUsername)) {
                $errorFieldArr[] = array(
                                            'id'    => 'placementUsernameError',
                                            'msg'   => $translations["E00322"][$language] /* Please fill in placement username */
                                        );
            }
            else {
                $db->where('username', $placementUsername);
                $uplineID = $db->getValue('client', 'id');

                if(empty($uplineID)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'placementUsernameError',
                                                'msg' => $translations["E00323"][$language] /* Invalid username. */
                                            );
                }
                else
                    $placementFlag = true;
            }

            if($sponsorFlag && $placementFlag) {
                $result = $this->getTreePlacementPositionValidity($sponsorID, $uplineID);
                if($result['status'] == "error") {
                    $errorFieldArr[] = array(
                                                'id'  => 'placementUsernameError',
                                                'msg' => $translations["E00324"][$language] /* Placement no found */
                                            );
                }
            }
            //checking placement position
            if (empty($placementPosition)) {
                $errorFieldArr[] = array(
                                            'id'    => 'placementPositionError',
                                            'msg'   => $translations["E00325"][$language] /* Please select placement position */
                                        );
            } elseif ($sponsorFlag && $placementFlag) {
                $result = $this->getTreePlacementPositionAvailability($uplineID, $placementPosition);
                if($result['status'] == "error") {
                    $errorFieldArr[] = array(
                                            'id'    => 'placementPositionError',
                                            'msg'   => $translations["E00326"][$language] /* Placement position has been taken. Please choose a different position */
                                        );
                }
            }

            if ($errorFieldArr) {

                $data['field'] = $errorFieldArr;

                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00327"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }
            
            // Get bonusValue, tierValue from product setting for portfolio
            // Set default as 0. Just in case db don't have the value
            $bonusValue = 0;
            $tierValue = 0;
            
            $db->where("product_id", $productID);
            $db->where("name", array('bonusValue', 'tierValue'), 'IN');
            $resultValue = $db->get("mlm_product_setting", null, "name, value");
            foreach ($resultValue as $value) {
                if ($value['name'] == 'bonusValue') {
                    if($registerType != 'free')
                        $bonusValue = $value['value'];
                }
                if ($value['name'] == 'tierValue') {
                    $tierValue = $value['value'];
                }
            }

            $clientID     = $db->getNewID();
            $batchID      = $db->getNewID();
            $password     = $this->getEncryptedPassword($password);
            $tPassword    = $this->getEncryptedPassword($tPassword);

            $insertClientData = array(
                                        "id"                   => $clientID,
                                        "name"                 => $fullName,
                                        "username"             => $username,
                                        "address"              => $address,
                                        "country_id"           => $country,
                                        "state_id"             => $state,
                                        "county_id"            => $county,
                                        "city_id"              => $city,
                                        "phone"                => $phone,
                                        "email"                => $email,
                                        "type"                 => "Client",
                                        "password"             => $password,
                                        "transaction_password" => $tPassword,
                                        "sponsor_id"           => $sponsorID,
                                        "placement_id"         => $uplineID,
                                        "placement_position"   => $placementPosition,
                                        "activated"            => 0,
                                        "disabled"             => 0,
                                        "suspended"            => 0,
                                        "freezed"              => 0,
                                        "deleted"              => 0,
                                        "created_at"           => $db->now(),
                                        "updated_at"           => $db->now()

                                     );

            $insertClientResult  = $db->insert('client', $insertClientData);
            // Failed to insert client account
            if (!$insertClientResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language] /* Failed to register member. */, 'data' => "");
            
            $sponsorTree = $tree->insertSponsorTree($clientID, $sponsorID);
            // Failed to insert sponsorTree
            if (!$sponsorTree)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language] /* Failed to register member. */, 'data' => "");
            
            $placementTree = $tree->insertPlacementTree($clientID, $uplineID, $placementPosition);
            // Failed to insert placementTree
            if (!$placementTree)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language] /* Failed to register member. */, 'data' => "");

            //generate belong id
            $belongID = $db->getNewID();

            // Compile portfolio data to insert here
            $portfolioData['clientID'] = $clientID;
            $portfolioData['productID'] = $productID;
            $portfolioData['price'] = $price;
            $portfolioData['bonusValue'] = $bonusValue;
            $portfolioData['tierValue'] = $tierValue;
            $portfolioData['type'] = 'Registration';
            $portfolioData['belongID'] = $belongID;
            $portfolioData['referenceID'] = '';
            $portfolioData['batchID'] = $batchID;
            $portfolioData['status'] = 'Active';
            $portfolioData['expireAt'] = $expireAt;
            $portfolioData['unitPrice'] = $unitPrice;
            
            $portfolioID = $this->insertClientPortfolio($portfolioData);
            // Failed to insert portfolio
            if (!$portfolioID)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language] /* Failed to register member. */, 'data' => "");
            
            if ($registerType == 'pin') {
                // Update pin status
                $updatePinData = array(
                    "status"         => "Used",
                    "used_at"        => $db->now(),
                    "receiver_id"    => $clientID
                );
                
                $db->where('id', $pinID);
                $updatePinDataResult = $db->update("mlm_pin", $updatePinData);
                
                // Failed to update the Pin status
                if (!$updatePinDataResult) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language] /* Failed to register member. */, 'data' => "");
                }
                
                //update portfolio id to invoice item table
                $db->where("belong_id", $pinBelongID);
                $updateInvoiceItemResult = $db->update("mlm_invoice_item", array('portfolio_id' => $portfolioID));
                // Failed to update portfolio id to invoice item table
                if (!$updateInvoiceItemResult)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language] /* Failed to register member. */, 'data' => "");
                
            } elseif ($registerType == 'package') {
                
                // Insert invoice
                $invoiceData['productId']       = $productID;
                $invoiceData['bonusValue']      = $bonusValue;
                $invoiceData['productPrice']    = $price;
                $invoiceData['unitPrice']       = $unitPrice;
                $invoiceData['belongId']        = $db->getNewID();
                $invoiceData['portfolioId']     = $portfolioID;

                $invoiceDataArr[] = $invoiceData;
                $invoiceResult    = $invoice->insertFullInvoice($buyerID, $totalAmount, $invoiceDataArr, $creditData);
                // Failed to insert invoice
                if (!$invoiceResult)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language] /* Failed to register member. */, 'data' => "");
                
                // Get receiver ID
                $db->where('username', 'creditSales');
                $db->where('type', 'Internal');
                $receiverID = $db->getValue("client", "id");
                    
                // To deduct the balance of the sponsor
                foreach ($creditData as $key) {
                    $minusBalanceResult = $cash->insertTAccount($sponsorID, $receiverID, $key['creditType'], $key['paymentAmount'], "Registration", $db->getNewID(), "", $db->now(), $batchID, $buyerID);
                    
                    // Failed to insertTAccount
                    if (!$minusBalanceResult) {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language] /* Failed to register member. */, 'data' => "");
                    }
                }
            }
            
            // Insert client settings for this registered client
            $clientSettingData['productID']         = $productID;
            $clientSettingData['productBelongID']   = $db->getNewID();
            $clientSettingData['productBatchID']    = $batchID;
            $clientSettingData['remark']            = '';
            $clientSettingData['subject']           = 'Registration';
            $clientSettingData['clientID']          = $clientID;
            
            $insertClientSettingResult = $this->insertClientSettingByProductSetting($clientSettingData);
            // Failed to insert client setting
            if($insertClientSettingResult['status'] == 'error')
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00335"][$language] /* Failed to insert client settings. */, 'data' => "");
            
            // Insert bonus in
            $bonusInData['clientID']    = $clientID;
            $bonusInData['type']        = $registerType;
            $bonusInData['productID']   = $productID;
            $bonusInData['belongID']    = $belongID; //need to same with portfolio for cancellation
            $bonusInData['batchID']     = $batchID;
            $bonusInData['bonusValue']  = $bonusValue;
            
            $insertBonusResult = $bonus->insertBonusValue($bonusInData);
            // Failed to insert bonus
            if (!$insertBonusResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00336"][$language] /* Failed to insert bonus. */, 'data' => "");

            //update client's upline placement bonus
            $instantUpdateClientPlacementBonusResult = $bonus->instantUpdateClientPlacementBonus($clientID, $bonusValue);

            if (!$instantUpdateClientPlacementBonusResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00336"][$language] /* Failed to insert bonus. */, 'data' => "");
            
            $activityData = array('user' => $username);
            $activityRes = $activity->insertActivity('Registration', 'T00001', 'L00001', $activityData, $clientID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00337"][$language] /* Failed to insert activity. */, 'data' => "");
            
            $data['invoiceID'] = $invoiceResult;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00145"][$language] /* Registration successful. */, 'data' => $data);
        }

        public function verifyPayment($params) {
            $db                  = $this->db;
            $cash                = $this->cash;
            $setting             = $this->setting;
            $activity            = $this->activity;
            $product             = $this->product;
            $language            = $this->general->getCurrentLanguage();
            $translations        = $this->general->getTranslations();

            $clientId            = $params['clientId'];
            $packageId           = $params['packageId'];
            $tPassword           = trim($params['tPassword']);
            $creditData          = $params['creditData'];

            // Get password encryption type
            $passwordEncryption  = $setting->getMemberPasswordEncryption();
            // Get latest unit price
            $unitPrice = $this->getLatestUnitPrice();
            
            //checking client ID
            if (empty($clientId)) {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00338"][$language] /* Client not found. */, 'data' => '');
            } else {
                $db->where("id", $clientId);
                $id = $db->getValue("client", "id");

                if (empty($id)) {
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00338"][$language] /* Client not found. */, 'data' => '');
                }
            }
            //checking package ID
            if (empty($packageId)) {
                $errorFieldArr[] = array(
                                            'id'    => 'packageIdError',
                                            'msg'   => $translations["E00339"][$language] /* Invalid package */
                                        );
            }else {
                $db->where("id", $packageId);
                $db->where("category", 'Package');
                $checkingPackageId = $db->getOne("mlm_product", "price, status");
                $price             = $checkingPackageId['price'] * $unitPrice;
                $status            = $checkingPackageId['status'];

                if (empty($checkingPackageId)) {
                    $errorFieldArr[] = array(
                                                'id'    => 'packageIdError',
                                                'msg'   => $translations["E00339"][$language] /* Invalid package */
                                            );
                } else {
                    if ($status != 'Active') {
                        $errorFieldArr[] = array(
                                                    'id'    => 'packageIdError',
                                                    'msg'   => $translations["E00339"][$language] /* Invalid package */
                                                );
                    }
                }
            }
            // checking credit type and amount
            if (empty($creditData)) {
                $errorFieldArr[] = array(
                                            'id'    => 'totalError',
                                            'msg'   => $translations["E00340"][$language] /* Please enter an amount. */
                                        );
            }
            $totalAmount = 0;
            foreach ($creditData as $value) {
                $balance = $cash->getClientCacheBalance($id, $value['creditType']);
                if (!is_numeric($value['paymentAmount']) || $value['paymentAmount'] < 0) {
                    $errorFieldArr[] = array(
                                                'id'    => $value['creditType'].'Error',
                                                'msg'   => $translations["E00341"][$language] /* Amount is required or invalid */
                                            );
                } else {
                    if ($value['paymentAmount'] > $balance){
                        $errorFieldArr[] = array(
                                                    'id'    => $value['creditType'].'Error',
                                                    'msg'   => $translations["E00342"][$language] /* Insufficient credit. */
                                                );
                    }

                    $minMaxResult = $product->checkMinMaxPayment($price, $value['paymentAmount'], $value['creditType'], "Registration");
                    if($minMaxResult["status"] != "ok"){
                        $errorFieldArr[] = array(
                                                'id'    => $value['creditType'].'Error',
                                                'msg'   => $minMaxResult["statusMsg"]
                                            );
                    }

                    $totalAmount = $totalAmount + $value['paymentAmount'];
                    //matching amount with price 
                    
                }
            }

            if ($totalAmount == 0) {
                $errorFieldArr[] = array(
                                            'id'    => 'totalError',
                                            'msg'   => $translations["E00343"][$language] /* Please enter an amount. */
                                        );
            }

            if ($totalAmount < $price) {
                $errorFieldArr[] = array(
                                            'id'    => 'totalError',
                                            'msg'   => $translations["E00342"][$language] /* Insufficient credit. */
                                        );
            }
            if ($totalAmount > $price) {
                $errorFieldArr[] = array(
                                            'id'    => 'totalError',
                                            'msg'   => $translations["E00344"][$language] /* Credit total does not match with total cost. */
                                        );
            }      
            //checking transaction password
            if ($activity->creatorType == "Member") {
                if (empty($tPassword)) {
                    $errorFieldArr[] = array(
                                                'id'    => 'tPasswordError',
                                                'msg'   => $translations["E00345"][$language] /* Please enter transaction password. */
                                            );
                } else {
                    $result = $this->verifyTransactionPassword($clientId, $tPassword);
                    if($result['status'] != "ok") {
                        $errorFieldArr[] = array(
                                                    'id'  => 'tPasswordError',
                                                    'msg' => $translations["E00346"][$language] /* Invalid password. */
                                                );
                    }
                }
            }
            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;

                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00347"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        } 

        public function getCreditTransactionList($params) {
            $db           = $this->db;
            $general      = $this->general;
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            $decimalPlaces  = $this->setting->getSystemDecimalPlaces();

            $creditType   = $params['creditType'];
            $searchData   = $params['searchData'];
            
            //Get the limit.
            $pageNumber   = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = $general->getLimit($pageNumber);

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'fullName':
                            $clientNameID = $db->subQuery();
                            $clientNameID->where('name', $dataValue);
                            $clientNameID->get('client', NULL, "id");
                            $db->where('client_id', $clientNameID, 'in');
                            break;

                        case 'userName':
                            $clientUsernameID = $db->subQuery();
                            $clientUsernameID->where('username', $dataValue);
                            $clientUsernameID->getOne('client', "id");
                            $db->where('client_id', $clientUsernameID);
                            break;
                            
                        case 'memberId':
                            $db->where('client_id', $dataValue);  
                            break;
                            
                        case 'transactionType':
                            $db->where('subject', $dataValue);
                            break;
                            
                        case 'toFromId':
                            $fromUsernameID = $db->subQuery();
                            $fromUsernameID->where('username', $dataValue);
                            $fromUsernameID->getOne('client', "id");
                            $db->where('from_id', $fromUsernameID);
                            $db->orwhere('to_id', $toUsernameID);
                            break;
                            
                        case 'searchDate':
                            $columnName = 'created_at';
                            $dateFrom   = trim($v['tsFrom']);
                            $dateTo     = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00348"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00348"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00349"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
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

            if (empty($creditType)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00350"][$language] /* Please select a credit type */, 'data' => "");
            } else {
                $db->where('type', $creditType);
            }

            $copyDb = $db->copy();
            $db->orderBy('created_at', "DESC");

            $getUsername = "(SELECT username FROM client WHERE client.id=client_id) AS username";
            $getName     = "(SELECT name FROM client WHERE client.id=client_id) AS name";

            $result = $db->get("credit_transaction", $limit, $getUsername.','.$getName.", client_id, subject, from_id, to_id, amount, remark, batch_id, creator_id, creator_type, created_at");
            
            if (!empty($result)) {
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

                foreach($result as $value) {
                    $clientIDs[] = $value['client_id'];
                }

                $db->where('name', $creditType);
                $db->where('client_id', $clientIDs, 'IN');
                $clientsBalance = $db->get('client_setting', null, 'value, client_id');
                if(empty($clientsBalance))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00351"][$language] /* Failed to load credit transaction listing. */, 'data' => "");

                foreach($clientsBalance as $value) {
                    $balance[$value['client_id']] = $value['value'];
                }

                foreach($result as $value) {
                    $transaction['created_at']  = $general->formatDateTimeToString($value['created_at'], "d/m/Y H:i:s");
                    $transaction['username']    = $value['username'];
                    $transaction['name']        = $value['name'];
                    $transaction['subject']     = $value['subject'];
                    if($value['subject'] == "Transfer Out") {
                        $transaction['to_from'] = $batchUsername[$value['batch_id']]["Transfer In"] ? $batchUsername[$value['batch_id']]["Transfer In"] : "-";
                    }
                    else if($value['subject'] == "Transfer In") {
                        $transaction['to_from'] = $batchUsername[$value['batch_id']]["Transfer Out"] ? $batchUsername[$value['batch_id']]["Transfer Out"] : "-";
                    }
                    else if($value['from_id'] == "9")
                        $transaction['to_from'] = "bonusPayout";
                    else
                        $transaction['to_from'] = "-";

                    if($value['from_id'] >= 1000000) {
                        $transaction['credit_in'] = "-";
                        $transaction['credit_out'] = $value['amount'];
                        $transaction['balance'] = number_format($balance[$value['client_id']], $decimalPlaces, '.', '');
                        $balance[$value['client_id']] += $value['amount'];
                    }
                    else {
                        $transaction['credit_in'] = $value['amount'];
                        $transaction['credit_out'] = "-";
                        $transaction['balance'] = number_format($balance[$value['client_id']], $decimalPlaces, '.', '');
                        $balance[$value['client_id']] -= $value['amount'];
                    }

                    $transaction['creator_id'] = $usernameList[$value['creator_type']][$value['creator_id']];
                    $transaction['remark'] = $value['remark'] ? $value['remark'] : "-";

                    $transactionList[] = $transaction;
                    unset($transaction);
                }

                // This is to get the transaction type(as subject) for the search select option
                $resultType = $db->get('credit_transaction', $limit, 'subject');
                if (empty($resultType)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00352"][$language] /* Failed to get commands for search option */, 'data' => '');
                }
                foreach($resultType as $value) {
                    $searchBarData['type'] = $value['subject'];

                    $searchBarDataList[] = $searchBarData;
                }

                $totalRecord = $copyDb->getValue("credit_transaction", "count(*)");

                // remove duplicate transaction type. Then sort it alphabetically
                $searchBarDataList = array_map("unserialize", array_unique(array_map("serialize", $searchBarDataList)));
                sort($searchBarDataList);

                $data['transactionList'] = $transactionList;
                $data['transactionType'] = $searchBarDataList;
                $data['totalPage']       = ceil($totalRecord/$limit[1]);
                $data['pageNumber']      = $pageNumber;
                $data['totalRecord']     = $totalRecord;
                $data['numRecord']       = $limit[1];
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);  

            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00146"][$language] /* No result found. */, 'data'=> "");
            }
        }

        public function getImportData($params) {
            $db = $this->db;
            $general = $this->general;
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = $general->getLimit($pageNumber);

            $searchData = $params['searchData'];
            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'type':
                            $db->where('type', $dataValue);
                            break;

                        case 'createdAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00353"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where('created_at', date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00353"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00354"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

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
            $db->orderBy('id', 'Desc');
            $copyDb = $db->copy();
            $result = $db->get('mlm_import_data', $limit);

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00147"][$language] /* No result found. */, 'data' => "");

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
                $import['id'] = $value['id'];
                $import['type'] = $value['type'];
                $import['attachment_name'] = $value['attachment_name'];
                $import['username'] = $usernameList[$value['creator_type']][$value['creator_id']];
                $import['total_records'] = $value['total_records'];
                $import['total_processed'] = $value['total_processed'];
                $import['total_failed'] = $value['total_failed'];
                $import['created_at'] = $value['created_at'];

                $importList[] = $import;
            }

            $totalRecords = $copyDb->getValue('mlm_import_data', 'count(id)');
            $data['importList'] = $importList;
            $data['totalPage'] = ceil($totalRecords/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord'] = $limit[1];
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getImportDataDetails($params) {
            $db = $this->db;
            $general = $this->general;

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = $general->getLimit($pageNumber);

            $db->where('mlm_import_data_id', $params['id']);
            $copyDb = $db->copy();
            $result = $db->get('mlm_import_data_details', $limit, 'data, status, error_message');

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00148"][$language] /* No result found. */, 'data' => "");

            foreach($result as $value) {
                foreach(json_decode($value['data']) as $key => $val) {
                    $details[$key] = $val;
                }
                $details['status'] = $value['status'];
                $details['error_message'] = $value['error_message'];

                $importDetailsList[] = $details;
            }

            $totalRecords = $copyDb->getValue('mlm_import_data_details', 'count(id)');
            $data['importDetailsList'] = $importDetailsList;
            $data['totalPage'] = ceil($totalRecords/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord'] = $limit[1];
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function massChangePassword($params, $site) {
            $db = $this->db;
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $fileDataBase64 = base64_decode((string)$params['base64']);
            $tmp_handle = tempnam(sys_get_temp_dir(), 'adminMassChangePassword');

            $handle = fopen($tmp_handle, 'r+');
            fwrite($handle, $fileDataBase64);
            rewind($handle);

            $fileType = PHPExcel_IOFactory::identify($tmp_handle);
            $objReader = PHPExcel_IOFactory::createReader($fileType);
            
            $excelObj = $objReader->load($tmp_handle);
            $worksheet = $excelObj->getSheet(0);
            $lastRow = $worksheet->getHighestRow();
            $lastCol = $worksheet->getHighestColumn();
            $lastCol++;

            if($lastRow <= 1)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00355"][$language] /* The selected file cannot be empty */, 'data' => "");

            if($worksheet->getCell('B1')->getValue() != "Username")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('C1')->getValue() != "New Login Password")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('D1')->getValue() != "New Transaction Password")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            $dataInsert = array (
                                    'data' => $params['base64'],
                                    'type' => $params['type'],
                                    'created_at' => $db->now()
                                );
            $uploadID = $db->insert('uploads', $dataInsert);

            if(empty($uploadID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] /* Failed to mass change password */, 'data' => "");

            $dataInsert = array (
                                    'type' => 'adminMassChangePassword',
                                    'attachment_id' => $uploadID,
                                    'attachment_name' => $params['name'],
                                    'creator_id' => $params['clientID'],
                                    'creator_type' => $site,
                                    'created_at' => $db->now()
                                );
            $importID = $db->insert('mlm_import_data', $dataInsert);

            if(empty($importID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] /* Failed to mass change password */, 'data' => "");

            $recordCount = 0; $processedCount = 0; $failedCount = 0;

            for($row=2; $row<=$lastRow; $row++) {

                $recordCount++;

                $username = $worksheet->getCell('B'.$row)->getValue();
                $loginPassword = $worksheet->getCell('C'.$row)->getValue();
                $transactionPassword = $worksheet->getCell('D'.$row)->getValue();

                $db->where('disabled', 1);
                $specialCharacters = $db->getValue('special_characters', 'value', null);
                $pattern = "/[";
                foreach($specialCharacters as $value) {
                    $pattern = $pattern.$value;
                }
                $pattern = $pattern."]/";

                $errorMessage = "";

                if(empty($username))
                    $errorMessage = $errorMessage."Username cannot be left empty.\n";
                if(empty($loginPassword))
                    $errorMessage = $errorMessage."Login password cannot be left empty.\n";
                else if(preg_match_all($pattern, $loginPassword))
                    $errorMessage = $errorMessage."Login password cannot contain special characters.\n";
                if(empty($transactionPassword))
                    $errorMessage = $errorMessage."Transaction password cannot be left empty.\n";
                else if(preg_match_all($pattern, $transactionPassword))
                    $errorMessage = $errorMessage."Transaction password cannot contain special characters.\n";

                $db->where('username', $username);
                $checkUser = $db->getValue('client', 'username');
                if(empty($checkUser))
                    $errorMessage = $errorMessage."Member not found.\n";

                if(empty($errorMessage)) {
                    $status = "Success";
                    $processedCount++;
                    $dataUpdate = array (
                                            'password' => $this->getEncryptedPassword($loginPassword),
                                            'transaction_password' => $this->getEncryptedPassword($transactionPassword)
                                        );
                    $db->where('username', $username);
                    $db->update('client', $dataUpdate);
                }
                else {
                    $status = "Failed";
                    $failedCount++;
                }

                $json = array   (
                                    'Username' => $username,
                                    'Login Password' => $loginPassword,
                                    'Transaction Password' => $transactionPassword
                                );
                $json = json_encode($json);

                $dataInsert = array (
                                        'mlm_import_data_id' => $importID,
                                        'data' => $json,
                                        'processed' => "1",
                                        'status' => $status,
                                        'error_message' => $errorMessage
                                    );
                $ID = $db->insert('mlm_import_data_details', $dataInsert);

                if(empty($ID))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");
            }

            $dataUpdate = array (
                                    'total_records' => $recordCount,
                                    'total_processed' => $processedCount,
                                    'total_failed' => $failedCount
                                );
            $db->where('id', $importID);
            $db->update('mlm_import_data', $dataUpdate);

            $handle = fclose($handle);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        function getTreePlacementPositionAvailability($uplineID, $position) {
            $db = $this->db;
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            if(empty($uplineID) || empty($position))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00358"][$language] /* Required fields cannot be empty */, 'data' => "");

            $maxPlacementPositions = $this->setting->systemSetting['maxPlacementPositions'];

            if($position < 1 || $position > $maxPlacementPositions)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00359"][$language] /* Invalid placement. */, 'data' => "");

            $db->where('upline_id', $uplineID);
            $db->where('client_position', $position);
            $result = $db->getOne('tree_placement', 'id');

            if($db->count > 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00360"][$language] /* Position has been taken. */, 'data' => "");
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        function getTreePlacementPositionValidity($sponsorID, $uplineID, $clientID="") {
            $db = $this->db;

            if(empty($sponsorID) || empty($uplineID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");

            $db->where('client_id', $uplineID);
            $result = $db->getValue('tree_placement', 'trace_key');

            if($db->count <= 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid placement.", 'data' => "");

            $traceKey = str_replace(array("-1>","-1<","-1|","-1"), "/", $result);
            $traceKey = array_filter(explode("/", $traceKey), 'strlen');

            $uplineLevel = array_search($uplineID, $traceKey);
            $sponsorLevel = array_search($sponsorID, $traceKey);

            if(!empty($clientID)) {
                if(strlen(array_search($clientID, $traceKey)) > 0)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");
            }

            if(strlen($sponsorLevel) <= 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");
            else if($sponsorLevel > $uplineLevel)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");
            else
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        function getUpline($params) {
            $db = $this->db;
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");

            $getUsername = '(SELECT username FROM client WHERE client.id=upline_id) AS username';
            $getID = '(SELECT id FROM client WHERE client.id=upline_id) AS id';
            $getCreatedAt = '(SELECT created_at FROM client WHERE client.id=upline_id) AS created_at';
            $db->where('client_id', $params['clientID']);
            $result = $db->getOne('tree_sponsor', $getUsername.','.$getID.','.$getCreatedAt);

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00361"][$language] /* Invalid client */, 'data' => "");

            foreach($result as $key => $value) {
                $sponsorUpline[$key] = $value ? $value : "-";
            }

            unset($result);

            $getUsername = '(SELECT username FROM client WHERE client.id=upline_id) AS username';
            $getID = '(SELECT id FROM client WHERE client.id=upline_id) AS id';
            $getCreatedAt = '(SELECT created_at FROM client WHERE client.id=upline_id) AS created_at';
            $db->where('client_id', $params['clientID']);
            $result = $db->getOne('tree_placement', $getUsername.','.$getID.','.$getCreatedAt);

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid client.", 'data' => "");

            foreach($result as $key => $value) {
                $placementUpline[$key] = $value ? $value : "-";
            }

            $memberDetails = $this->getCustomerServiceMemberDetails($params['clientID']);
            $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            $data['placementUpline'] = $placementUpline;
            $data['sponsorUpline'] = $sponsorUpline;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        function getTreeSponsor($params) {
            $db = $this->db;
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00362"][$language] /* Required fields cannot be empty. */, 'data' => "");

            if($params['username']) {
                $db->where('username', $params['username']);
                $childID = $db->getValue('client', 'id');
                if(empty($childID))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00363"][$language] /* Invalid username. */, 'data' => "");

                $db->where('client_id', $params['clientID']);
                $clientTraceKey = $db->getValue('tree_sponsor', 'trace_key');
                if(empty($clientTraceKey))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00364"][$language] /* Failed to load view. */, 'data' => "");

                $db->where('trace_key', $clientTraceKey."%", 'LIKE');
                $db->where('client_id', $childID);
                $isDownline = $db->getValue('tree_sponsor', 'id');
                if(empty($isDownline)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00365"][$language] /* Invalid downline */, 'data' => "");
                } else {
                    $params['clientID'] = $childID;
                }
            }

            $db->where('id', $params['clientID']);
            $sponsor = $db->getOne('client', 'id, username, created_at, (SELECT value FROM client_setting WHERE name = "rankID" AND type = "Overall Rank" AND client_id = client.id) AS rank');

            if(empty($sponsor))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00366"][$language] /* Invalid client. */, 'data' => "");

            $column = array(
                "client_id",
                "(SELECT username FROM client WHERE client.id=client_id) AS username",
                "(SELECT created_at FROM client WHERE client.id=client_id) AS created_at",
                "(SELECT value FROM client_setting WHERE client_setting.name = 'rankID' AND client_setting.type = 'Overall Rank' AND client_setting.client_id = tree_sponsor.client_id) AS rank"
            );
            $db->where('upline_id', $sponsor['id']);
            $downlines = $db->get('tree_sponsor', null, $column);

            if(empty($downlines))
                $downlines = array();

            foreach($downlines as &$downline){
                if (empty($downline['rank']))
                    $downline['rank'] = '-';
            }

            unset($downline);

            if($params['realClientID']) {
                $db->where('client_id', $params['clientID']);
                $clientTraceKey = $db->getValue('tree_sponsor', 'trace_key');

                if($clientTraceKey) {
                    $traceKey = array_filter(explode("/", $clientTraceKey), 'strlen');

                    $realClientKey = array_search($params['realClientID'], $traceKey);
                    $clientKey = array_search($params['clientID'], $traceKey);

                    for($i=$realClientKey; $i <= $clientKey; $i++) { 
                        $breadcrumbTemp[] = $traceKey[$i];
                    }

                    $db->where('id', $breadcrumbTemp, 'IN');
                    $result = $db->get('client', null, 'id, username');

                    if($result) {
                        foreach($breadcrumbTemp as $value) {
                            $arrayKey = array_search($value, array_column($result, 'id'));
                            $breadcrumb[] = $result[$arrayKey];
                        }
                    }
                } 
            }

            $memberDetails = $this->getCustomerServiceMemberDetails($params['clientID']);
            $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            $data['breadcrumb'] = $breadcrumb;
            $data['sponsor'] = $sponsor;
            $data['downlines'] = $downlines;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        function getTreePlacement($params) {
            $db = $this->db;
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00367"][$language] /* Failed to load view. */, 'data' => "");

            if($params['username']) {
                $db->where('username', $params['username']);
                $childID = $db->getValue('client', 'id');
                if(empty($childID))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00368"][$language] /* Invalid username. */, 'data' => "");

                $db->where('client_id', $params['clientID']);
                $clientTraceKey = $db->getValue('tree_placement', 'trace_key');
                if(empty($clientTraceKey))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00367"][$language] /* Failed to load view. */, 'data' => "");

                $db->where('trace_key', $clientTraceKey."%", 'LIKE');
                $db->where('client_id', $childID);
                $isDownline = $db->getValue('tree_placement', 'id');
                if(empty($isDownline)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00369"][$language] /* Invalid downline */, 'data' => "");
                } else {
                    $params['clientID'] = $childID;
                }
            }

            $db->where('id', $params['clientID']);
            $sponsor = $db->get('client', 1, 'id AS client_id, username, created_at, (SELECT value FROM client_setting WHERE name = "rankID" AND type = "Overall Rank" AND client_id = client.id) AS rank');

            if($db->count <= 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00370"][$language] /* Invalid client. */, 'data' => "");

            $depthLevel = $params['depthLevel'] ? $params['depthLevel'] : 3;
            $upline = $sponsor;
            $sponsorDownlines = [];
            for($i = 0; $i < $depthLevel; $i++) {
                $nextGenUpline = [];
                foreach($upline as $value) {
                    $colUsername = '(SELECT c.username FROM client c WHERE c.id=t.client_id) AS username';
                    $colCreatedAt = '(SELECT c.created_at FROM client c WHERE c.id=t.client_id) AS created_at';
                    $colRank = '(SELECT value FROM client_setting WHERE client_setting.name = "rankID" AND client_setting.type = "Overall Rank" AND client_setting.client_id = t.client_id) AS rank';
                    $db->where('upline_id', $value['client_id']);
                    $downlines = $db->get('tree_placement t', null, 't.client_id, '.$colUsername.', upline_id, client_position,'.$colCreatedAt . ',' . $colRank);
                    foreach($downlines as &$downline){
                        if (empty($downline['rank']))
                            $downline['rank'] = "-";
                    }

                    unset($downline);

                    if($db->count <= 0)
                        continue;

                    $nextGenUpline = array_merge($nextGenUpline, $downlines);
                    $sponsorDownlines = array_merge($sponsorDownlines, $downlines);
                }
                $upline = $nextGenUpline;
                unset($nextGenUpline);
            }

            foreach($sponsor as $array) {
                foreach($array as $k => $v) {
                    $sponsorRow[$k] = $v;
                }
            }

            $maxPlacementPositions = $this->setting->systemSetting['maxPlacementPositions'];
            unset($downlines);
            foreach($sponsorDownlines as $array) {
                foreach($array as $k => $v) {
                    if($k == "client_position") {
                        if($maxPlacementPositions == 2)
                            $col['placement'] = $v == 1 ? "Left" : "Right";
                        else if($maxPlacementPositions == 3) {
                            if($v == 1)
                                $col['placement'] = "Left";
                            else if($v == 2)
                                $col['placement'] = "Middle";
                            else if($v == 3)
                                $col['placement'] = "Right";
                        }
                    }

                    $col[$k] = $v;
                }
                $downlines[] = $col;
            }

            if(empty($downlines))
                $downlines = array();

            if($params['realClientID']) {
                $db->where('client_id', $params['clientID']);
                $clientTraceKey = $db->getValue('tree_placement', 'trace_key');

                if($clientTraceKey) {
                    $traceKey = str_replace(array("-1>","-1<","-1|","-1"), "/", $clientTraceKey);
                    $traceKey = array_filter(explode("/", $traceKey), 'strlen');

                    $realClientKey = array_search($params['realClientID'], $traceKey);
                    $clientKey = array_search($params['clientID'], $traceKey);

                    for($i=$realClientKey; $i <= $clientKey; $i++) { 
                        $breadcrumbTemp[] = $traceKey[$i];
                    }

                    $db->where('id', $breadcrumbTemp, 'IN');
                    $result = $db->get('client', null, 'id, username');

                    if($result) {
                        foreach($breadcrumbTemp as $value) {
                            $arrayKey = array_search($value, array_column($result, 'id'));
                            $breadcrumb[] = $result[$arrayKey];
                        }
                    }
                } 
            }

            $memberDetails = $this->getCustomerServiceMemberDetails($params['clientID']);
            $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            $data['breadcrumb'] = $breadcrumb;
            $data['sponsor'] = $sponsorRow;
            $data['downlines'] = $downlines;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        function getPlacementTreeVerticalView($params) {
            $db = $this->db;
            $setting = $this->setting;
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $clientID = trim($params["clientID"]);
            $targetID = trim($params["targetID"]);
            $viewType = trim($params["viewType"]);

            if($params['username']) {
                $db->where('username', $params['username']);
                $childID = $db->getValue('client', 'id');
                if(empty($childID))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00371"][$language] /* Invalid username. */, 'data' => "");

                $db->where('client_id', $clientID);
                $clientTraceKey = $db->getValue('tree_placement', 'trace_key');
                if(empty($clientTraceKey))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00372"][$language] /* Failed to load view. */, 'data' => "");

                $db->where('trace_key', $clientTraceKey."%", 'LIKE');
                $db->where('client_id', $childID);
                $isDownline = $db->getValue('tree_placement', 'id');
                if(empty($isDownline)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00373"][$language] /* Invalid downline */, 'data' => "");
                } else {
                    $clientID = trim($childID);
                    $targetID = trim($childID);
                }
            }

            $maxPlacementPositions = $setting->systemSetting["maxPlacementPositions"];

            if(strlen($clientID) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00374"][$language] /* Invalid client. */, 'data' => "clientID");
            if(!$viewType)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00375"][$language] /* Select view type */, 'data' => array('field' => "targetID"));


            for($i=1; $i<=$maxPlacementPositions; $i++) {
                $clientSettingName[] = "'Placement Total $i'";
                $clientSettingName[] = "'Placement CF Total $i'";
            }

            $db->where("id", $targetID);
            // $db->where("type", "Member");
            $result = $db->getOne("client", "id");
            if(!$result)
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00149"][$language] /* Client not found */, 'data' => array('field' => "targetID"));


            $db->where("client_id", $targetID);
            $targetClient = $db->getOne("tree_placement", "level, trace_key");            

            $filterTraceKey = strstr($targetClient["trace_key"], $clientID);

            $targetTraceKey = preg_split('/(?<=[0-9])(?=[<|>]+)/i', $filterTraceKey);


            foreach ($targetTraceKey as $key => $val) {
                if(!is_numeric($val[0])){
                    $targetUplinesID[] = explode("-", substr($val, 1))[0];

                }else{
                    $targetUplinesID[] = explode("-", $val)[0];
                }
            }

            $db->where("client_id" , $targetUplinesID, "IN");
            $targetUplinesAry = $db->get("tree_placement", null, "client_id,client_position,level,trace_key");
            
            $db->where("id" , $targetUplinesID, "IN");
            $targetUplinesClient = $db->map ('id')->ObjectBuilder()->get("client", null, "id, username, name, created_at");
            
            foreach ($targetUplinesAry as $key => $upline) {
                $uplineID = $upline['client_id'];
                $username = $targetUplinesClient[$uplineID]->username;
                $name = $targetUplinesClient[$uplineID]->name;
                $createdAt = $targetUplinesClient[$uplineID]->created_at;

                $tree['attr']['ID'] = $uplineID;
                $tree['attr']['name'] = $name;
                $tree['attr']['username'] = $username;
                // Build the level from clientID to targetID
                $data['treeLink'][] = $tree;

                if($uplineID == $targetID) {

                    $data['target']['attr']['id'] = $uplineID;
                    $data['target']['attr']['username'] = $username;
                    $data['target']['attr']['name'] = $name;
                    $data['target']['attr']['createdAt'] = date("d/m/Y", strtotime($createdAt));

                    $targetLevel = $upline["level"];
                }
            }

            $depthRule = "1";
            if($viewType == "Horizontal") $depthRule = "3";

            $db->where("level", $targetClient["level"], ">");
            $db->where("level", $targetClient["level"]+$depthRule, "<=");
            $db->where("trace_key", $targetClient["trace_key"]."%", "LIKE");
            $targetDownlinesAry = $db->get("tree_placement", null," client_id,client_unit,client_position,level,trace_key");

            if(count($targetDownlinesAry) == 0) return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);

            foreach ($targetDownlinesAry as $key => $val) $targetDownlinesIDAry[] = $val["client_id"];
            $db->where("id", $targetDownlinesIDAry, "in");
            $targetDownlinesClient = $db->map('id')->ObjectBuilder()->get("client",null,"id,username,name,created_at,disabled,suspended,freezed");
            
            foreach ($targetDownlinesAry as $key => $targetDownline) {
                $depth = $targetDownline["level"] - $targetLevel;
                $downlineID = $targetDownline['client_id'];
                $username = $targetDownlinesClient[$downlineID]->username;
                $name = $targetDownlinesClient[$downlineID]->name;
                $createdAt = $targetDownlinesClient[$downlineID]->created_at;
                $disabled = $targetDownlinesClient[$downlineID]->disabled;
                $suspended = $targetDownlinesClient[$downlineID]->suspended;
                $freezed = $targetDownlinesClient[$downlineID]->freezed;

                $downline['attr']['id'] = $downlineID;
                $downline['attr']['username'] = $username;
                $downline['attr']['name'] = $name;
                $downline['attr']['position'] = $targetDownline["client_position"];

                $maxPlacementPositions = $this->setting->systemSetting['maxPlacementPositions'];
                if($maxPlacementPositions == 2)
                    $downline['attr']['position'] = $downline['attr']['position'] == 1 ? "Left" : "Right";
                else if($maxPlacementPositions == 3) {
                    if($downline['attr']['position'] == 1)
                        $downline['attr']['position'] = "Left";
                    else if($downline['attr']['position'] == 2)
                        $downline['attr']['position'] = "Middle";
                    else if($downline['attr']['position'] == 3)
                        $downline['attr']['position'] = "Right";
                }
                $downline['attr']['depth'] = $depth;
                $downline['attr']['createdAt'] = date("d/m/Y", strtotime($createdAt));
                $downline['attr']['downlineCount'] = count($this->getPlacementTreeDownlines($downlineID, false));
                $downline['attr']['disabled'] = $disabled==0 ? "No" : "Yes";
                $downline['attr']['suspended'] = $suspended==0 ? "No" : "Yes";
                $downline['attr']['freezed'] = $freezed==0 ? "No" : "Yes";

                $data['downline'][] = $downline;
                unset($downline);

                //get placement total in client setting                
            }

            $data['targetID'] = ($clientID == $targetID) ? "" : $targetID;
            
            // $data['generatePlacementBonusType'] = $setting->internalSetting['generatePlacementBonusType'];
            // $data['placementLRDecimalType'] = $setting->internalSetting['placementLRDecimalType'];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

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

        function getSponsorTreeTextView($params) {
            $db = $this->db;
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00376"][$language] /* Failed to load view */, 'data' => "");

            if($params['username']) {
                $db->where('username', $params['username']);
                $childID = $db->getValue('client', 'id');
                if(empty($childID))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00377"][$language] /* Invalid username. */, 'data' => "");

                $db->where('client_id', $params['clientID']);
                $clientTraceKey = $db->getValue('tree_sponsor', 'trace_key');
                if(empty($clientTraceKey))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00376"][$language] /* Failed to load view */, 'data' => "");

                $db->where('trace_key', $clientTraceKey."%", 'LIKE');
                $db->where('client_id', $childID);
                $isDownline = $db->getValue('tree_sponsor', 'id');
                if(empty($isDownline)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00378"][$language] /* Invalid downline */, 'data' => "");
                } else {
                    $params['clientID'] = $childID;
                }
            }

            $db->where('id', $params['clientID']);
            $sponsor = $db->get('client', 1, 'id AS client_id, username, created_at');

            if($db->count <= 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00379"][$language] /* Invalid client */, 'data' => "");

            $depthLevel = 30;
            $upline = $sponsor;
            $sponsorDownlines = [];
            for($i = 0; $i < $depthLevel; $i++) {
                $nextGenUpline = [];
                foreach($upline as $value) {
                    $colUsername = '(SELECT c.username FROM client c WHERE c.id=t.client_id) AS username';
                    $colCreatedAt = '(SELECT c.created_at FROM client c WHERE c.id=t.client_id) AS created_at';
                    $db->where('upline_id', $value['client_id']);
                    $downlines = $db->get('tree_sponsor t', null, 't.client_id, '.$colUsername.', upline_id,'.$colCreatedAt);

                    if($db->count <= 0)
                        continue;

                    $nextGenUpline = array_merge($nextGenUpline, $downlines);
                    // $sponsorDownlines = array_merge($sponsorDownlines, $downlines);
                }
                $upline = $nextGenUpline;
                unset($nextGenUpline);
                $downlinesLevel[$i] = $upline;
            }

            $data['downlines'] = $downlinesLevel;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        function changeSponsor($params) {
            $db             = $this->db;
            $activity       = $this->activity;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            
            if(empty($params['clientID']) || empty($params['uplineUsername']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00380"][$language] /* Required fields cannot be empty. */, 'data' => "");

            $clientId       = $params['clientID'];
            $uplineUsername = $params['uplineUsername'];

            //checking client
            $db->where('id', $clientId);
            $clientDetails  = $db->getValue('client', 'username');
            if(empty($clientDetails))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00381"][$language] /* Client no found. */, 'data' => "");

            $clientID       = $clientId;
            $username       = $clientDetails;

            $db->where('username', $uplineUsername);
            $uplineID = $db->getValue('client', 'id');

            if(empty($uplineID)) {
                $errorFieldArr[] = array(
                                            'id'  => 'uplineUsernameError',
                                            'msg' => $translations["E00382"][$language] /* Username does not exist. */
                                        );
            }

            $data['field'] = $errorFieldArr;

            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);

            //lock the table prevent others access this table while running function
            $db->setLockMethod("WRITE")->lock("tree_sponsor");

            $db->where('client_id', $uplineID);
            $upline = $db->getOne('tree_sponsor', 'level, trace_key', 1);

            if($db->count <= 0) {
                $db->unlock();
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00383"][$language] /* Invalid sponsor */, 'data' => "");
            }

            $uplineLevel = $upline['level'];
            $traceKey = $upline['trace_key'];

            $db->where('client_id', $clientID);
            $client = $db->getOne('tree_sponsor', 'level, trace_key');

            if($db->count <= 0) {
                $db->unlock();
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00383"][$language] /* Invalid sponsor */, 'data' => "");
            }

            $updateData = array (
                                    'upline_id' => $uplineID,
                                    'level' => $uplineLevel + 1,
                                    'trace_key' => $traceKey.'/'.$clientID 
                                );
            $db->where('client_id', $clientID);
            $db->update('tree_sponsor', $updateData);

            $db->where('trace_key', $client['trace_key'].'/%', 'like');
            $downlines = $db->get('tree_sponsor', null, 'id, level, trace_key');

            if($db->count <= 0) {
                $db->unlock();
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00383"][$language] /* Invalid sponsor */, 'data' => "");
            }

            $levelDiscrepancy = (($uplineLevel - $client['level']) + 1);

            foreach($downlines as $value) {
                $array = explode($clientID.'/', $value['trace_key']);

                $updateData = array (
                                        'level' =>  $levelDiscrepancy + $value['level'],
                                        'trace_key' => $traceKey.'/'.$clientID.'/'.$array[1]
                                    );
                $db->where('id', $value['id']);
                $db->update('tree_sponsor', $updateData);
            }

            // insert activity log
            $titleCode    = 'T00009';
            $activityCode = 'L00009';
            $transferType = 'Change Sponsor';
            $activityData = array('user' => $username);

            $activityRes = $activity->insertActivity($transferType, $titleCode, $activityCode, $activityData, $clientId);
            // Failed to insert activity
            if(!$activityRes) {
                $db->unlock();
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00384"][$language] /* Failed to insert activity. */, 'data' => "");
            }

            $db->unlock();
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        function changePlacement($params) {
            $db = $this->db;
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            if(empty($params['clientID']) || empty($params['uplineUsername']) || empty($params['position']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00385"][$language] /* Required fields cannot be empty. */, 'data' => "");

            //lock table prevent other access to the table
            $db->setLockMethod("WRITE")->lock("tree_placement");

            $clientID = $params['clientID'];
            $uplineUsername = $params['uplineUsername'];
            $position = $params['position'];

            $db->where('client_id', $clientID);
            $sponsorID = $db->getValue('tree_sponsor', 'upline_id');

            if($db->count <= 0) {
                $db->unlock();
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00386"][$language] /* Invalid placement. */, 'data' => "");
            }

            $db->where('username', $uplineUsername);
            $uplineID = $db->getValue('client', 'id');

            if(empty($uplineID)) {
                $errorFieldArr[] = array(
                                            'id'  => 'uplineUsernameError',
                                            'msg' => $translations["E00387"][$language] /* Username does not exist. */
                                        );
            }

            $data['field'] = $errorFieldArr;

            if($errorFieldArr) {
                $db->unlock();
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
            }

            $result = $this->getTreePlacementPositionValidity($sponsorID, $uplineID, $clientID);

            if($result['status'] == "error") {
                $db->unlock();
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00386"][$language] /* Invalid placement. */, 'data' => "");
            }

            $result = $this->getTreePlacementPositionAvailability($uplineID, $position);

            if($result['status'] == "error") {
                $db->unlock();
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00388"][$language] /* Position has been taken. */, 'data' => "");
            }

            $db->where('client_id', $uplineID);
            $upline = $db->getOne('tree_placement', 'client_position, level, trace_key', 1);

            if($db->count <= 0) {
                $db->unlock();
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00386"][$language] /* Invalid placement. */, 'data' => "");
            }

            $uplinePosition = $upline['client_position'];
            $uplineLevel = $upline['level'];
            $traceKey = $upline['trace_key'];

            $db->where('client_id', $clientID);
            $client = $db->getOne('tree_placement', 'level, trace_key');

            if($db->count <= 0) {
                $db->unlock();
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00386"][$language] /* Invalid placement. */, 'data' => "");
            }

            $maxPlacementPositions = $this->setting->systemSetting['maxPlacementPositions'];

            if($maxPlacementPositions == 2) {
                $positionSign[1] = '<';
                $positionSign[2] = '>';
            }
            else {
                $positionSign[1] = '<';
                $positionSign[2] = '|';
                $positionSign[3] = '>';
            }

            $updateData = array (
                                    'client_position' => $position,
                                    'upline_id' => $uplineID,
                                    'upline_position' => $uplinePosition
                                );
            $db->where('client_id', $clientID);
            $db->update('tree_placement', $updateData);

            $db->where('trace_key', $client['trace_key'].'%', 'like');
            $downlines = $db->get('tree_placement', null, 'id, level, trace_key');

            if($db->count <= 0) {
                $db->unlock();
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00386"][$language] /* Invalid placement. */, 'data' => "");
            }

            $levelDiscrepancy = (($uplineLevel - $client['level']) + 1);

            foreach($downlines as $value) {
                $array = explode($clientID, $value['trace_key']);

                $updateData = array (
                                        'level' =>  $levelDiscrepancy + $value['level'],
                                        'trace_key' => $traceKey.$positionSign[$position].$clientID.$array[1]
                                    );
                $db->where('id', $value['id']);
                $db->update('tree_placement', $updateData);
            }

            $db->unlock();
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        function getSponsor($params) {
            $db = $this->db;
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");
            $getClientName = "(SELECT name FROM client WHERE client.id=client_id) AS client_name";
            $getClientUsername = "(SELECT username FROM client WHERE client.id=client_id) AS client_username";
            $getUplineName = "(SELECT name FROM client WHERE client.id=upline_id) AS upline_name";
            $getUplineUsername = "(SELECT username FROM client WHERE client.id=upline_id) AS upline_username";
            $db->where('client_id', $params['clientID']);
            $result = $db->getOne('tree_sponsor', 'client_id, upline_id,'.$getClientName.','.$getClientUsername.','.$getUplineName.','.$getUplineUsername);

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00389"][$language] /* Invalid client. */, 'data' => "");

            foreach($result as $key => $value) {
                if(empty($value))
                    $value = "-";
                $data[$key] = $value;
            }

            $memberDetails = $this->getCustomerServiceMemberDetails($params['clientID']);
            $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        function getPlacement($params) {
            $db = $this->db;
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");
            $getClientName = "(SELECT name FROM client WHERE client.id=client_id) AS client_name";
            $getClientUsername = "(SELECT username FROM client WHERE client.id=client_id) AS client_username";
            $getUplineName = "(SELECT name FROM client WHERE client.id=upline_id) AS upline_name";
            $getUplineUsername = "(SELECT username FROM client WHERE client.id=upline_id) AS upline_username";
            $db->where('client_id', $params['clientID']);
            $result = $db->getOne('tree_placement', 'client_id, upline_id, client_position, '.$getClientName.','.$getClientUsername.','.$getUplineName.','.$getUplineUsername);

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00390"][$language] /* Invalid client. */, 'data' => "");

            $maxPlacementPositions = $this->setting->systemSetting['maxPlacementPositions'];

            foreach($result as $key => $value) {
                if($key == "client_position"){
                    if($maxPlacementPositions == 2)
                        $value = $value == 1 ? "Left" : "Right";
                    else if($maxPlacementPositions == 3) {
                        if($value == 1)
                            $value = "Left";
                        else if($value == 2)
                            $value = "Middle";
                        else if($value == 3)
                            $value = "Right";
                    }
                }
                if(empty($value))
                    $value = "-";
                $data[$key] = $value;
            }

            $memberDetails = $this->getCustomerServiceMemberDetails($params['clientID']);
            $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getMemberBankList($params) {
            $db = $this->db;
            $cash = $this->cash;
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $clientID = $params['clientID'];
            $creditType = $params['creditType'];

            if(empty($clientID) || empty($creditType))
                return array('status' => "error", 'code' => 0, 'statusMsg' => $translations["E00391"][$language] /* Failed to load bank list */, 'data' => "");

            $countryID = $db->subQuery();
            $countryID->where('id', $clientID);
            $countryID->get('client', null, 'country_id');

            $db->where('id', $countryID);
            $country = $db->getOne('country', 'id, name');

            $bankIDs = $db->subQuery();
            $bankIDs->where('country_id', $country['id']);
            $bankIDs->get('mlm_bank', null, 'id');

            $db->where('client_id', $clientID);
            $db->where('bank_id', $bankIDs, 'IN');
            $db->where('status', "Active");
            $getBankName = "(SELECT name FROM mlm_bank WHERE mlm_bank.id=bank_id) AS bank_name";
            $banks = $db->get('mlm_client_bank', null, 'bank_id, '.$getBankName.', account_no, account_holder, province, branch');

            $balance = $cash->getBalance($clientID, $creditType);

            $db->where('name', 'withdrawalAdminFee');
            $withdrawalAdminFee = $db->getValue('credit_setting', 'value');

            $data['balance'] = $balance;
            $data['clientBankList'] = $banks;
            $data['country'] = $country;
            $data['withdrawalAdminFee'] = $withdrawalAdminFee;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getWithdrawalListing($params) {
            $db = $this->db;
            $general = $this->general;
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $creditType = $params['creditType'];
            $clientID = $params['clientID'];
            $searchData = $params['searchData'];

            if(empty($creditType) || empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00392"][$language] /* Required fields cannot be empty. */, 'data' => "");

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = $general->getLimit($pageNumber);

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'status':
                            $db->where('status', $dataValue);
                            break;

                        case 'createdAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00393"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where('created_at', date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00393"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00394"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

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

            $db->where('credit_type', $creditType);
            $db->where('client_id', $clientID);
            $getCountryName = "(SELECT name FROM country WHERE country.id=(SELECT country_id FROM mlm_bank WHERE mlm_bank.id=bank_id)) AS country_name";
            $getAccountHolderName = "(SELECT account_holder FROM mlm_client_bank WHERE mlm_client_bank.client_id=mlm_withdrawal.client_id AND mlm_client_bank.bank_id=mlm_withdrawal.bank_id AND mlm_client_bank.account_no=mlm_withdrawal.account_no) AS account_holder";
            $getBankName = "(SELECT name FROM mlm_bank WHERE mlm_bank.id=bank_id) AS bank_name";
            $getProvince = "(SELECT province FROM mlm_client_bank WHERE mlm_client_bank.client_id=mlm_withdrawal.client_id AND mlm_client_bank.bank_id=mlm_withdrawal.bank_id AND mlm_client_bank.account_no=mlm_withdrawal.account_no) AS province";
            $copyDb = $db->copy();
            $result = $db->get('mlm_withdrawal', $limit, 'id, created_at, status, amount, charges, receivable_amount, '.$getCountryName.', currency_rate, account_no, '.$getAccountHolderName.', '.$getBankName.', '.$getProvince.', branch');

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00150"][$language] /* No results found */, 'data' => "");

            foreach($result as $array) {
                foreach($array as $key => $value) {
                    $withdrawal[$key] = $value ? $value : "";

                    if($key == "created_at")
                        $withdrawal[$key] = $general->formatDateTimeToString($value);
                }
                $withdrawalListing[] = $withdrawal;
            }

            $totalRecord = $copyDb->getValue("mlm_withdrawal", "COUNT(*)");
            $data['withdrawalListing'] = $withdrawalListing;
            $data['totalPage']   = ceil($totalRecord/$limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord']   = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getBankAccountList($params) {
            $db           = $this->db;
            $general      = $this->general;
            $activity     = $this->activity;
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $searchData   = $params['searchData'];
            $pageNumber   = $params['pageNumber'] ? $params['pageNumber'] : 1;
            
            //Get the limit.
            $limit        = $general->getLimit($pageNumber);

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName  = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'username':
                            $clientID = $db->subQuery();
                            $clientID->where('username', $dataValue);
                            $clientID->getOne("client", "id");
                            $db->where("client_id", $clientID); 
                            break;

                        case 'accHolderName':
                            $db->where('account_holder', $dataValue);
                            break;
                            
                        case 'typeBank':
                            $bankID = $db->subQuery();
                            $bankID->where('name', $dataValue);
                            $bankID->getOne('mlm_bank', "id");
                            $db->where('bank_id', $bankID);  
                            break;
                            
                        case 'status':
                            if ($dataValue == 0) {
                                $db->where('status', "Active");
                            } elseif ($dataValue == 1) {
                               $db->where('status', "Inactive");
                            }
                            break;
                            
                        case 'branch':
                            $db->where('branch', $dataValue);
                            break;
                            
                        case 'province':
                            $db->where('province', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($activity->creatorType == "Member") {
                $memberID = $params['memberId'];

                $db->where('id', $memberID);
                $memberDetail = $db->getOne('client', "name, username");
                $clientDetail['id'] = $memberID;
                $clientDetail['name'] = $memberDetail['name'];
                $clientDetail['username'] = $memberDetail['username'];
                $data['clientDetails'] = $clientDetail;
                $db->where('client_id', $memberID);
            }

            $db->where("status", array('Deleted'), "NOT IN");
            $copyDb = $db->copy();
            $db->orderBy("id", "DESC");

            $getUsername  = '(SELECT username FROM client WHERE mlm_client_bank.client_id = client.id) as username';
            $getBankName  = '(SELECT name FROM mlm_bank WHERE mlm_client_bank.bank_id = mlm_bank.id) as bank_name';

            $result = $db->get("mlm_client_bank ", $limit, $getUsername. "," .$getBankName. ", id, client_id, account_no, account_holder as accountHolder, province, branch, status");
                  
            $totalRecord = $copyDb->getValue ("mlm_client_bank", "count(*)");

            if(!empty($result)) {
                foreach($result as $value) {
                    $bankAcc['id']            = $value['id'];
                    $bankAcc['bankName']      = $value['bank_name'];
                    if($activity->creatorType == "Admin") {
                        $bankAcc['username']      = $value['username'];
                    }
                    $bankAcc['accountHolder'] = $value['accountHolder'];
                    $bankAcc['accountNo']     = $value['account_no'];
                    $bankAcc['province']      = $value['province'];
                    $bankAcc['branch']        = $value['branch'];
                    $bankAcc['status']        = $value['status'];

                    $bankAccList[] = $bankAcc;
                }

            $data['bankAccList'] = $bankAccList ? $bankAccList : "";
            $data['totalPage']   = ceil($totalRecord/$limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord']   = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00151"][$language] /* No results found */, 'data' => $data);
            }
        }

        public function updateBankAccStatus($params) {
            $db              = $this->db;
            $general         = $this->general;
            $language        = $this->general->getCurrentLanguage();
            $translations    = $this->general->getTranslations();

            if(empty($params['checkedIDs']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00395"][$language] /* No check box selected. */, 'data' => "");

            if(empty($params['status']) || ($params['status'] != "Inactive" && $params['status'] != "Deleted"))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00396"][$language] /* Invalid status. */, 'data' => "");

            $form = array(
                'status' => $params['status']
            );
            $db->where('id', $params['checkedIDs'], 'in');
            $db->update('mlm_client_bank', $form);
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }
        
        // To generate reference number for client portfolio
        function generateReferenceNo() {
            $db         = $this->db;
            $setting    = $this->setting;
            $tableName  = 'mlm_client_portfolio';
            
            // Get the length setting
            $referenceNoLength = $setting->systemSetting['referenceNumberLength']?:8;

            $min = "1"; $max = "9";
            for($i=1;$i<$referenceNoLength;$i++) $max .= "9";

            while (1) {
                $referenceNo = sprintf("%0".$referenceNoLength."s", mt_rand((int)$min, (int)$max));
                
                $db->where('reference_no', $referenceNo);
                $count = $db->getValue($tableName, 'count(*)');
                if ($count == 0) break;
                // If exists, continue to generate again
            }

            return $referenceNo;
        }
        
        // To insert into client portfolio
        function insertClientPortfolio($params) {
            $db         = $this->db;
            $activity   = $this->activity;
            $tableName  = "mlm_client_portfolio";
            
            $referenceNo = $this->generateReferenceNo();
            $insertData = array(

                "client_id"             => $params['clientID'],
                "product_id"            => $params['productID'],
                "product_price"         => $params['price'],
                "reference_no"          => $referenceNo,
                "bonus_value"           => $params['bonusValue'],
                "tier_value"            => $params['tierValue'],
                "portfolio_type"        => $params['type'],
                "belong_id"             => $params['belongID'],
                "reference_id"          => $params['referenceID'],
                "batch_id"              => $params['batchID'],
                "status"                => $params['status'],
                "expire_at"             => $params['expireAt'],
                "unit_price"            => $params['unitPrice'],
                "creator_id"            => $activity->creatorID?:'0',
                "creator_type"          => $activity->creatorType?:'System',
                "created_at"            => $db->now()

            );
            
            $portfolioID = $db->insert($tableName, $insertData);
            if (!$portfolioID)
                return false;
            
            return $portfolioID;
        }

        public function getPinList($params) {

            $db             = $this->db;
            $general        = $this->general;
            $setting        = $this->setting;
            $activity       = $this->activity;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $tableName      = "mlm_pin";
            $searchData     = $params['searchData'];
            $offsetSecs     = trim($params['offsetSecs']);
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = $general->getLimit($pageNumber);
            $decimalPlaces  = $setting->getSystemDecimalPlaces();

            $column     = array(

                "id",
                "code",
                "created_at",
                "unit_price",
                "bonus_value",
                "price",
                "buyer_id",
                "(SELECT username FROM client WHERE id = client_id) AS buyer_username",
                "(SELECT name FROM mlm_product WHERE id = product_id) AS package_name",
                "pin_type",
                "receiver_id",
                "(SELECT username FROM client WHERE id = receiver_id) AS placement_username",
                "owner_id",
                "(SELECT username FROM client WHERE id = owner_id) AS holder_username",
                "used_at",
                "status"

            );

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'code':
                            $db->where('code', $dataValue);

                            break;

                        case 'purchaseDate':
                            if($dataValue < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00397"][$language] /* Invalid date. */, 'data'=>""); // Invalid date

                            $dataValue = date('Y-m-d', $dataValue);
                            $db->where('created_at', $dataValue.'%', 'LIKE');

                            break;

                        case 'transactionDate':
                            $columnName = 'created_at';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00397"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00397"][$language] /* Invalid date. */, 'data'=>"");

                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00398"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'package':

                            $sq = $db->subQuery();
                            $sq->where("name", $dataValue);
                            $sq->getOne("mlm_product", "id");
                            $db->where("product_id", $sq);

                            break;

                        case 'placementDate':
                            $columnName = 'used_at';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00397"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00397"][$language] /* Invalid date. */, 'data'=>"");

                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00398"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'buyerName':

                            $sq = $db->subQuery();
                            $sq->where("name", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("buyer_id", $sq);

                            break;

                        case 'status':
                            $db->where('status', $dataValue);

                            break;

                        default:
                            $db->where($dataName, $dataValue);
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if ($activity->creatorType == "Member")
                $db->where("owner_id", $activity->creatorID);
            $db->orderBy("created_at", "DESC");
            $copyDb = $db->copy();
            $pinList = $db->get($tableName, $limit, $column);

            if (empty($pinList))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00152"][$language] /* No results found */, 'data' => "");

            foreach ($pinList as $pin) {

                if ($activity->creatorType == "Admin")
                    $pinListing['id']               = $pin['id'];
                $pinListing['pinNumber']            = $pin['code'];
                $pinListing['createdAt']            = $general->formatDateTimeToString($pin['created_at'])?:'-';

                if ($activity->creatorType == "Admin")
                    $pinListing['entryPrice']       = $pin['unit_price']?number_format($pin['unit_price'], $decimalPlaces, '.', ''):'-';
                $pinListing['purchasePrice']        = $pin['price']?number_format($pin['price'], $decimalPlaces, '.', ''):'-';

                if ($activity->creatorType == "Member") {
                    $pinListing['bonusValue']       = $pin['bonus_value'] ?: '-';
                    $pinListing['contract_length']  = '-'; //TODO not sure how is the contract get from need wait for reply
                }

                $pinListing['buyerId']              = $pin['buyer_id']?:'-';
                $pinListing['buyerUsername']        = $pin['buyer_username']?:'-';
                $pinListing['packageName']          = $pin['package_name']?:'-';

                if ($activity->creatorType == "Admin")
                    $pinListing['BvType']           = $pin['pin_type']?:'-';
                $pinListing['placeId']              = $pin['receiver_id']?:'-';

                $pinListing['placementUsername']    = $pin['placement_username']?:'-';
                $pinListing['holderId']             = $pin['owner_id']?:'-';
                $pinListing['holderUsername']       = $pin['holder_username']?:'-';
                $pinListing['placeDate']            = $general->formatDateTimeToString($pin['used_at'])?:'-';
                $pinListing['status']               = $pin['status']?:'-';

                $pinPageListing[] = $pinListing;
            }

            $totalRecord                    = $copyDb->getValue($tableName, "count(*)");
            $data['pinPageListing']         = $pinPageListing;
            $data['totalPage']              = ceil($totalRecord/$limit[1]);
            $data['pageNumber']             = $pageNumber;
            $data['totalRecord']            = $totalRecord;
            $data['numRecord']              = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00153"][$language] /* Pin list successfully retrieved */, 'data' => $data);
        }

        public function getPinDetail($params) {

            $db             = $this->db;
            $setting        = $this->setting;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $pinId          = trim($params['pinId']);
            $tableName      = "mlm_pin";
            $decimalPlaces  = $setting->getSystemDecimalPlaces();

            $column     = array(

                "code",
                "(SELECT name FROM mlm_product WHERE id = product_id) AS package_name",
                "bonus_value",
                "buyer_id",
                "(SELECT username FROM client WHERE id = client_id) AS buyer_username",
                "receiver_id",
                "(SELECT username FROM client WHERE id = receiver_id) AS receiver_username",
                "status"

            );

            $db->where("id", $pinId);
            $pinDetail = $db->getOne($tableName, $column);

            if (empty($pinDetail))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00154"][$language] /* No results found */, 'data' => "");

            $pinDetail['bonus_value'] = $pinDetail['bonus_value']?number_format($pinDetail['bonus_value'], $decimalPlaces, '.', ''):'-';


            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00155"][$language] /* Pin detail successfully retrieved */, 'data' => $pinDetail);
        }

        public function updatePinDetail($params) {

            $db             = $this->db;
            $cash           = $this->cash;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $status         = trim($params['status']);
            $tableName      = "mlm_pin";
            $pinIdList      = $params['pinId'];
            $column         = array(

                "buyer_id",
                "batch_id",
                "unit_price",
                "(SELECT id FROM client WHERE name = 'creditRefund' AND type = 'Internal') AS account_id"
            );

            if (empty($status))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00399"][$language] /* Status is invalid */, 'data' => "");

            if (!$pinIdList)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00400"][$language] /* Pin id is invalid */, 'data' => "");

            foreach($pinIdList as $pinId) {

                $db->where("id", $pinId);
                $currentStatus = $db->getValue($tableName, "status");

                if ($currentStatus == "New" || $currentStatus == "Transferred") {

                    //only perform this when user select refund
                    //refund to the buyer of the id
                    if ($status == "Refund"){

                        $db->where("id", $pinId);
                        $pinDetail = $db->getOne($tableName, $column);

                        $db->where("pin_id", $pinId);
                        $pinPayments = $db->get("mlm_pin_payment", NULL, "credit_type, amount");
                        $belongId = $db->getNewID();

                        if (empty($pinDetail) || empty($pinPayments))
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00401"][$language] /* Invalid pin */, 'data' => "");

                        foreach($pinPayments as $pinPayment) {
                            $cash->insertTAccount($pinDetail['account_id'], $pinDetail['buyer_id'], $pinPayment['credit_type'], $pinPayment['amount'], "Pin Refund", $belongId, "", $db->now(), $pinDetail['batch_id'], $pinDetail['buyer_id']);
                        }

                    }

                    $updateData = array(

                        'status' => $status
                    );

                    $db->where("id", $pinId);

                    if (!$db->update($tableName, $updateData))
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00402"][$language] /* Pin failed to update */, 'data' => "");

                }
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00156"][$language] /* Pin successfully updated */, 'data' => "");
        }

        public function getPinPurchaseFormDetail($params) {

            $db             = $this->db;
            $setting        = $this->setting;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $activity       = $this->activity;
            $tableName      = "enumerators";
            $decimalPlaces  = $setting->getSystemDecimalPlaces();
            $column         = array(

                "name",
                "translation_code"
            );

            $db->where("type", "pinType");
            $pinTypeResult = $db->get($tableName, NULL, $column);

            if (empty($pinTypeResult))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00403"][$language] /* No result found. */, 'data' => "");

            $tableName  = "mlm_product";
            $column     = array(

                "id",
                "(SELECT name FROM rank WHERE id = (SELECT value FROM mlm_product_setting WHERE name = 'rankID' AND product_id = " . $tableName . ".id)) AS product_name",
                "(SELECT value FROM mlm_product_setting WHERE name = 'pinType' AND product_id = ". $tableName . ".id) AS pin_type",
                "(SELECT value FROM mlm_product_setting WHERE name = 'bonusValue' AND product_id = ". $tableName . ".id) AS bonus_value",
                "(price * (SELECT unit_price FROM mlm_unit_price ORDER BY created_at DESC LIMIT 1)) AS unit_price",

            );

            $db->where('category', 'Pin');
            $pinResult = $db->get($tableName, NULL, $column);

            if (empty($pinResult))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00403"][$language] /* No result found. */, 'data' => "");

            foreach($pinResult as $pin){

                $pinProduct['id']           = $pin['id'];
                $pinProduct['product_name'] = $pin['product_name'];

                if ($activity->creatorType == "Admin")
                    $pinProduct['pin_type']     = $pin['pin_type'];

                $pinProduct['bonus_value']  = $pin['bonus_value'] ? number_format($pin['bonus_value'], $decimalPlaces, '.', '') : '-';
                $pinProduct['unit_price']   = $pin['unit_price'] ? number_format($pin['unit_price'], $decimalPlaces, '.', '') : '-';

                $pinProductList[] = $pinProduct;

            }

            $data['pinType']    = $pinTypeResult;
            $data['pinProduct'] = $pinProductList;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00157"][$language] /* Pin purchase form detail retrieved  */, 'data' => $data);
        }

        public function checkProductAndGetClientCreditType($params) {

            $db             = $this->db;
            $activity       = $this->activity;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $tableName      = "mlm_product";
            $cash           = $this->cash;
            $product        = $this->product;
            $productIdList  = $params['productIdList'];
            $clientId       = trim($params['clientId']);

            // Get valid credit type 
            $creditTypeList = $this->getValidCreditType();

            if ($activity->creatorType == "Member")
                $clientId = $activity->creatorID;

            $totalPrice = 0;
            foreach($productIdList as $productId){

                $db->where("id", $productId['productId']);
                $db->where("status", "Active");
                $result = $db->get($tableName, null, "name, price");

                if (empty($result))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00158"][$language] /* Product is invalid */, 'data' => "");

                foreach($result as $value){
                    $totalPrice += $value["price"] * $productId["quantity"];
                }
            }

            foreach($creditTypeList as $creditType){
                // Get min/max payment method
                $paymentMethod = $product->getMinMaxPaymentMethod($totalPrice, $creditType, "Purchase Pin");

                if($paymentMethod[$creditType]){
                    $balance = $cash->getClientCacheBalance($clientId, $creditType);
                    $wallet[] = array("name" => $creditType, "balance" => $balance, "payment" => $paymentMethod[$creditType]);
                }

            }
            $data['wallet'] = $wallet;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function purchasePin($params) {

            $db                 = $this->db;
            $cash               = $this->cash;
            $productObj         = $this->product;
            $invoice            = $this->invoice;
            $db                 = $this->db;
            $activity           = $this->activity;
            $setting            = $this->setting;
            $language           = $this->general->getCurrentLanguage();
            $translations       = $this->general->getTranslations();

            $products           = $params['products'];
            $buyerID            = trim($params['buyerId']);
            $wallets            = $params['wallets'];
            $tPassword          = trim($params['tPassword']);
            $batchId            = $db->getNewID();
            $totalPayment       = 0;
            $totalProductPrice  = 0;
            $productsCount      = 0;
            $invoiceProducts    = array();

            // Get password encryption type
            $passwordEncryption  = $setting->getMemberPasswordEncryption();

            if (empty($buyerID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00404"][$language] /* Buyer id is invalid */, 'data' => "");

            $db->where('id', $buyerID);
            $clientDetails = $db->getValue('client', 'username');
            if(empty($clientDetails))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00405"][$language] /* Buyer no found. */, 'data' => "");

            $buyerId       = $buyerID;
            $userName      = $clientDetails;

            if (empty($products))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00406"][$language] /* Product is invalid */, 'data' => "");

            if (empty($wallets))
                $wallets = array();

            if ($activity->creatorType == "Member") {
                if (empty($tPassword)) {
                    $errorFieldArr[] = array(
                        'id' => 'tPasswordError',
                        'msg' => $translations["E00414"][$language] /* Please enter transaction password. */
                    );
                } else {
                    $db->where('id', $buyerId);
                    if ($passwordEncryption == "bcrypt") {
                        // Bcrypt encryption
                        // Hash can only be checked from the raw values
                    } else if ($passwordEncryption == "mysql") {
                        // Mysql DB encryption
                        $db->where('transaction_password', $db->encrypt($tPassword));
                    } else {
                        // No encryption
                        $db->where('transaction_password', $tPassword);
                    }
                    $result = $db->get('client');

                    if (!empty($result)) {
                        if ($passwordEncryption == "bcrypt") {
                            // We need to verify hash password by using this function
                            if (!password_verify($tPassword, $result[0]['transaction_password']))
                                $errorFieldArr[] = array(
                                    'id' => 'tPasswordError',
                                    'msg' => $translations["E00407"][$language] /* Invalid transaction password. */
                                );
                        }
                    } else {
                        $errorFieldArr[] = array(
                            'id' => 'tPasswordError',
                            'msg' => $translations["E00407"][$language] /* Invalid transaction password. */
                        );
                    }
                }
            }

            if ($errorFieldArr) {

                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00407"][$language] /* Invalid transaction password. */, 'data'=> "");
            }

            //check amount passed to here is same with price of the products
            foreach($products as $product){

                $db->where("id", $product['productId']);
                $productPrice = $db->getValue("mlm_product", "price * (SELECT unit_price FROM mlm_unit_price ORDER BY created_at DESC LIMIT 1)");

                if (!empty($productPrice) && is_numeric($productPrice))
                    for($i = 0 ; $i < $product['quantity']; $i++)
                        $totalProductPrice += $productPrice;
            }

            foreach($wallets as $wallet) {
                if (empty($wallet) || !is_numeric($wallet['paymentAmount']) || $wallet['paymentAmount'] < 0)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00408"][$language] /* Wallet is invalid */, 'data' => "");

                $walletBalance = $cash->getBalance($buyerId, $wallet['creditType']);

                if ($wallet['paymentAmount'] > $walletBalance)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00409"][$language] /* Wallet balance is insufficient */, 'data' => "");

                $minMaxResult = $productObj->checkMinMaxPayment($totalProductPrice, $wallet["paymentAmount"], $wallet['creditType'], "Purchase Pin");
                if($minMaxResult["status"] != "ok"){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $minMaxResult["statusMsg"], 'data' => "");
                }

                $totalPayment += $wallet['paymentAmount'];
            }

            if ($totalProductPrice > $totalPayment || $totalProductPrice < $totalPayment)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00410"][$language] /* Payment not enough or doesn't match with the total payable */, 'data' => $totalProductPrice);

            //get the default account id form the client table
            $db->where("username", "creditSales");
            $receiverId = $db->getValue("client", "id");

            if (empty($receiverId))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00411"][$language] /* No result found. */, 'data' => "");


            //insert transaction into acc_credit table
            foreach($wallets as $wallet) {

                $belongId = $db->getNewID();
                $accountBalance = $cash->insertTAccount($buyerId, $receiverId, $wallet['creditType'], $wallet['paymentAmount'], "Pin Purchase", $belongId, "", $db->now(), $batchId, $buyerId);
            }

            foreach($products as $product){

                $productsCount += $product['quantity'];
            }

            //insert pin into mlm_pin table
            foreach ($products as &$product) {

                $tableName = "mlm_product";
                $productId = $product['productId'];
                $column = array(

                    "price",
                    "(SELECT value FROM mlm_product_setting WHERE name = 'bonusValue' AND product_id = " . $tableName . ".id) AS bonus_value",
                    "(SELECT value FROM mlm_product_setting WHERE name = 'pinType' AND product_id = " . $tableName . ".id) AS pin_type",

                );

                $db->where("id", $productId);
                $productDetail = $db->getOne($tableName, $column);

                if (empty($productDetail))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00411"][$language] /* No result found. */, 'data' => "");

                $unitPrice = $this->getLatestUnitPrice();

                if (empty($unitPrice))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00411"][$language] /* No result found. */, 'data' => "");

                //assign product price, bonus value and belong id for invoice usage
                $product['productPrice']    = $productDetail['price'] * $unitPrice;
                $product['bonusValue']      = $productDetail['bonus_value'];
                $product['unitPrice']       = $unitPrice;

                for ($i = 0; $i < $product['quantity']; $i++) {

                    //generate belongId for each pin purchased
                    $belongId = $db->getNewID();
                    $product['belongId']        = $belongId;

                    while (true) {

                        $pinNumber = $productObj->generatePinNumber();
                        $db->where("code", $pinNumber);
                        $count = $db->getValue("mlm_pin", "count(*)");

                        if ($count == 0)
                            break;
                    }

                    if (empty($pinNumber))
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00412"][$language] /* Pin number failed to generate */, 'data' => "");

                    $insertData = array(

                        'productId'     => $productId,
                        'pinNumber'     => $pinNumber,
                        'buyerId'       => $buyerId,
                        'clientId'      => $buyerId,
                        'price'         => $productDetail['price'],
                        'bonusValue'    => $productDetail['bonus_value'],
                        'pinType'       => $productDetail['pin_type'],
                        'belongId'      => $belongId,
                        'batchId'       => $batchId,
                        'ownerId'       => $buyerId,
                        'unitPrice'     => $unitPrice
                    );

                    $pinId = $productObj->purchaseNewPin($insertData);

                    foreach($wallets as $wallet){
                        //insert data into mlm_pin_payment
                        $db->insert("mlm_pin_payment", array(
                            'pin_id'        => $pinId,
                            'credit_type'   => $wallet['creditType'],
                            'amount'        => $wallet['paymentAmount'] / $productsCount
                        ));
                    }

                    $invoiceProducts[] = $product;

                }

                unset($product);

            }

            $invoiceId = $invoice->insertFullInvoice($buyerId, $totalProductPrice, $invoiceProducts, $wallets);

            // insert activity log
            $titleCode    = 'T00006';
            $activityCode = 'L00006';
            $transferType = 'Pin Purchase';
            $activityData = array('user'   => $userName);

            $activityRes = $activity->insertActivity($transferType, $titleCode, $activityCode, $activityData, $buyerId);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00413"][$language] /* Failed to insert activity. */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00159"][$language] /* Successfully purchase pin */, 'data' => $invoiceId);
        }

        public function reentryPin($params) {

            $db             = $this->db;
            $productObj     = $this->product;
            $cash           = $this->cash;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $bonus          = $this->bonus;
            $activity       = $this->activity;
            $tableName      = "mlm_pin";
            $receiverID     = trim($params['receiverId']);
            $pinNumber      = trim($params['pinNumber']);

            if (empty($receiverID) || empty($pinNumber))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00415"][$language] /* Data is invalid */, 'data' => $params);

            //checking client
            $db->where('id', $receiverID);
            $clientDetails = $db->getValue('client', 'username');
            if(empty($clientDetails))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00416"][$language] /* Client no found. */, 'data' => "");

            $receiverId    = $receiverID;
            $username      = $clientDetails;

            //check whether the pin is used
            $db->where("code", $pinNumber);
            $pinStatus = $db->getValue($tableName, "status");

            if ($pinStatus != "New")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00417"][$language] /* Pin number is invalid */, 'data' => "");


            //check whether this code exist and the corresponding product exist
            $column = array(

                "product_id",
                "price",
                "bonus_value",
                "(SELECT value FROM mlm_product_setting WHERE product_id = mlm_pin.product_id AND name = 'tierValue') AS tier_value",
                "belong_id",
                "batch_id",
                "(SELECT expire_at FROM mlm_product WHERE id = product_id) AS expire_at",
                "unit_price"

            );

            $db->where("code", $pinNumber);
            $productDetail = $db->getOne($tableName, $column);

            if (empty($productDetail))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00418"][$language] /* No product found */, 'data' => "");


            //update the pin status in mlm_pin table to used
            $updateData     = array(

                'status'        => "Used",
                'receiver_id'   => $receiverId,
                'used_at'       => $db->now()
            );

            $db->where("code", $pinNumber);
            if(!$db->update($tableName, $updateData))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00420"][$language] /* Failed to re-entry pin */, 'data' => "");


            //insert product details and client details to portfolio table

            $insertData = array(

                "clientID"              => $receiverId,
                "productID"             => $productDetail['product_id'],
                "price"                 => $productDetail['price'],
                "bonusValue"            => $productDetail['bonus_value'],
                "tierValue"             => $productDetail['tier_value'],
                "type"                  => "Pin Re-entry",
                "belongID"              => $productDetail['belong_id'],
                "referenceID"           => "",
                "batchID"               => $productDetail['batch_id'],
                "status"                => "Active",
                "expireAt"              => $productDetail['expire_at'],
                "unitPrice"             => $productDetail['unit_price'],

            );

            $portfolioId = $this->insertClientPortfolio($insertData);
            if (empty($portfolioId))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00420"][$language] /* Failed to re-entry pin */, 'data' => $insertData);

            $updateData = array(

                "portfolio_id" => $portfolioId
            );


            //update portfolio id to invoice item table
            $db->where("belong_id", $productDetail['belong_id']);
            if (!$db->update("mlm_invoice_item", $updateData))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00420"][$language] /* Failed to re-entry pin */, 'data' => "");

            // Insert client setting
            $clientSettingData['productID'] = $productDetail['product_id'];
            $clientSettingData['productBelongID'] = $productDetail['belong_id'];
            $clientSettingData['productBatchID'] = $productDetail['batch_id'];
            $clientSettingData['remark'] = '';
            $clientSettingData['subject'] = 'Pin Re-entry';
            $clientSettingData['clientID'] = $receiverId;
            
            $insertClientSettingResult = $this->insertClientSettingByProductSetting($clientSettingData);
            if($insertClientSettingResult['status'] == 'error')
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00420"][$language] /* Failed to re-entry pin */, 'data' => "");

            $bonusInData['clientID']    = $receiverId;
            $bonusInData['type']        = "Pin Re-entry";
            $bonusInData['productID']   = $productDetail['product_id'];
            $bonusInData['belongID']    = $productDetail['belong_id'];
            $bonusInData['batchID']     = $productDetail['batch_id'];
            $bonusInData['bonusValue']  = $productDetail['bonus_value'];
            $bonusInData['processed']   = 0;

            $insertBonusResult = $bonus->insertBonusValue($bonusInData);
             // Failed to insert bonus
            if(!$insertBonusResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00420"][$language] /* Failed to re-entry pin */, 'data'=> "");

            //update client's upline placement bonus
            $instantUpdateClientPlacementBonusResult = $bonus->instantUpdateClientPlacementBonus($receiverID, $productDetail['bonus_value']);

            if (!$instantUpdateClientPlacementBonusResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00336"][$language] /* Failed to insert bonus. */, 'data' => "");


            // insert activity log
            $titleCode    = 'T00011';
            $activityCode = 'L00011';
            $transferType = 'Reentry Pin';
            $activityData = array('user' => $username);

            $activityRes = $activity->insertActivity($transferType, $titleCode, $activityCode, $activityData, $receiverId);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00421"][$language] /* Failed to insert activity. */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00160"][$language] /* Congratulations! Your re-entry is successful */, 'data' => "");
        }

        public function getClientRepurchasePinDetail($params){

            $db                 = $this->db;
            $tree               = $this->tree;
            $language           = $this->general->getCurrentLanguage();
            $translations       = $this->general->getTranslations();
            $clientId           = $params['clientId'];
            $tableName          = "client";
            $column             = array(

                "name",
                "username",
                "(SELECT username FROM client sponsorUsername WHERE sponsorUsername.id = client.sponsor_id) AS sponsor_username",
                "sponsor_id",
                "(SELECT username FROM client placementUsername WHERE placementUsername.id = client.placement_id) AS placement_username",
                "placement_id",
                "(SELECT client_position FROM tree_placement WHERE client_id = ". $clientId.") AS client_position",
                "(SELECT value FROM system_settings WHERE name = 'maxPlacementPositions') AS max_placement_position"

            );

            $db->where("id", $clientId);
            $result = $db->getOne($tableName, $column);

            if (empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No result found", 'data' => "");

            if ($result['max_placement_position'] == 2){

                if ($result['client_position'] == 1)
                    $result['client_position'] = "Left";
                else if ($result['client_position'] == 2)
                    $result['client_position'] = "Right";
            }
            else if ($result['max_placement_position'] == 3){

                if ($result['client_position'] == 1)
                    $result['client_position'] = "Left";
                else if ($result['client_position'] == 2)
                    $result['client_position'] = "Center";
                else if ($result['client_position'] == 3)
                    $result['client_position'] = "Right";
            }

            foreach($result as $key => &$value){

                if (empty($value))
                    $value = "-";
            }

            unset($value);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00161"][$language] /* Successfully retrieved client detail */, 'data' => $result);
        }

        public function getClientRepurchasePackageDetail($params){

            $db             = $this->db;
            $tree           = $this->tree;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $setting        = $this->setting;
            $clientID       = $params['clientID'];
            $tableName      = "client";
            $column         = array(

                "name",
                "username",
                "(SELECT username FROM client sponsorUsername WHERE sponsorUsername.id = client.sponsor_id) AS sponsor_username",
                "sponsor_id",
                "(SELECT username FROM client placementUsername WHERE placementUsername.id = client.placement_id) AS placement_username",
                "placement_id",
                "(SELECT client_position FROM tree_placement WHERE client_id = ". $clientID.") AS client_position",
                "(SELECT value FROM system_settings WHERE name = 'maxPlacementPositions') AS max_placement_position"

            );

            $db->where("id", $clientID);
            $result = $db->getOne($tableName, $column);

            if (empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00162"][$language] /* No result found. */, 'data' => "");

            if ($result['max_placement_position'] == 2){

                if ($result['client_position'] == 1)
                    $result['client_position'] = "Left";
                else if ($result['client_position'] == 2)
                    $result['client_position'] = "Right";
            }
            else if ($result['max_placement_position'] == 3){

                if ($result['client_position'] == 1)
                    $result['client_position'] = "Left";
                else if ($result['client_position'] == 2)
                    $result['client_position'] = "Center";
                else if ($result['client_position'] == 3)
                    $result['client_position'] = "Right";
            }

            foreach($result as $key => &$value){

                if (empty($value))
                    $value = "-";
            }

            unset($value);

            $clientIdDetail[] = $result;

            // p is mlm_product table, s is mlm_product_setting table
            $db->join("mlm_product_setting s", "p.id = s.product_id", "LEFT");
            $db->where("s.name","bonusValue");
            $db->where("p.status", "Active");
            $db->where("p.category","Package");
            $copyDb = $db->copy();
            $result = $db->get("mlm_product p", null, "p.id, p.name, p.price, s.value");

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00422"][$language] /* No have any package. */, 'data' => "");

            $decimalPlaces = $setting->getSystemDecimalPlaces();

            foreach($result as $value) {
                $package['id']      = $value['id'];
                $package['name']    = $value['name'];
                $package['price']   = number_format($value['price'], $decimalPlaces, ".", "");
                $package['value']   = $value['value'];

                $pacDetail[] = $package;
            }

            $data['pacDetails']     = $pacDetail;
            $data['clientIdDetail'] = $clientIdDetail;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00163"][$language] /* Successfully retrieved ticket detail. */, 'data' => $data);
        }

        public function getRepurchasePackagePaymentDetail($params) {
            $db             = $this->db;
            $cash           = $this->cash;
            $setting        = $this->setting;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $product        = $this->product;

            $packageID      = $params['packageID'];
            $clientID       = $params['clientID'];

            // Get latest unit price
            $unitPrice = $this->getLatestUnitPrice();
            // Get valid credit type 
            $creditName = $this->getValidCreditType();
            // Get decimal places
            $decimalPlaces = $setting->getSystemDecimalPlaces();

            if (empty($clientID)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00423"][$language] /* Client not found. */, 'data'=> "");
            } else {
                $db->where("id", $clientID);
                $username = $db->getValue("client", "username");
            }
            if (empty($packageID)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00424"][$language] /* Package not found. */, 'data'=> "");
            } else {
                // p is mlm_product table, s is mlm_product_setting table
                $db->join("mlm_product_setting s", "p.id = s.product_id", "LEFT");
                $db->where("s.name","bonusValue");
                $db->where("p.status", "Active");
                $db->where("p.category","Package");
                $db->where("p.id", $packageID);
                $copyDb        = $db->copy();
                $resultPackage = $db->getOne("mlm_product p", "p.price, p.name, s.value");
                if (empty($resultPackage)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00424"][$language] /* Package not found. */, 'data'=> "");
                }
            }

            // Get client blocked rights
            $clientBlockedRights = array();
            $column = array(
                "(SELECT name FROM mlm_client_rights WHERE id = rights_id) AS blocked_right"
            );
            $db->where("client_id", $clientID);
            $blockedRights = $db->get("mlm_client_blocked_rights", NULL, $column);

            foreach ($blockedRights as $blockedRight){
                $clientBlockedRights[] = $blockedRight['blocked_right'];
            }

            foreach ($creditName as $value) {
                // Get min/max payment method
                $paymentMethod = $product->getMinMaxPaymentMethod(number_format($resultPackage['price'] * $unitPrice, $decimalPlaces, ".", ""), $value, "Package Repurchase");

                if($paymentMethod[$value]){
                    if (!in_array($value . " Purchase Package", $clientBlockedRights))
                        $balance[] = array("name" => $value, "value" => $cash->getClientCacheBalance($clientID, $value), "payment" => $paymentMethod[$value]);
                }
            }

            $data['username']               = $username;
            $data['balance']                = $balance;
            $data['blockedRights']          = $blockedRights;
            $data['resultPackage']['price'] = number_format($resultPackage['price'] * $unitPrice, $decimalPlaces, ".", "");
            $data['resultPackage']['name']  = $resultPackage['name'];
            $data['resultPackage']['value'] = $resultPackage['value'];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function verifyRepurchasePackageDetail($params){

            $db                  = $this->db;
            $cash                = $this->cash;
            $setting             = $this->setting;
            $activity            = $this->activity;
            $invoice             = $this->invoice;
            $bonus               = $this->bonus;
            $product             = $this->product;
            $language            = $this->general->getCurrentLanguage();
            $translations        = $this->general->getTranslations();
            $decimalPlaces       = $setting->getSystemDecimalPlaces();

            $clientID            = $params['clientID'];
            $packageID           = $params['packageID'];
            $tPassword           = trim($params['tPassword']);
            $creditData          = $params['creditData'];

            // Get password encryption type
            $passwordEncryption  = $setting->getMemberPasswordEncryption();
            // Get latest unit price
            $unitPrice = $this->getLatestUnitPrice();

            //checking client ID
            if (empty($clientID)) {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00425"][$language] /* Client not found. */, 'data' => '');
            } else {
                $db->where("id", $clientID);
                $clientDetails  = $db->getOne("client", "id, username");
                $id             = $clientDetails['id'];
                $username       = $clientDetails['username'];

                if (empty($id)) {
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00425"][$language] /* Client not found. */, 'data' => '');
                }
            }
            //checking package ID
            if (empty($packageID)) {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00426"][$language] /* Package not found. */, 'data' => '');
            } else {
                $db->where("id", $packageID);
                $db->where("category", 'Package');
                $checkingPackageId = $db->getOne("mlm_product", "price, status, expire_at");
                $price             = $checkingPackageId['price'] * $unitPrice;
                $status            = $checkingPackageId['status'];
                $expireAt          = $checkingPackageId['expire_at'];

                if (empty($checkingPackageId)) {
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00427"][$language] /* Package Invalid */, 'data' => '');
                } else {
                    if ($status != 'Active') {
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00427"][$language] /* Package Invalid */, 'data' => '');
                    }
                }
            }
            // checking credit type and amount
            if (empty($creditData)) {
                $errorFieldArr[] = array(
                    'id'    => 'totalError',
                    'msg'   => $translations["E00428"][$language] /* Please enter an amount. */
                );
            }
            $totalAmount = 0;
            foreach ($creditData as $value) {
                $balance = $cash->getClientCacheBalance($id, $value['creditType']);
                if (!is_numeric($value['paymentAmount']) || $value['paymentAmount'] < 0) {
                    $errorFieldArr[] = array(
                        'id'    => $value['creditType'].'Error',
                        'msg'   => $translations["E00429"][$language] /* Amount is required or invalid */
                    );
                } else {
                    if ($value['paymentAmount'] > $balance){
                        $errorFieldArr[] = array(
                            'id'    => $value['creditType'].'Error',
                            'msg'   => $translations["E00430"][$language] /* Insufficient credit. */
                        );
                    }

                    $minMaxResult = $product->checkMinMaxPayment($price, $value['paymentAmount'], $value['creditType'], "Package Repurchase");
                    if($minMaxResult["status"] != "ok"){
                        $errorFieldArr[] = array(
                            'id'    => $value['creditType'].'Error',
                            'msg'   => $minMaxResult["statusMsg"]
                        );
                    }

                    $totalAmount = $totalAmount + $value['paymentAmount'];
                    //matching amount with price

                }
            }

            if ($totalAmount == 0) {
                $errorFieldArr[] = array(
                    'id'    => 'totalError',
                    'msg'   => $translations["E00428"][$language] /* Please enter an amount. */
                );
            }

            $totalAmount = number_format($totalAmount, $decimalPlaces, ".", "");
            $price       = number_format($price, $decimalPlaces, ".", "");

            if ($totalAmount < $price) {
                $errorFieldArr[] = array(
                    'id'    => 'totalError',
                    'msg'   => $translations["E00430"][$language] /* Insufficient credit. */
                );
            }
            if ($totalAmount > $price) {
                $errorFieldArr[] = array(
                    'id'    => 'totalError',
                    'msg'   => $translations["E00431"][$language] /* Credit total does not match with total cost. */
                );
            }
            //checking transaction password
            if ($activity->creatorType == "Member") {
                if (empty($tPassword)) {
                    $errorFieldArr[] = array(
                        'id' => 'tPasswordError',
                        'msg' => $translations["E00432"][$language] /* Please enter transaction password. */
                    );
                } else {
                    $db->where('id', $clientID);
                    if ($passwordEncryption == "bcrypt") {
                        // Bcrypt encryption
                        // Hash can only be checked from the raw values
                    } else if ($passwordEncryption == "mysql") {
                        // Mysql DB encryption
                        $db->where('transaction_password', $db->encrypt($tPassword));
                    } else {
                        // No encryption
                        $db->where('transaction_password', $tPassword);
                    }
                    $result = $db->get('client');

                    if (!empty($result)) {
                        if ($passwordEncryption == "bcrypt") {
                            // We need to verify hash password by using this function
                            if (!password_verify($tPassword, $result[0]['transaction_password']))
                                $errorFieldArr[] = array(
                                    'id' => 'tPasswordError',
                                    'msg' => $translations["E00433"][$language] /* Invalid transaction password. */
                                );
                        }
                    } else {
                        $errorFieldArr[] = array(
                            'id' => 'tPasswordError',
                            'msg' => $translations["E00433"][$language] /* Invalid transaction password. */
                        );
                    }
                }
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;

                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00434"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> "");
        }

        public function reentryPackage($params) {
            $db                  = $this->db;
            $cash                = $this->cash;
            $setting             = $this->setting;
            $activity            = $this->activity;
            $invoice             = $this->invoice;
            $bonus               = $this->bonus;
            $product             = $this->product;
            $language            = $this->general->getCurrentLanguage();
            $translations        = $this->general->getTranslations();
            $decimalPlaces       = $setting->getSystemDecimalPlaces();

            $clientID            = $params['clientID'];
            $packageID           = $params['packageID'];
            $tPassword           = trim($params['tPassword']);
            $creditData          = $params['creditData'];

            // Get password encryption type
            $passwordEncryption  = $setting->getMemberPasswordEncryption();
            // Get latest unit price
            $unitPrice = $this->getLatestUnitPrice();

           //checking client ID
            if (empty($clientID)) {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00425"][$language] /* Client not found. */, 'data' => '');
            } else {
                $db->where("id", $clientID);
                $clientDetails  = $db->getOne("client", "id, username");
                $id             = $clientDetails['id'];
                $username       = $clientDetails['username'];

                if (empty($id)) {
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00425"][$language] /* Client not found. */, 'data' => '');
                }
            }
            //checking package ID
            if (empty($packageID)) {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00426"][$language] /* Package not found. */, 'data' => '');
            } else {
                $db->where("id", $packageID);
                $db->where("category", 'Package');
                $checkingPackageId = $db->getOne("mlm_product", "price, status, expire_at");
                $price             = $checkingPackageId['price'] * $unitPrice;
                $status            = $checkingPackageId['status'];
                $expireAt          = $checkingPackageId['expire_at'];

                if (empty($checkingPackageId)) {
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00427"][$language] /* Package Invalid */, 'data' => '');
                } else {
                    if ($status != 'Active') {
                       return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00427"][$language] /* Package Invalid */, 'data' => '');
                    }
                }
            }
            // checking credit type and amount
            if (empty($creditData)) {
                $errorFieldArr[] = array(
                                            'id'    => 'totalError',
                                            'msg'   => $translations["E00428"][$language] /* Please enter an amount. */
                                        );
            }
            $totalAmount = 0;
            foreach ($creditData as $value) {
                $balance = $cash->getClientCacheBalance($id, $value['creditType']);
                if (!is_numeric($value['paymentAmount']) || $value['paymentAmount'] < 0) {
                    $errorFieldArr[] = array(
                                                'id'    => $value['creditType'].'Error',
                                                'msg'   => $translations["E00429"][$language] /* Amount is required or invalid */
                                            );
                } else {
                    if ($value['paymentAmount'] > $balance){
                        $errorFieldArr[] = array(
                                                    'id'    => $value['creditType'].'Error',
                                                    'msg'   => $translations["E00430"][$language] /* Insufficient credit. */
                                                );
                    }

                    $minMaxResult = $product->checkMinMaxPayment($price, $value['paymentAmount'], $value['creditType'], "Package Repurchase");
                    if($minMaxResult["status"] != "ok"){
                        $errorFieldArr[] = array(
                                                'id'    => $value['creditType'].'Error',
                                                'msg'   => $minMaxResult["statusMsg"]
                                            );
                    }

                    $totalAmount = $totalAmount + $value['paymentAmount'];
                    //matching amount with price 
                   
                }
            }

             if ($totalAmount == 0) {
                $errorFieldArr[] = array(
                                            'id'    => 'totalError',
                                            'msg'   => $translations["E00428"][$language] /* Please enter an amount. */
                                        );
            }

            $totalAmount = number_format($totalAmount, $decimalPlaces, ".", "");
            $price       = number_format($price, $decimalPlaces, ".", "");

            if ($totalAmount < $price) {
                $errorFieldArr[] = array(
                                            'id'    => 'totalError',
                                            'msg'   => $translations["E00430"][$language] /* Insufficient credit. */
                                        );
            }
            if ($totalAmount > $price) {
                $errorFieldArr[] = array(
                                            'id'    => 'totalError',
                                            'msg'   => $translations["E00431"][$language] /* Credit total does not match with total cost. */
                                        );
            }       
            //checking transaction password
            if ($activity->creatorType == "Member") {
                if (empty($tPassword)) {
                    $errorFieldArr[] = array(
                        'id' => 'tPasswordError',
                        'msg' => $translations["E00432"][$language] /* Please enter transaction password. */
                    );
                } else {
                    $db->where('id', $clientID);
                    if ($passwordEncryption == "bcrypt") {
                        // Bcrypt encryption
                        // Hash can only be checked from the raw values
                    } else if ($passwordEncryption == "mysql") {
                        // Mysql DB encryption
                        $db->where('transaction_password', $db->encrypt($tPassword));
                    } else {
                        // No encryption
                        $db->where('transaction_password', $tPassword);
                    }
                    $result = $db->get('client');

                    if (!empty($result)) {
                        if ($passwordEncryption == "bcrypt") {
                            // We need to verify hash password by using this function
                            if (!password_verify($tPassword, $result[0]['transaction_password']))
                                $errorFieldArr[] = array(
                                    'id' => 'tPasswordError',
                                    'msg' => $translations["E00433"][$language] /* Invalid transaction password. */
                                );
                        }
                    } else {
                        $errorFieldArr[] = array(
                            'id' => 'tPasswordError',
                            'msg' => $translations["E00433"][$language] /* Invalid transaction password. */
                        );
                    }
                }
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;

                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00434"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }
            
            $db->where("product_id", $packageID);
            $db->where("name", array('bonusValue', 'tierValue'), 'IN');
            $resultValue = $db->get("mlm_product_setting", null, "name, value");
            foreach ($resultValue as $value) {
                if ($value['name'] == 'bonusValue') {
                    $bonusValue = $value['value'];
                }
                if ($value['name'] == 'tierValue') {
                    $tierValue = $value['value'];
                }
            }

            $batchID    = $db->getNewID();
            $belongID   = $db->getNewID();

            //insert product details and client details to portfolio table
            $insertData = array(
                                    "clientID"     => $id,
                                    "productID"    => $packageID,
                                    "price"        => $price,
                                    "bonusValue"   => $bonusValue,
                                    "tierValue"    => $tierValue,
                                    "type"         => "Package Re-entry",
                                    "belongID"     => $belongID,
                                    "referenceID"  => "",
                                    "batchID"      => $batchID,
                                    "status"       => "Active",
                                    "expireAt"     => $expireAt,
                                    "unitPrice"    => $unitPrice,
            );

            $portfolioId = $this->insertClientPortfolio($insertData);
            // Failed to insert portfolio
            if (empty($portfolioId))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00435"][$language] /* Failed to re-entry package. */, 'data' => "");

            // Insert invoice
                $invoiceData['productId']          = $packageID;
                $invoiceData['bonusValue']         = $bonusValue;
                $invoiceData['productPrice']       = $price;
                $invoiceData['unitPrice']          = $unitPrice;
                $invoiceData['belongId']           = $db->getNewID();
                $invoiceData['portfolioId']        = $portfolioId;

                $invoiceDataArr[] = $invoiceData;
                $invoiceResult    = $invoice->insertFullInvoice($id, $totalAmount, $invoiceDataArr, $creditData);
                // Failed to insert invoice
                if (!$invoiceResult)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00435"][$language] /* Failed to re-entry package. */, 'data' => "");
                
                // Get receiver ID
                $db->where('username', 'creditSales');
                $db->where('type', 'Internal');
                $receiverID = $db->getValue("client", "id");
                    
                // To deduct the balance of the sponsor
                foreach ($creditData as $key) {
                    $minusBalanceResult = $cash->insertTAccount($id, $receiverID, $key['creditType'], $key['paymentAmount'], "Package Purchase", $db->getNewID(), "", $db->now(), $batchID, $id);
                    
                    // Failed to insertTAccount
                    if (!$minusBalanceResult) {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00435"][$language] /* Failed to re-entry package. */, 'data' => "");
                    }
                }

            // Insert client setting
            $clientSettingData['productID']       = $packageID;
            $clientSettingData['productBelongID'] = $belongID;
            $clientSettingData['productBatchID']  = $batchID;
            $clientSettingData['remark']          = '';
            $clientSettingData['subject']         = 'Package Re-entry';
            $clientSettingData['clientID']        = $receiverID;
            
            $insertClientSettingResult = $this->insertClientSettingByProductSetting($clientSettingData);
            // Failed to insert client setting
            if ($insertClientSettingResult['status'] == 'error')
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00435"][$language] /* Failed to re-entry package. */, 'data' => "");

            // Insert bonus in
            $bonusInData['clientID']    = $id;
            $bonusInData['type']        = "Package Re-entry";
            $bonusInData['productID']   = $packageID;
            $bonusInData['belongID']    = $belongID; //need to same with portfolio for cancellation
            $bonusInData['batchID']     = $batchID;
            $bonusInData['bonusValue']  = $bonusValue;
            $bonusInData['processed']   = 0;
            
            $insertBonusResult = $bonus->insertBonusValue($bonusInData);
            // Failed to insert bonus
            if (!$insertBonusResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00435"][$language] /* Failed to re-entry package. */, 'data' => "");

            //update client's upline placement bonus
            $instantUpdateClientPlacementBonusResult = $bonus->instantUpdateClientPlacementBonus($clientID, $bonusValue);

            if (!$instantUpdateClientPlacementBonusResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00336"][$language] /* Failed to insert bonus. */, 'data' => "");
            
            $activityData = array('user' => $username);
            $activityRes = $activity->insertActivity('Package Re-entry', 'T00012', 'L00012', $activityData, $id);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00436"][$language] /* Failed to insert activity. */, 'data' => "");

            $data = $invoiceResult;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00164"][$language] /* Re-entry is successful. */, 'data' => $data);
        }

        public function getRepurchasePackageSuccessDetail($params) {
            $db            = $this->db;
            $cash          = $this->cash;
            $setting       = $this->setting;
            $language      = $this->general->getCurrentLanguage();
            $translations  = $this->general->getTranslations();

            // Get latest unit price
            $unitPrice = $this->getLatestUnitPrice();
            // Get valid credit type 
            $creditName = $this->getValidCreditType();
            // Get decimal places
            $decimalPlaces = $setting->getSystemDecimalPlaces();

            $packageID     = $params['packageID'];
            $clientID      = $params['clientID'];

            if(empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00437"][$language] /* Client not found. */, 'data'=> "");
            else {
                $db->where("id", $clientID);
                $username  = $db->getValue("client", "username");
            }
            
            foreach($creditName as $value) {
                $credit[]  = array("name" => $value, "value" => number_format($cash->getClientCacheBalance($clientID, $value), $decimalPlaces, ".", ""));
                $data['credit'] = $credit;
            }

            if (empty($packageID)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00438"][$language] /* Package not found. */, 'data'=> "");
            } else {
                // p is mlm_product table, s is mlm_product_setting table
                $db->join("mlm_product_setting s", "p.id = s.product_id", "LEFT");
                $db->where("s.name","bonusValue");
                $db->where("p.status", "Active");
                $db->where("p.category","Package");
                $db->where("p.id", $packageID);
                $copyDb        = $db->copy();
                $resultPackage = $db->getOne("mlm_product p", "p.price, p.name, s.value");
                if (empty($resultPackage)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00438"][$language] /* Package not found. */, 'data'=> "");
                }
                $data['result']['price'] = number_format($resultPackage['price'] * $unitPrice, $decimalPlaces, ".", "");
                $data['result']['name']  = $resultPackage['name'];
                $data['result']['value'] = $resultPackage['value'];
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }
        
        function insertClientSettingByProductSetting($params) {
            
            $db                 = $this->db;
            $cash               = $this->cash;
            $language           = $this->general->getCurrentLanguage();
            $translations       = $this->general->getTranslations();
            
            $productID          = $params['productID'];
            $productBelongID    = $params['productBelongID'];
            $productBatchID     = $params['productBatchID'];
            $remark             = $params['remark'];
            $subject            = $params['subject'];
            $clientID           = $params['clientID'];
            
            // get internal accounts
            $db->where("username", "creditSales");
            $accountID = $db->getValue("client", "id");
            
            // select bonus from mlm_bonus table
            $db->where("allow_rank_maintain", "1");
            $db->where("disabled", "0");
            $bonuses = $db->get("mlm_bonus",null, "name");

            foreach ($bonuses as $bonus)
                $bonusList[] = $bonus['name'];
            
            // Overall rankID
            $bonusList[] = 'rankID';
            
            // select credit from credit table
            $credits = $db->get("credit", null, "name");

            foreach ($credits as $credit)
                $creditList[] = $credit['name'];

            $mergedArray = array_merge($bonusList,$creditList);

            // get product bonuses
            $db->where("product_id", $productID);
            $db->where("name", $mergedArray, "IN");
            $bonusRankList = $db->get("mlm_product_setting", null, "name, value, type");
            
            //check client setting table, update if exists else insert, cant use mysql on duplicate update because table doesn't have any unique column
            foreach($bonusRankList as $newRank){

                $db->where("name", $newRank['name']);
                $db->where("client_id", $clientID);
                $previousRank = $db->get("client_setting", null, "value");

                if (in_array($newRank['name'], $bonusList)){

                    if (empty($previousRank)) {

                        $insertData = array(

                            "name"      => $newRank['name'],
                            "value"     => $newRank['value'],
                            "type"      => $newRank['type'],
                            "client_id" => $clientID
                        );
                        // Insert bonus rank
                        $insertRankResult = $db->insert("client_setting", $insertData);

                        if (empty($insertRankResult))
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00439"][$language] /* Failed to insert client rank. */, 'data' => "");
                        
                        $db->where('name', $newRank['name']);
                        $db->where('rank_id', $newRank['value']);
                        $rankSetting = $db->getOne('rank_setting', null, 'value, type');
                        if($rankSetting) {
                            $rankValue['type'] = $rankSetting['type'];
                            $rankValue['value'] = $rankSetting['value'];
                        }
                        
                        $insertData = array(

                            "name"      => $newRank['name'],
                            "value"     => $rankValue['value']?:'',
                            "type"      => $rankValue['type']?:'',
                            "client_id" => $clientID
                        );
                        // Insert bonus percentage
                        $insertRankValueResult = $db->insert("client_setting", $insertData);
                        
                        unset($rankValue);
                        
                        if (empty($insertRankValueResult))
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00439"][$language] /* Failed to insert client rank. */, 'data' => "");

                    } else {

                        //check previous value whether it is greater than the new one if so remain same value
                        if ($previousRank['value'] < $newRank['value']) {
                            
                            $updateData = array(
                                "value" => $newRank['value']
                            );
                            // Update bonus rank
                            $db->where('type', $newRank['type']);
                            $db->where("name", $newRank['name']);
                            $db->where("client_id", $clientID);
                            $updateRankResult = $db->update("client_setting", $updateData);
                            if (!$updateRankResult)
                                return array('status' => "error", 'code' => 1, 'statusMsg' =>$translations["E00439"][$language] /* Failed to insert client rank. */, 'data' => "");

                            $db->where('name', $newRank['name']);
                            $db->where('rank_id', $newRank['value']);
                            $rankSetting = $db->getOne('rank_setting', null, 'value, type');
                            if($rankSetting) {
                                $rankValue['type'] = $rankSetting['type'];
                                $rankValue['value'] = $rankSetting['value'];
                            }
                            
                            $updateData = array(
                                "value"     => $rankValue['value']?:''
                            );
                            // Update bonus percentage
                            $db->where('type', $rankValue['type']);
                            $db->where("name", $newRank['name']);
                            $db->where("client_id", $clientID);
                            $updateRankValueResult = $db->update("client_setting", $updateData);
                            
                            unset($rankValue);
                            if (empty($updateRankValueResult))
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00439"][$language] /* Failed to insert client rank. */, 'data' => "");

                        }
                    }
                }
                else if (in_array($newRank['name'], $creditList)){
                    $insertTAccountResult = $cash->insertTAccount($accountID, $clientID, $newRank['name'], $newRank['value'], $subject, $productBelongID, "", $db->now(), $productBatchID, $clientID, $remark);
                    if(!$insertTAccountResult)
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00440"][$language] /* Failed to insert data */, 'data' => "");
                }
            }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }
        
        // Get the latest unit price. Default is 1.00
        function getLatestUnitPrice() {
            $db         = $this->db;
            $tableName  = 'mlm_unit_price';
            
            $db->where('type', 'purchase');
            $db->orderBy('created_at', 'DESC');
            $unitPrice = $db->getValue($tableName, 'unit_price');
            if($unitPrice)
                return $unitPrice;
            
            return 1.00;
        }

        public function getCustomerServiceMemberDetails($clientID="", $params="") {

            $db             = $this->db;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();

            if(empty($clientID) && empty($params))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00441"][$language] /* Required fields cannot be empty. */, 'data' => "");

            $clientID = $clientID ? $clientID : $params['clientID'];
            $db->where('id', $clientID);
            $getClientUnitTier = "(SELECT value FROM client_setting WHERE name='tierValue' AND client_id=client.id) AS unit_tier";
            $getClientSponsorBonusPercentage = "(SELECT value FROM client_setting WHERE type='Bonus Percentage' AND name='sponsorBonus' AND client_id=client.id) AS sponsor_bonus_percentage";
            $getClientPairingBonusPercentage = "(SELECT value FROM client_setting WHERE type='Bonus Percentage' AND name='pairingBonus' AND client_id=client.id) AS pairing_bonus_percentage";
            $getClientRank = "(SELECT value FROM client_setting WHERE name = 'rankID' AND type = 'Overall Rank' AND client_id = client.id) AS rank";
            $result = $db->getOne('client', 'id, username, name, '.$getClientUnitTier.','.$getClientSponsorBonusPercentage.','.$getClientPairingBonusPercentage . ',' . $getClientRank);
            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00441"][$language] /* Required fields cannot be empty. */, 'data' => "");

            foreach($result as $key => $value) {
                $memberDetails[$key] = $value ? $value : "0";
            }

            $data['memberDetails'] = $memberDetails;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getPackageDetail($params) {

            $db             = $this->db;
            $setting        = $this->setting;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();

            // p is mlm_product table, s is mlm_product_setting table
            $db->join("mlm_product_setting s", "p.id = s.product_id", "LEFT");
            $db->where("s.name","bonusValue");
            $db->where("p.status", "Active");
            $db->where("p.category","Package");
            $result = $db->get("mlm_product p", null, "p.id, p.name, p.price, s.value");

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00442"][$language] /* No have any package. */, 'data' => "");

            $decimalPlaces = $setting->getSystemDecimalPlaces();

            foreach($result as $value) {
                $package['id']      = $value['id'];
                $package['name']    = $value['name'];
                $package['price']   = number_format($value['price'], $decimalPlaces, ".", "");
                $package['value']   = $value['value'];

                $pacDetail[] = $package;
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $pacDetail);
        }

        public function getPin($params) {

            $db             = $this->db;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $tableName      = "mlm_product";
            $column         = array(
                "name"
            );

            $db->where("category", "Pin");
            $data = $db->get($tableName, NULL, $column);

            if (empty($data))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00165"][$language] /* No result found. */, 'data' => "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function memberChangePassword($params) {
            $db                  = $this->db;
            $setting             = $this->setting;
            $language            = $this->general->getCurrentLanguage();
            $translations        = $this->general->getTranslations();

            $memberId            = $params['memberId'];
            $passwordCode        = $params['passwordCode'];
            $currentPassword     = $params['currentPassword'];
            $newPassword         = $params['newPassword'];
            $newPasswordConfirm  = $params['newPasswordConfirm'];

            // get password length
            $maxPass  = $setting->systemSetting['maxPasswordLength'];
            $minPass  = $setting->systemSetting['minPasswordLength'];
            $maxTPass = $setting->systemSetting['maxTransactionPasswordLength'];
            $minTPass = $setting->systemSetting['minTransactionPasswordLength'];
            // Get password encryption type
            $passwordEncryption  = $setting->getMemberPasswordEncryption();

            if (empty($memberId))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00443"][$language] /* Member not found. */, 'data'=> "");

            if (empty($passwordCode)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00444"][$language] /* Password type not found. */, 'data'=> "");

            } else {
                if ($passwordCode == 1) {
                    $passwordType = "password";

                } else if ($passwordCode == 2) {
                    $passwordType = "transaction_password";

                } else {
                   return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00444"][$language] /* Password type not found. */, 'data'=> "");
                }
            }
            // get error msg type
            if ($passwordType == "password") {
                $idName        = 'Password';
                $msgFieldB     = 'Password';
                $msgFieldS     = 'password';
                $maxLength     = $maxPass;
                $minLenght     = $minPass;

            } else if ($passwordType == "transaction_password") {
                $idName        = 'TPassword';
                $msgFieldB     = 'Transaction password';
                $msgFieldS     = 'transaction password';
                $maxLength     = $maxTPass;
                $minLenght     = $minTPass;

            }
            if (empty($newPasswordConfirm)) {
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."ConfirmError",
                                            'msg' => $translations["E00445"][$language] /* Please re-type */.  $msgFieldS
                                        );
            } else {
                if ($newPasswordConfirm != $newPassword) 
                    $errorFieldArr[] = array(
                                                'id'  => "new".$idName."ConfirmError",
                                                'msg' => $translations["E00446"][$language] /* Re-type new  */ . " " . $msgFieldS . " no match."
                                            );
            }

            // Retrieve the encrypted password based on settings
            $newEncryptedPassword = $this->getEncryptedPassword($newPassword);
            // Retrieve the encrypted currentPassword based on settings
            $encryptedCurrentPassword = $this->getEncryptedPassword($currentPassword);

            $db->where('id', $memberId);
            $result = $db->getOne('client', $passwordType);
            if (empty($result)) 
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00443"][$language] /* Member not found. */, 'data'=> "");

            if (empty($currentPassword)) {
                $errorFieldArr[] = array(
                                            'id'  => "current".$idName."Error",
                                            'msg' => $translations["E00448"][$language] /* Please enter old  */ . " " . $msgFieldS
                                        );
            } else {
                // Check password encryption
                if ($passwordEncryption == "bcrypt") {
                    // We need to verify hash password by using this function
                    if(!password_verify($currentPassword, $result[$passwordType])) {
                        $errorFieldArr[] = array(
                                                    'id'  => "current".$idName."Error",
                                                    'msg' => $translations["E00449"][$language] /* Invalid  */ . " " . $msgFieldS
                                                );
                    }
                } else {
                    if ($encryptedCurrentPassword != $result[$passwordType]) {
                        $errorFieldArr[] = array(
                                                    'id'  => "current".$idName."Error",
                                                    'msg' => $translations["E00449"][$language] /* Invalid  */ . " " . $msgFieldS
                                                );
                    }
                }
            }
            if (empty($newPassword)) {
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."Error",
                                            'msg' => $translations["E00450"][$language] /* Please enter new  */ . " " . $msgFieldS
                                        );
            } else {
                if (strlen($newPassword) < $minLenght || strlen($newPassword) > $maxLength) {
                    $errorFieldArr[] = array(
                                                'id'  => "new".$idName."Error",
                                                'msg' => $msgFieldB . $translations["E00451"][$language] /*  cannot be less than  */ . " " . $minLenght . " " . $translations["E00452"][$language] /*  or more than  */ . " " . $maxLength
                                            );
                } else {
                    //checking new password no match with current password
                    if ($passwordEncryption == "bcrypt") {
                        // We need to verify hash password by using this function
                        if(password_verify($newPassword, $result[$passwordType])) {
                            $errorFieldArr[] = array(
                                                        'id'  => "new".$idName."Error",
                                                        'msg' => $translations["E00453"][$language] /* Please enter different  */ . " " . $msgFieldS
                                                    );
                        }
                    } else {
                        if ($newEncryptedPassword == $result[$passwordType]) {
                            $errorFieldArr[] = array(
                                                        'id'  => "new".$idName."Error",
                                                        'msg' => $translations["E00453"][$language] /* Please enter different  */ . " " . $msgFieldS
                                                    );
                        }  
                    }
                }
            }
            $data['field'] = $errorFieldArr;
            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00454"][$language] /* Data does not meet requirements. */, 'data' => $data);

            $updateData = array($passwordType => $newEncryptedPassword);
            $db->where('id', $memberId);
            $updateResult = $db->update('client', $updateData);
            if($updateResult)
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");

            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00455"][$language] /* Update failed. */, 'data' => "");
        }

        public function transferPin($params) {

            $db             = $this->db;
            $tableName      = "mlm_pin";
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $pin            = trim($params['pinCode']);
            $username       = trim($params['username']);

            if (empty($pin) || empty($username))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00456"][$language] /* Failed to transfer pin */, 'data' => "");

            $db->where('username', $username);
            $clientId   = $db->getValue("client", "id");

            if (empty($clientId))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00457"][$language] /* User doesn't exist */, 'data' => "");

            $updateData = array(
                "owner_id" => $clientId
            );
            $db->where("code", $pin);
            if ($db->update($tableName, $updateData))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00166"][$language] /* Successfully transferred pin */, 'data' => "");
            else
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00456"][$language] /* Failed to transfer pin */, 'data' => "");
        }

        function getSponsorBonusList($params) {

            $db             = $this->db;
            $activity       = $this->activity;
            $general        = $this->general;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $tableName      = "mlm_bonus_sponsor";
            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = $general->getLimit($pageNumber);

            $column     = array(
                "bonus_date",
                "(SELECT username FROM client WHERE id = from_id) AS username",
                "(SELECT name FROM client WHERE id = from_id) AS name",
                "(SELECT name FROM rank WHERE id = rank_id) AS package_name",
                "(SELECT name FROM rank WHERE id = from_rank_id) AS from_package_name",
                "percentage",
                "payable_amount"
            );

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case 'transactionDate':
                            $columnName = 'bonus_date';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                // if($dateFrom < 0)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00458"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 1) {
                                // if($dateTo < 0)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00458"][$language] /* Invalid date. */, 'data'=>"");

                                // if($dateTo < $dateFrom)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00459"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        default:
                            $db->where($dataName, $dataValue);
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where("client_id", $activity->creatorID);
            $db->orderBy("bonus_date", "DESC");
            $copyDb = $db->copy();
            $result = $db->get($tableName, $limit, $column);

            foreach($result as $row){

                $sponsorBonus['bonusDate']      = $row['bonus_date'];
                $sponsorBonus['fromUsername']   = $row['username'];
                $sponsorBonus['fromName']       = $row['name'];
                $sponsorBonus['packageName']    = $row['package_name'];
                $sponsorBonus['from_package_name']    = $row['from_package_name'];
                $sponsorBonus['percentage']     = $row['percentage'] . "%";
                $sponsorBonus['totalBonus']     = $row['payable_amount'];

                $sponsorBonusList[] = $sponsorBonus;

            }

            $totalRecord                    = $copyDb->getValue($tableName, "count(*)");
            $data['sponsorBonusList']       = $sponsorBonusList;
            $data['totalPage']              = ceil($totalRecord/$limit[1]);
            $data['pageNumber']             = $pageNumber;
            $data['totalRecord']            = $totalRecord;
            $data['numRecord']              = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00167"][$language] /* Sponsor bonus list successfully retrieved */, 'data' => $data);
        }

        function getPairingBonusList($params) {

            $db             = $this->db;
            $activity       = $this->activity;
            $general        = $this->general;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $tableName      = "mlm_bonus_pairing";
            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = $general->getLimit($pageNumber);

            $column     = array(
                "bonus_date",
                "(SELECT username FROM client WHERE id = client_id) AS username",
                // "(SELECT name FROM client WHERE id = from_id) AS name",
                // "(SELECT name FROM rank WHERE id = rank_id) AS package_name",
                "percentage",
                "payable_amount",
                "unit_price",
                // "pairing_amount",
                // "amount AS bonus_amount"
                "cf_position_1",
                "cf_position_2",
                "position_1",
                "position_2",
                "rm_position_1",
                "rm_position_2"
            );

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case 'transactionDate':
                            $columnName = 'bonus_date';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                // if($dateFrom < 0)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00458"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 1) {
                                // if($dateTo < 0)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00458"][$language] /* Invalid date. */, 'data'=>"");

                                // if($dateTo < $dateFrom)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00459"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        default:
                            $db->where($dataName, $dataValue);
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where("client_id", $activity->creatorID);
            $db->orderBy("bonus_date", "DESC");
            $copyDb = $db->copy();
            $result = $db->get($tableName, $limit, $column);

            foreach($result as $row){

                $pairingBonus['bonusDate']      = $row['bonus_date'];
                $pairingBonus['username']   = $row['username'];
                // $pairingBonus['fromName']       = $row['name'];
                // $pairingBonus['packageName']    = $row['package_name'];
                $pairingBonus['unit_price']    = $row['unit_price'];
                $pairingBonus['cf_position_1']     = $row['cf_position_1'];
                $pairingBonus['cf_position_2']     = $row['cf_position_2'];
                $pairingBonus['position_1']     = $row['position_1'];
                $pairingBonus['position_2']     = $row['position_2'];
                $pairingBonus['rm_position_1']     = $row['rm_position_1'];
                $pairingBonus['rm_position_2']     = $row['rm_position_2'];
                $pairingBonus['percentage']     = $row['percentage'] . "%";
                $pairingBonus['totalBonus']     = $row['payable_amount'];
                $pairingBonusList[] = $pairingBonus;

            }

            $totalRecord                    = $copyDb->getValue($tableName, "count(*)");
            $data['pairingBonusList']       = $pairingBonusList;
            $data['totalPage']              = ceil($totalRecord/$limit[1]);
            $data['pageNumber']             = $pageNumber;
            $data['totalRecord']            = $totalRecord;
            $data['numRecord']              = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00169"][$language] /* Pairing bonus list successfully retrieved */, 'data' => $data);
        }

        function getRebateBonusList($params) {

            $db             = $this->db;
            $activity       = $this->activity;
            $general        = $this->general;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $tableName      = "mlm_bonus_rebate";
            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = $general->getLimit($pageNumber);

            $column     = array(
                "bonus_date",
                "(SELECT username FROM client WHERE id = client_id) AS username",
                // "(SELECT name FROM client WHERE id = from_id) AS name",
                // "(SELECT name FROM rank WHERE id = rank_id) AS package_name",
                "percentage",
                "payable_amount",
                "(SELECT name FROM rank WHERE id = product_id) AS product_id"
            );

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case 'transactionDate':
                            $columnName = 'bonus_date';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                // if($dateFrom < 0)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00458"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 1) {
                                // if($dateTo < 0)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00458"][$language] /* Invalid date. */, 'data'=>"");

                                // if($dateTo < $dateFrom)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00459"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        default:
                            $db->where($dataName, $dataValue);
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where("client_id", $activity->creatorID);
            $db->orderBy("bonus_date", "DESC");
            $copyDb = $db->copy();
            $result = $db->get($tableName, $limit, $column);

            foreach($result as $row){

                $rebateBonus['bonusDate']      = $row['bonus_date'];
                $rebateBonus['username']   = $row['username'];
                // $rebateBonus['fromName']       = $row['name'];
                $rebateBonus['product_id']    = $row['product_id'];
                // $rebateBonus['portfolio_id']    = $row['portfolio_id'];
                $rebateBonus['percentage']     = $row['percentage'] . "%";
                $rebateBonus['totalBonus']     = $row['payable_amount'];

                $rebateBonusList[] = $rebateBonus;

            }
            $totalRecord                    = $copyDb->getValue($tableName, "count(*)");
            $data['rebateBonusList']       = $rebateBonusList;
            $data['totalPage']              = ceil($totalRecord/$limit[1]);
            $data['pageNumber']             = $pageNumber;
            $data['totalRecord']            = $totalRecord;
            $data['numRecord']              = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00170"][$language] /* Rebate bonus list successfully retrieved */, 'data' => $data);
        }

        function getMatchingBonusList($params) {

            $db             = $this->db;
            $activity       = $this->activity;
            $general        = $this->general;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $tableName      = "mlm_bonus_matching";
            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = $general->getLimit($pageNumber);

            $column     = array(
                "bonus_date",
                "(SELECT username FROM client WHERE id = from_id) AS fromUsername",
                "(SELECT name FROM client WHERE id = from_id) AS name",
                "(SELECT name FROM rank WHERE id = rank_id) AS package_name",
                // "from_pairing_id",
                "from_pairing_amount",
                "(SELECT name FROM rank WHERE id = from_level) AS from_level",
                "percentage",
                "payable_amount"
            );

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case 'transactionDate':
                            $columnName = 'bonus_date';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                // if($dateFrom < 0)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00458"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 1) {
                                // if($dateTo < 0)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00458"][$language] /* Invalid date. */, 'data'=>"");

                                // if($dateTo < $dateFrom)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00459"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        default:
                            $db->where($dataName, $dataValue);
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where("client_id", $activity->creatorID);
            $db->orderBy("bonus_date", "DESC");
            $copyDb = $db->copy();
            $result = $db->get($tableName, $limit, $column);

            foreach($result as $row){

                $matchingBonus['bonusDate']      = $row['bonus_date'];
                $matchingBonus['fromUsername']   = $row['fromUsername'];
                $matchingBonus['fromName']       = $row['name'];
                $matchingBonus['packageName']    = $row['package_name'];
                // $matchingBonus['from_pairing_id']    = $row['from_pairing_id'];
                $matchingBonus['from_pairing_amount']    = $row['from_pairing_amount'];
                $matchingBonus['from_level']    = $row['from_level'];
                $matchingBonus['percentage']     = $row['percentage'] . "%";
                $matchingBonus['totalBonus']     = $row['payable_amount'];

                $matchingBonusList[] = $matchingBonus;

            }

            $totalRecord                    = $copyDb->getValue($tableName, "count(*)");
            $data['matchingBonusList']       = $matchingBonusList;
            $data['totalPage']              = ceil($totalRecord/$limit[1]);
            $data['pageNumber']             = $pageNumber;
            $data['totalRecord']            = $totalRecord;
            $data['numRecord']              = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00171"][$language] /* Matching bonus list successfully retrieved */, 'data' => $data);
        }

        function getWaterBucketBonusList($params) {

            $db             = $this->db;
            $activity       = $this->activity;
            $general        = $this->general;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $tableName      = "mlm_bonus_water_bucket";
            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = $general->getLimit($pageNumber);

            $column     = array(
                "bonus_date",
                "(SELECT username FROM client WHERE id = from_id) AS username",
                "(SELECT name FROM client WHERE id = from_id) AS name",
                "(SELECT name FROM rank WHERE id = rank_id) AS package_name",
                "(SELECT name FROM rank WHERE id = from_rank_id) AS from_package_name",
                "percentage",
                "payable_amount"
            );

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case 'transactionDate':
                            $columnName = 'bonus_date';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                // if($dateFrom < 0)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00458"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 1) {
                                // if($dateTo < 0)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00458"][$language] /* Invalid date. */, 'data'=>"");

                                // if($dateTo < $dateFrom)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00459"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        default:
                            $db->where($dataName, $dataValue);
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where("client_id", $activity->creatorID);
            $db->orderBy("bonus_date", "DESC");
            $copyDb = $db->copy();
            $result = $db->get($tableName, $limit, $column);

            foreach($result as $row){

                $sponsorBonus['bonusDate']      = $row['bonus_date'];
                $sponsorBonus['fromUsername']   = $row['username'];
                $sponsorBonus['fromName']       = $row['name'];
                $sponsorBonus['packageName']    = $row['package_name'];
                $sponsorBonus['from_package_name']    = $row['from_package_name'];
                $sponsorBonus['percentage']     = $row['percentage'] . "%";
                $sponsorBonus['totalBonus']     = $row['payable_amount'];

                $sponsorBonusList[] = $sponsorBonus;

            }

            $totalRecord                    = $copyDb->getValue($tableName, "count(*)");
            $data['sponsorBonusList']       = $sponsorBonusList;
            $data['totalPage']              = ceil($totalRecord/$limit[1]);
            $data['pageNumber']             = $pageNumber;
            $data['totalRecord']            = $totalRecord;
            $data['numRecord']              = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00172"][$language] /* Water Bucket bonus list successfully retrieved */, 'data' => $data);
        }

        function getPlacementBonusList($params) {

            $db             = $this->db;
            $activity       = $this->activity;
            $general        = $this->general;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();
            $tableName      = "mlm_bonus_placement";
            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = $general->getLimit($pageNumber);

            $column     = array(
                "(SELECT username FROM client where id = client_id) AS username",
                "(SELECT name FROM client where id = client_id) AS name",
                "(SELECT name FROM client where id = from_id) AS from_id",
                "level",
                "from_level",
                "payable_amount AS bonus_amount",
                "bonus_date"
            );

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case 'transactionDate':
                            $columnName = 'bonus_date';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                // if($dateFrom < 0)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00458"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 1) {
                                // if($dateTo < 0)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00458"][$language] /* Invalid date. */, 'data'=>"");

                                // if($dateTo < $dateFrom)
                                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00459"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        default:
                            $db->where($dataName, $dataValue);
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where("client_id", $activity->creatorID);
            $db->orderBy("bonus_date", "DESC");
            $copyDb = $db->copy();
            $result = $db->get($tableName, $limit, $column);
            
            foreach($result as $row){

                $placementBonus['bonusDate']      = $row['bonus_date'];
                // $placementBonus['fromUsername']   = $row['username'];
                // $placementBonus['fromName']       = $row['name'];
                $placementBonus['from_id']    = $row['from_id'];
                $placementBonus['level']    = $row['level'];
                $placementBonus['from_level']     = $row['from_level'];
                $placementBonus['bonus_amount']     = $row['bonus_amount'];

                $placementBonusList[] = $placementBonus;

            }

            $totalRecord                    = $copyDb->getValue($tableName, "count(*)");
            $data['placementBonusList']       = $placementBonusList;
            $data['totalPage']              = ceil($totalRecord/$limit[1]);
            $data['pageNumber']             = $pageNumber;
            $data['totalRecord']            = $totalRecord;
            $data['numRecord']              = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00167"][$language] /* Sponsor bonus list successfully retrieved */, 'data' => $data);
        }

        public function getBankAccountDetail($params) {
            $db           = $this->db;
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $memberId     = $params['memberId'];

            if (empty($memberId))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00460"][$language] /* Member not found. */, 'data'=> "");

            // get member name, username, country_id
            $db->where('id', $memberId);
            $memberDetail = $db->getOne('client', "name, username, country_id");
            if (empty($memberDetail))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00460"][$language] /* Member not found. */, 'data'=> "");

            $countryCode  = $memberDetail['country_id'];

            // get bank list
            $db->where('country_id', $countryCode);
            $bankDetail   = $db->get("mlm_bank ", $limit, "id, name");
            if (empty($bankDetail))
                $bankDetail = '';


            $clientDetail['id']       = $memberId;
            $clientDetail['name']     = $memberDetail['name'];
            $clientDetail['username'] = $memberDetail['username'];
            $data['clientDetails']    = $clientDetail;
            $data['bankDetails']      = $bankDetail;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function addBankAccountDetail($params) {
            $db            = $this->db;
            $language      = $this->general->getCurrentLanguage();
            $translations  = $this->general->getTranslations();

            $memberID      = $params['memberId'];
            $accountHolder = $params['accountHolder'];
            $bankID        = $params['bankID'];
            $accountNo     = $params['accountNo'];
            $province      = $params['province'];
            $branch        = $params['branch'];
            $tPassword     = $params['tPassword'];

            if (empty($memberID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00461"][$language] /* Member not found. */, 'data'=> "");

            if (empty($accountHolder))
                $errorFieldArr[] = array(
                                            'id'  => "accHolderNameError",
                                            'msg' => $translations["E00462"][$language] /* Please enter account holder name. */
                                        );
            if (empty($bankID))
                $errorFieldArr[] = array(
                                            'id'  => "bankTypeError",
                                            'msg' => $translations["E00463"][$language] /* Please enter a bank. */
                                        );
            if (empty($accountNo))
                $errorFieldArr[] = array(
                                            'id'  => "accountNoError",
                                            'msg' => $translations["E00464"][$language] /* Please enter account number. */
                                        );
            if (empty($province))
                $errorFieldArr[] = array(
                                            'id'  => "provinceError",
                                            'msg' => $translations["E00465"][$language] /* Please enter province. */
                                        );
            if (empty($branch))
                $errorFieldArr[] = array(
                                            'id'  => "branchError",
                                            'msg' => $translations["E00466"][$language] /* Please enter branch. */
                                        );
            if (empty($tPassword)){
                $errorFieldArr[] = array(
                                            'id'  => "tPasswordError",
                                            'msg' => $translations["E00467"][$language] /* Please enter transaction password. */
                                        );
            } else {
                $tPasswordResult = $this->verifyTransactionPassword($memberID, $tPassword);
                if($tPasswordResult['status'] != "ok") {
                    $errorFieldArr[] = array(
                                                'id'  => 'tPasswordError',
                                                'msg' => $translations["E00468"][$language] /* Invalid password. */
                                            );
                }
            }

            $data['field'] = $errorFieldArr;
            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00469"][$language] /* Data does not meet requirements. */, 'data' => $data);

            $insertClientBankData = array(
                                        "client_id"      => $memberID,
                                        "bank_id"        => $bankID,
                                        "account_no"     => $accountNo,
                                        "account_holder" => $accountHolder,
                                        "created_at"     => $db->now(),
                                        "status"         => 'Active',
                                        "province"       => $province,
                                        "branch"         => $branch

                                     );

            $insertClientBankResult  = $db->insert('mlm_client_bank', $insertClientBankData);
            // Failed to insert client bank account
            if (!$insertClientBankResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00470"][$language] /* Failed to add bank account. */, 'data' => "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00168"][$language] /* Update successful */, 'data' => "");
        }
    }
?>
