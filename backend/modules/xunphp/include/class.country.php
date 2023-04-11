<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the Database functionality for System Interal Accounts..
     * Date  1/08/2017.
    **/

    class Country {
        
        function __construct($db, $general) {
            $this->db = $db;
            $this->general = $general;
        }

        /**
         * Function for getting the Interal Accounts List.
         * @param $countryParams.
         * @author Rakesh.
        **/
        public function getCountriesList($countryParams) {
            $db = $this->db;
            $general = $this->general;
            
            $pageNumber = $countryParams['pageNumber'] ? $countryParams['pageNumber'] : 1;
            //Get the limit.
            $limit        = $general->getLimit($pageNumber);

            $searchData = $countryParams['searchData'];

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName  = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'name':
                            $db->where('name', $dataValue);
                            break;
                            
                        case 'iso_code2':
                            $db->where('iso_code2', $dataValue);
                            break;
                            
                        case 'iso_code3':
                            $db->where('iso_code3', $dataValue);
                            break;
                            
                        case 'country_code':
                            $db->where('country_code', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->orderBy("name", "ASC");
            $copyDb = $db->copy();
            
            if ($countryParams['pagination'] == "No") {
                $result = $db->get("country"); 
            }else{
                $result = $db->get("country", $limit);
            }
            
            if (!empty($result)) {
                foreach($result as $value) {

                    $countries['id']            = $value['id'];
                    $countries['name']          = $value['name'];
                    $countries['isoCode2']      = $value['iso_code2'];
                    $countries['isoCode3']      = $value['iso_code3'];
                    $countries['countryCode']   = $value['country_code'];
                    $countries['currencyCode']  = $value['currency_code'];

                    $countriesList[] = $countries;
                }
                
                $totalRecords = $copyDb->getValue ("country", "count(id)");
                $data['countriesList'] = $countriesList;
                $data['totalPage']    = ceil($totalRecords/$limit[1]);
                $data['pageNumber']   = $pageNumber;
                $data['totalRecord']   = $totalRecords;
                $data['numRecord']   = $limit[1];
                
                return array('status' => "ok", 'code' => 1, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 1, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for adding the New Countries.
         * @param $countryParams.
         * @author Rakesh.
        **/
        function newCountry($countryParams) {
            $db = $this->db;

            $name           = trim($countryParams['name']);
            $isoCode2       = trim($countryParams['isoCode2']);
            $isoCode3       = trim($countryParams['isoCode3']);
            $countryCode    = trim($countryParams['countryCode']);
            $currencyCode   = trim($countryParams['currencyCode']);

            if(strlen($name) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Country Name.", 'data'=>"");

            if(strlen($isoCode2) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter ISO Code2.", 'data'=>"");

            if(strlen($isoCode3) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter ISO Code3.", 'data'=>"");

            if(strlen($countryCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Country Code.", 'data'=>"");

            if(strlen($currencyCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Currency Code.", 'data'=>"");

            $fields = array("name",
                            "iso_code2",
                            "iso_code3",
                            "country_code",
                            "currency_code",
                            "created_at");
            $values = array($name, 
                            $isoCode2,
                            $isoCode3,
                            $countryCode,
                            $currencyCode,
                            date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);

            $result = $db->insert("country", $arrayData);
            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Country Successfully Saved"); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Country", 'data'=>"");
            }
        }

        /**
         * Function for adding the Updating the Country.
         * @param $countryParams.
         * @author Rakesh.
        **/
        public function editCountryData($countryParams) {
            $db = $this->db;

            $id             = trim($countryParams['id']);
            $name           = trim($countryParams['name']);
            $isoCode2       = trim($countryParams['isoCode2']);
            $isoCode3       = trim($countryParams['isoCode3']);
            $countryCode    = trim($countryParams['countryCode']);
            $currencyCode   = trim($countryParams['currencyCode']);

            if(strlen($name) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Country Name.", 'data'=>"");

            if(strlen($isoCode2) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter ISO Code2.", 'data'=>"");

            if(strlen($isoCode3) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter ISO Code3.", 'data'=>"");

            if(strlen($countryCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Country Code.", 'data'=>"");

            if(strlen($currencyCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Currency Code.", 'data'=>"");

            $fields     = array("name",
                            "iso_code2",
                            "iso_code3",
                            "country_code",
                            "currency_code",
                            "updated_at");

            $values     = array($name, 
                            $isoCode2,
                            $isoCode3,
                            $countryCode,
                            $currencyCode,
                            date("Y-m-d H:i:s"));
            $arrayData  = array_combine($fields, $values);
            $db->where('id', $id);
            $result = $db->update("country", $arrayData);

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Country Successfully Updated"); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Country", 'data'=>"");
            }
        }

        /**
         * Function for deleting the Country.
         * @param $countryParams.
         * @author Rakesh.
        **/
        function deleteCountry($countryParams) {
            $db = $this->db;

            $id = trim($countryParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Country", 'data'=> '');

            $db->where('id', $id);
            $result = $db->get("country", 1);

            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete("country");
                if($result) {
                    return $this->getCountriesList();
                } else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete', 'data' => '');
            } else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Country", 'data'=>"");
            }
        }

        /**
         * Function for getting the Country data in the Edit.
         * @param $countryParams.
         * @author Rakesh.
        **/
        public function getCountryData($countryParams) {
            $db = $this->db;
            $id = trim($countryParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Country", 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->getOne("country");

            if (!empty($result)) {
                $countries['id']            = $result['id'];
                $countries['name']          = $result['name'];
                $countries['isoCode2']     = $result['iso_code2'];
                $countries['isoCode3']     = $result['iso_code3'];
                $countries['countryCode']  = $result['country_code'];
                $countries['currencyCode'] = $result['currency_code'];
                
                $data['countryData'] = $countries;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Country", 'data'=>"");
            }
        }

        public function getState(){

            $db = $this->db;
            $general = $this->general;
            $tableName = "state";

            $result = $db->get($tableName);

            if (!empty($result)) {

                return $result;
            } else {
                return null;
            }
        }

        public function getCity(){

            $db = $this->db;
            $general = $this->general;
            $tableName = "city";

            $result = $db->get($tableName);

            if (!empty($result)) {

                return $result;
            } else {
                return null;
            }
        }

        public function getCounty(){

            $db = $this->db;
            $general = $this->general;
            $tableName = "county";

            $result = $db->get($tableName);

            if (!empty($result)) {

                return $result;
            } else {
                return null;
            }
        }

        public function getCountryDataByIsoCode2($countryParams){
            $db = $this->db;
            $iso_code2_arr = $countryParams['iso_code2_arr'];

            $db->where("iso_code2", $iso_code2_arr, "in");
            $result = $db->map("iso_code2")->ArrayBuilder()->get("country");

            return $result;
        }

        public function getCountryByCountryCode($countryParams){
            $db = $this->db;
            $country_code_arr = $countryParams['country_code_arr'];

            $db->where("country_code", $country_code_arr, "in");
            $result = $db->map("country_code")->ArrayBuilder()->get("country");

            return $result;
        
        }
    }

?>
