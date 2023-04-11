<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the Database functionality for System Settings..
     * Date  11/07/2017.
    **/

    class Setting {

        /**
         * Constructor for storing the system setting into class variables for usage in other class functions.
         * Usage: $variable = $setting->systemSetting[name] = value;
         **/
        function __construct($db) {
            $this->db = $db;
            
            $results = $db->get('system_settings');
            foreach ($results as $row) {
                $this->systemSetting[$row['name']] = $row['value'];
            }
            
        }

        // START OF RESELLER

        public function getResellerTimeOut() {
            return $this->systemSetting['resellerTimeout']? $this->systemSetting['resellerTimeout'] : 900;
        }

        public function getResellerPasswordEncryption(){
            return $this->systemSetting['resellerPasswordEncryption']? $this->systemSetting['resellerPasswordEncryption'] : "bcrypt";
        }

        public function getResellerPageLimit() {
            return $this->systemSetting['resellerPageLimit']? $this->systemSetting['adminPageLimit'] : 25;
        }        

        // END OF RESELLER

        
        public function getAuditHistoryLimit() {
            return $this->systemSetting['auditHistoryLimit']? $this->systemSetting['auditHistoryLimit'] : 100;
        }
        
        public function getSuperAdminPasswordEncryption() {
            return $this->systemSetting['superAdminPasswordEncryption']? $this->systemSetting['superAdminPasswordEncryption'] : "bcrypt";
        }
        
        public function getAdminPasswordEncryption() {
            return $this->systemSetting['adminPasswordEncryption']? $this->systemSetting['adminPasswordEncryption'] : "bcrypt";
        }        
        
        public function getMemberPasswordEncryption() {
            return $this->systemSetting['memberPasswordEncryption']? $this->systemSetting['memberPasswordEncryption'] : "";
        }
        
        public function getSuperAdminPageLimit() {
            return $this->systemSetting['superAdminPageLimit']? $this->systemSetting['superAdminPageLimit'] : 25;
        }
        
        public function getAdminPageLimit() {
            return $this->systemSetting['adminPageLimit']? $this->systemSetting['adminPageLimit'] : 25;
        }
        
        public function getMemberPageLimit() {
            return $this->systemSetting['memberPageLimit']? $this->systemSetting['memberPageLimit'] : 25;
        }
        
        public function getSuperAdminTimeOut() {
            return $this->systemSetting['superAdminTimeout']? $this->systemSetting['superAdminTimeout'] : 900;
        }
        
        public function getAdminTimeOut() {
            return $this->systemSetting['adminTimeout']? $this->systemSetting['adminTimeout'] : 900;
        }
        
        public function getMemberTimeout() {
            return $this->systemSetting['memberTimeout']? $this->systemSetting['memberTimeout'] : 900;
        }
        
        public function getSystemDecimalPlaces() {
            return $this->systemSetting['decimalPlaces']? $this->systemSetting['decimalPlaces'] : 2;
        }
        
        public function getSystemCreditSetting() {
            return $this->systemSetting['creditSetting']? $this->systemSetting['creditSetting'] : "prepaid";
        }
        
        public function getDateFormat() {
            return $this->systemSetting['systemDateFormat']? $this->systemSetting['systemDateFormat'] : "Y-m-d";
        }
        
        public function getDateTimeFormat() {
            return $this->systemSetting['systemDateTimeFormat']? $this->systemSetting['systemDateTimeFormat'] : "Y-m-d h:i:s";
        }
        
        public function getTimezoneSetting() {
            return $this->systemSetting['timezoneUsage']? $this->systemSetting['timezoneUsage'] : 0;
        }
        
        public function getErlangAccessToken() {
            return $this->systemSetting['erlangAccessToken']? $this->systemSetting['erlangAccessToken'] : 0;
        }
        
        public function getCryptoAccessToken() {
            return $this->systemSetting['cryptoAccessToken']? $this->systemSetting['cryptoAccessToken'] : 0;
        }
        
        /**
         * Function for getting the Settings List.
         * @param $settingParams.
         * @author Rakesh.
        **/
        public function getSettingsList($settingParams) {
            global $db, $general;
            $pageNumber = $settingParams['pageNumber'] ? $settingParams['pageNumber'] : 1;

            //Get the limit.
            $limit        = $general->getLimit($pageNumber);

            $searchData = $settingParams['searchData'];
            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName  = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'name':
                            $db->where('name', $dataValue);
                            break;
                            
                        case 'type':
                            $db->where('type', $dataValue);
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
            $result = $db->get("system_settings", $limit);

            if (!empty($result)) {
                $totalRecord = $copyDb->getValue ("system_settings", "count(id)");
                foreach($result as $value) {

                    $setting['id']         = $value['id'];
                    $setting['name']       = $value['name'];
                    $setting['type']       = $value['type'];
                    $setting['reference']  = $value['reference'];
                    $setting['module']     = $value['module'];
                    $setting['value']      = $value['value'];

                    $settingList[] = $setting;
                }
                
                $data['settingList'] = $settingList;
                $data['totalPage']   = ceil($totalRecord/$limit[1]);
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord']   = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for adding the New Settings.
         * @param $settingParams.
         * @author Rakesh.
        **/
        function newSetting($settingParams) {
            global $db;

            $name      = trim($settingParams['name']);
            $type      = trim($settingParams['type']);
            $reference = trim($settingParams['reference']);
            $value     = trim($settingParams['value']);
            $module    = trim($settingParams['module']);

            if(strlen($name) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Name.", 'data'=>"");

            if(strlen($type) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Type.", 'data'=>"");

            if(strlen($reference) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Reference.", 'data'=>"");

            if(strlen($value) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter the Value.", 'data'=>"");

            if(strlen($module) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter the Module.", 'data'=>"");

            $fields     = array("name", "type", "reference", "value", "module");
            $values     = array($name, $type, $reference, $value, $module);
            $arrayData  = array_combine($fields, $values);

            $result = $db->insert("system_settings", $arrayData);
            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Setting Successfully Saved"); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Setting", 'data'=>"");
            }
        }

        /**
         * Function for adding the Updating the Setting.
         * @param $settingParams.
         * @author Rakesh.
        **/
        public function editSettingData($settingParams) {
            global $db;

            $id          = trim($settingParams['id']);
            $name        = trim($settingParams['name']);
            $type        = trim($settingParams['type']);
            $value       = trim($settingParams['value']);
            $reference   = trim($settingParams['reference']);
            $module      = trim($settingParams['module']);

            if(strlen($name) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Name.", 'data'=>"");

            if(strlen($type) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Type.", 'data'=>"");

            if(strlen($reference) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Reference.", 'data'=>"");

            if(strlen($value) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Value.", 'data'=>"");

            if(strlen($module) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Module.", 'data'=>"");

            $fields = array("name", "type", "reference", "value", "module");
            $values = array($name, $type, $reference, $value, $module);
            $arrayData = array_combine($fields, $values);
            $db->where('id', $id);
            $result =$db->update("system_settings", $arrayData);

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Setting Successfully Updated"); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Setting", 'data'=>"");
            }
        }

        /**
         * Function for deleting the Setting.
         * @param $settingParams.
         * @author Rakesh.
        **/
        function deleteSettings($settingParams) {
            global $db;

            $id = trim($settingParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Setting", 'data'=> '');

            $db->where('id', $id);
            $result = $db->get('system_settings', 1);

            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete('system_settings');
                if($result) {
                    return $this->getSettingsList();
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete', 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Setting", 'data'=>"");
            }
        }

        /**
         * Function for getting the Setting data in the Edit.
         * @param $settingParams.
         * @author Rakesh.
        **/
        public function getSettingData($settingParams) {
            global $db;
            $id = trim($settingParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Setting", 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->getOne("system_settings");
            
            if (!empty($result)) {
                $setting['id']            = $result["id"];
                $setting['name']          = $result["name"];
                $setting['type']          = $result["type"];;
                $setting['reference']     = $result["reference"];
                $setting['value']         = $result["value"];
                $setting['module']        = $result['module'];
                
                $data['settingData'] = $setting;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Setting", 'data'=>"");
            }
        }

        public function setDecimal($amount, $creditType=""){

            $decimal = $this->systemSetting[$creditType."DecimalPlaces"];
            if(!$decimal) $decimal = $this->systemSetting['decimalPlaces'];
            if(!$decimal) $decimal = 8; // default 8
            if(is_int($creditType) && $creditType < 9) $decimal = $creditType;
      
            $floor = pow(10, $decimal); // floor for extra decimal
            $convertedAmount = number_format( (floor(strval($amount*$floor))/$floor) , $decimal , '.', '');

            return $convertedAmount;
        }

        public function setDecimalWithNoOfDP($amount, $decimal = ""){

            if(!$decimal) $decimal = $this->systemSetting['decimalPlaces'];
            if(!$decimal) $decimal = 8; // default 8
      
            $floor = pow(10, $decimal); // floor for extra decimal
            $convertedAmount = number_format( (floor(strval($amount*$floor))/$floor) , $decimal , '.', '');

            return $convertedAmount;
        }
    }

?>
