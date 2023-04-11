<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the Languages code.
     * Date  11/07/2017.
    **/
    class Language {
        
        function __construct($db, $general, $setting) {
            $this->db = $db;
            $this->general = $general;
            $this->setting = $setting;
        }

        function getLanguageTranslations()
        {
            $db = $this->db;

            // Get all available languages
            $db->where('disabled', 0);
            $languageRes = $db->get('languages', NULL, "language");
            foreach ($languageRes as $languageRow) {
                $languages[$languageRow['language']] = $languageRow['language'];
            }

            // Get all language translations
            $db->orderBy("site", "ASC");
            $db->orderBy("code", "ASC");
            $db->orderBy("module", "ASC");
            $translationRes = $db->get('language_translation', NULL, "code, language, content");
            foreach ($translationRes as $translationRow) {

                $translationCode[$translationRow['code']] = $translationRow['code'];
                $translations[$translationRow['code']][$translationRow['language']] = $translationRow['content'];
            }

            foreach ($translationCode as $code) {

                foreach ($languages as $language) {

                    if ($translations[$code][$language]) {
                        // Set the translations
                        $translationsData[$language][$code] = $translations[$code][$language];

                    }
                    else {
                        // If translation does not exist, set default to english
                        $translationsData[$language][$code] = $translations[$code]["english"];
                    }
                }
            }


            $data['languageData'] = $translationsData;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }
        
        function generateLanguageFile()
        {
            $db = $this->db;
            $setting = $this->setting;
            
            $results = $db->get("languages", null, "language");
            
            foreach($results as $row)
            {
                $languageArray[$row["language"]] = $row["language"];
            }
            
            $db->orderBy("site", "ASC");
            $db->orderBy("code", "ASC");
            $db->orderBy("module", "ASC");
            $translationResults = $db->get("language_translation");
            
            // Generate php file
            $content .= '<?php	'."\n";
            
            foreach ($translationResults as $row)
            {
                $translationCode[$row["code"]] = $row["code"];
                $translationArray[$row["code"]][$row["language"]] = $row;
            }
            
            foreach ($translationCode as $code) {
                
                if ($tempCode != $code)
                {
                    $tempCode = $code;
                    $content .= "\n";
                }
                
                foreach ($languageArray as $lang) {
                    
                    if ($translationArray[$code][$lang])
                    {
                        
                        if ($comment != $translationArray[$code][$lang]["site"]." ".$translationArray[$code][$lang]["module"])
                        {
                            
                            // Add comments
                            $comment = $translationArray[$code][$lang]["site"]." ".$translationArray[$code][$lang]["module"];
                            $content .= "\t".'// '.$comment.' section'."\n";
                            
                        }
                        
                        // Set the language
                        $content .= "\t".'$translations[\''.$code.'\'][\''.$lang.'\'] = "'.str_replace('"', '\"', $translationArray[$code][$lang]["content"]).'";'."\n";
                        
                    }
                    else
                    {
                        // If translation does not exist, set default to english
                        $content .= "\t".'$translations[\''.$code.'\'][\''.$lang.'\'] = "'.str_replace('"', '\"', $translationArray[$code]["english"]["content"]).'";'."\n";
                    }
                }
            }
            
            $content .= "\n";
            $content .= '?';
            $content .= '>';
            
            $languagePath = realpath(dirname(__FILE__))."/../language/";
            
            file_put_contents($languagePath.'lang_all.php', $content);
            
            // Check whether frontend Member and Admin path is set
            // If it's set, we try to automate the file copy process based on the settings
            // ***** IMPORTANT TO CHANGE THE PATH FOR EVERY DIFFERENT PROJECT!!!!! *****
            if ($setting->systemSetting['memberLanguagePath'])
            {
                if ($setting->systemSetting['isLocalhost'])
                {
                    $cmd = "cp ".$languagePath."lang_all.php ".$setting->systemSetting['memberLanguagePath'];
                    exec($cmd, $output, $result);
                }
                elseif ($setting->systemSetting['frontendServerIP'])
                {
                    $cmd = "scp ".$languagePath."lang_all.php root@".$setting->systemSetting['frontendServerIP'].":".$setting->systemSetting['memberLanguagePath'];
                    exec($cmd, $output, $result);
                }
            }
            
            if ($setting->systemSetting['adminLanguagePath'])
            {
                if ($setting->systemSetting['isLocalhost'])
                {
                    $cmd = "cp ".$languagePath."lang_all.php ".$setting->systemSetting['adminLanguagePath'];
                    exec($cmd, $output, $result);
                }
                elseif ($setting->systemSetting['frontendServerIP'])
                {
                    $cmd = "scp ".$languagePath."lang_all.php root@".$setting->systemSetting['frontendServerIP'].":".$setting->systemSetting['adminLanguagePath'];
                    exec($cmd, $output, $result);
                }
            }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }
        
        
        /**
         * Function for getting the Languge List.
         * @param $params.
         * @author Aman.
        **/
        public function getLanguageList($params) {
            $db = $this->db;
            $general = $this->general;
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit        = $general->getLimit($pageNumber);

            $searchData = $params['searchData'];
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case "isoCode":
                            $db->where("iso_code", $dataValue);
                            break;

                        case "status":
                            $db->where("disabled", $dataValue);
                            break;

                        default:
                            $db->where($dataName, $dataValue);

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            $copyDb = $db->copy();
            $db->orderBy("id", "DESC");
            $result = $db->get("languages", $limit);
            
            if (!empty($result)) {
                $totalRecord = $copyDb->getValue ("languages", "count(id)");
                foreach($result as $value) {

                    $language['id'] = $value['id'];
                    $language['language'] = $value['language'];
                    $language['languageCode'] = $value['language_code'];
                    $language['isoCode'] = $value['iso_code'];
                    $language['status'] = ($value['disabled'] == 0) ? 'Active' : 'Disabled';
                    $language['createdDate'] = $value['created_at'];

                    $languageList[] = $language;
                }

                $data['languageList'] = $languageList;
                $data['totalPage']    = ceil($totalRecord/$limit[1]);
                $data['pageNumber']   = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord'] = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for adding the New Language.
         * @param $params
         * @author Aman
        **/
        function newLanguage($params) {
            $db = $this->db;

            $language = trim($params['language']);
            //$languageCode = trim($params['languageCode']);
            $isoCode = trim($params['isoCode']);
            $status = trim($params['status']);
            $createdAt = date("Y-m-d H:i:s");
            $updatedAt = date("Y-m-d H:i:s");
            

            if(strlen($language) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Language Name.", 'data'=>"");
            // if(strlen($languageCode) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Language Code.", 'data'=>"");
            if(strlen($isoCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Iso Code.", 'data'=>"");
             if(strlen($status) == 0)
                 return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter status.", 'data'=>"");

            // $fields = array("language", "language_code","iso_code","disabled","created_at","updated_at");
            // $values = array($language, $languageCode,$isoCode,$status,$createdAt,$updatedAt);

            $fields = array("language", "iso_code","disabled","created_at","updated_at");
            $values = array($language, $isoCode,$status,$createdAt,$updatedAt);

            $arrayData = array_combine($fields, $values);

            $db->insert("languages", $arrayData);

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Successfully Added", 'data'=>"");
        }

        /**
         * Function for adding the Updating a Language.
         * @param $params
         * @author Aman.
        **/
        public function editLanguageData($params) {
            $db = $this->db;

            $id             = trim($params['id']);
            $languageName   = trim($params['languageName']);
            //$languageCode   = trim($params['languageCode']);
            $isoCode        = trim($params['isoCode']);
            $status         = trim($params['status']);
            $updatedAt      = date("Y-m-d H:i:s");

            if(strlen($languageName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Language Name.", 'data'=>"");
            // if(strlen($languageCode) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Language Code.", 'data'=>"");
            if(strlen($isoCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter ISO Code.", 'data'=>"");
            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Status", 'data'=>"");

            $fields = array("language","iso_code","disabled","updated_at");
            $values = array($languageName, $isoCode,$status,$updatedAt);
            $arrayData = array_combine($fields, $values);
            $db->where('id', $id);
            $result =$db->update("languages", $arrayData);

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Language updated Successfully", 'data'=>''); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Language", 'data'=>"");
            }
        }

        /**
         * Function for deleting the Persmission.
         * @param $languageParams
         * @author Aman
        **/
        function deleteLanguage($languageParams) {
            $db = $this->db;

            $id = trim($languageParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Language", 'data'=> '');

            $db->where('id', $id);
            $result = $db->get('languages', 1);

            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete('languages');
                if($result) {
                    return $this->getLanguageList();
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete', 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Language", 'data'=>"");
            }
        }

        /**
         * Function for getting the Language data in the Edit.
         * @param $params
         * @author Aman.
        **/
        public function getLanguageData($params) {
            $db = $this->db;
            $id = trim($params['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select A Language", 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->getOne("languages");

            if (!empty($result)) {
                $language['id']             = $result["id"];
                $language['languageName']   = $result["language"];
                //$language['languageCode']   = $result["language_code"];
                $language['isoCode']        = $result['iso_code'];
                $language['status']         = $result['disabled'];
                
                $data['languageData'] = $language;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Language", 'data'=>"");
            }
        }

        /** ########### Languagecode Starts (class.language.class) ########### **/

        /**
         * Function for getting the Languge List.
         * @param $languageCodeParams.
         * @author Aman.
        **/
        public function getLanguageCodeList($languageCodeParams) {
            $db = $this->db;
            $general = $this->general;
            
            $pageNumber = $languageCodeParams['pageNumber'] ? $languageCodeParams['pageNumber'] : 1;

            //Get the limit.
            $limit        = $general->getLimit($pageNumber);

            $searchData = json_decode($languageCodeParams['searchData']);
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        default:
                            $db->where($dataName, $dataValue);

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            $copyDb = $db->copy();
            $db->orderBy("id", "DESC");
            $result = $db->get("language_translation", $limit);

            if (!empty($result)) {
                $totalRecord = $copyDb->getValue ("language_translation", "count(id)");
                foreach($result as $value) {
                    // $id[]               = $value['id'];
                    // $content_code[]     = $value['code'];
                    // $languages[]        = $value['language'];
                    // $module[]           = $value['module'];
                    // $site[]             = $value['site'];
                    // $category[]         = $value['type'];
                    // $content[]          = $value['content'];
                    // $created_at[]       = $value['created_at'];
                    // $updated_at[]       = $value['updated_at'];

                    $language['id']           = $value['id'];
                    $language['contentCode']  = $value['code'];
                    $language['language']     = $value['language'];
                    $language['module']       = $value['module'];
                    $language['site']         = $value['site'];
                    $language['category']     = $value['type'];
                    $language['content']      = $value['content'];
                    // $language['created_at']   = $value['created_at'];
                    // $language['updated_at']   = $value['updated_at'];

                    $languageList[] = $language;
                    
                }

                // $language['id']           = $id;
                // $language['contentCode'] = $content_code;
                // $language['language']     = $languages;
                // $language['module']       = $module;
                // $language['site']         = $site;
                // $language['category']     = $category;
                // $language['content']      = $content;

                $data['languageCodeList'] = $languageList;
                $data['totalPage']        = ceil($totalRecord/$limit[1]);
                $data['pageNumber']       = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord'] = $limit[1];
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for getting the Languge List.
         * @param $languageCodeParams.
         * @author Aman.
        **/
        public function getLanguageRows($languageCodeParams) {
            $db = $this->db;

            $cols = Array ("id","language");
            $db->orderBy("id", "DESC");
            $result = $db->get("languages",null,$cols);

            if (!empty($result)) {
                foreach($result as $value) {
                    //$id[]       = $value['id'];
                    $language[] = $value['language'];
                }

                //$languageData['ID']       = $id;
                $languageData['Language'] = $language;
                $data['languageData']     = $languageData;

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for adding the New Language.
         * @param $languageCodeParams
         * @author Aman
        **/
        public function newLanguageCode($languageCodeParams) {
            $db = $this->db;
            //$test = $languageCodeParams['languageData'];
            $contentCode   = trim($languageCodeParams['contentCode']);
            $site           = trim($languageCodeParams['site']);
            $category       = trim($languageCodeParams['category']);
            $module         = trim($languageCodeParams['module']);
            $languageData   = $languageCodeParams['languageData'];

            $dataArray = [];

            foreach($languageData as $language => $content) {
                array_push($dataArray, Array($contentCode,$module,$language,$site,$category,$content));
            }

            // $myObj->data->data           = $languageCodeParams;
            // $myJson                     = json_encode($myObj);
            // return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $myJson);

            if(strlen($contentCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Content Code.", 'data'=>"");

            if(strlen($site) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Site Name", 'data'=>"");

            if(strlen($category) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Category", 'data'=>"");

            $fields = array("code", "module","language","site","type","content");

            $db->insertMulti('language_translation',$dataArray,$fields);

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Successfully Added", 'data'=>json_decode($dataArray));
        }

        /**
         * Function for adding the Updating a Language.
         * @param $languageCodeParams
         * @author Aman.
        **/
        public function editLanguageCodeData($languageCodeParams) {
            $db = $this->db;

            $id                  = trim($languageCodeParams['id']);
            $contentCode        = trim($languageCodeParams['contentCode']);
            $module              = trim($languageCodeParams['module']);
            $language            = trim($languageCodeParams['language']);
            $site                = trim($languageCodeParams['site']);
            $category            = trim($languageCodeParams['category']);
            $content             = trim($languageCodeParams['content']);

            if(strlen($contentCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Language Name.", 'data'=>"");

            if(strlen($language) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Language Code.", 'data'=>"");

            $fields = array("code","module","language","site","type","content");
            $values = array($contentCode,$module,$language,$site,$category,$content);
            $arrayData = array_combine($fields, $values);
            $db->where('id', $id);
            $result =$db->update("language_translation", $arrayData);

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Permission Successfully Updated"); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Permission", 'data'=>"");
            }
        }

        /**
         * Function for deleting the Persmission.
         * @param $languageCodeParams
         * @author Aman
        **/
        public function deleteLanguageCode($languageCodeParams) {
            $db = $this->db;

            $id = trim($languageCodeParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Permission", 'data'=> '');

            $db->where('id', $id);
            $result = $db->get('language_translation', 1);

            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete('language_translation');
                if($result) {
                    return $this->getLanguageCodeList();
                } else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete', 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Language Code", 'data'=>"");
            }
        }

        /**
         * Function for getting the Language data in the Edit.
         * @param $languageCodeParams
         * @author Aman.
        **/
        public function getLanguageCodeData($languageCodeParams) {
            $db = $this->db;
            $id = trim($languageCodeParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select A Language Code", 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->getOne("language_translation");

            if (!empty($result)) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $result);
            } else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Language", 'data'=>"");
            }
        }

        /**
         * Upload the Language Codes Excel file.
         * @param NULL.
         * @author Rakesh.
        **/
        public function uploadFile($languageCodeParams) {
            $db = $this->db;

            $fields = array("data", "type", "created_at");
            $values = array($languageCodeParams['data'], $languageCodeParams['type'], date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);

            $uploadId = $db->insert("uploads", $arrayData);

            $file        = $languageCodeParams['fileName'];
            $fileArray   = explode('.', $file);
            $fileName    = $fileArray[0];
            $fileExt     = $fileArray[1];
            $newFileName = $fileName."_".time().".".$fileExt;

            if ($uploadId) {
                $importFields = array("file_name", "processed", "upload_id", "created_by", "created_at");
                $importValues = array($newFileName, "0", $uploadId, "1", date("Y-m-d H:i:s"));
                $importData = array_combine($importFields, $importValues);

                $languageImportId = $db->insert("language_import", $importData);
            }
            if((!$uploadId) || (!$languageImportId))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Transalation file.", 'data'=>"");
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Imported Successfully.', 'data' => '');
        }

        /**
         * Export the Language Codes.
         * @param NULL.
         * @author Rakesh.
        **/
        public function exportLanguageCodes() {
            $db = $this->db;
            
            $db->orderBy("code", "ASC");
            $result = $db->get("language_translation");
            $columnHeaders = $db->rawQuery("SELECT language from languages where disabled = 0 ");
             //languages list.
            $languages     =  array_column($columnHeaders, "language");

            if(empty($result)) {
                $langColumns = $db->rawQuery("SHOW COLUMNS FROM language_translation where Field NOT IN ('id', 'language', 'content', 'created_at', 'updated_at')  ");

                foreach ($langColumns as $langColumn) {
                    $headerCols[] = $langColumn["Field"];
                }
                $finalHeader = array_merge($headerCols, $languages);

                return array('status' => "ok", 'code' => 1, 'statusMsg' => '', 'data' => $finalHeader);
            }

            if (!empty($result)) {
                $prevCode = '';
                $currCode = '';

                $i = -1;
                foreach($result as $key => $value) {
                    $currCode = $value['code'];
                    if($prevCode != $currCode) {
                        $i++;
                        $exportArray[$i]['code'] = $value['code'];
                        $exportArray[$i]['site'] = $value['site'];
                        $exportArray[$i]['module'] = $value['module'];
                        $exportArray[$i]['type'] = $value['type'];
                    }

                    $language = $value['language'];
                    foreach ($languages as $lang) {
                        if($value['language'] == $lang) {
                            $language = $value['language'];
                        }
                    }
                    $exportArray[$i][$language] = $value['content'];

                    $prevCode = $value['code'];
                }
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $exportArray);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Import the Language Translations.
         * @param NULL.
         * @author Rakesh.
        **/
        public function importLanguageTranslations() {
            $db = $this->db;

            $db->join("language_import li", "u.id=li.upload_id", "LEFT");
            $db->where("li.processed", 0);
            $importFiles = $db->get ("uploads u", null, "u.data, li.file_name, li.id");

            $dbLanguages = $db->get ("language_translation ", null, "code, site, module, type, language, content, created_at");

            if(isset($importFiles) && !empty($importFiles)) {

                //Check the Temp Dir is exists or not.
                if (!file_exists(realpath(dirname(__DIR__))."/temp/")) {
                    mkdir(realpath(dirname(__DIR__))."/temp/", 0700, true);
                }

                //Create the Excel files in the temp Floder.
                foreach ($importFiles as $importFile) {
                    $decodedData = base64_decode($importFile["data"]);
                    file_put_contents(realpath(dirname(__DIR__))."/temp/".$importFile["file_name"], $decodedData);
                }
                //Get the All Excel file names.
                $files = glob(realpath(dirname(__DIR__))."/temp/*.xlsx");

                //Get all the Importing files Content.
                foreach ($files as $inputFileName) {
                    //  Read your Excel workbook
                    try {
                        $inputFileType  = PHPExcel_IOFactory::identify($inputFileName);
                        $objReader      = PHPExcel_IOFactory::createReader($inputFileType);
                        $objPHPExcel    = $objReader->load($inputFileName);
                    } catch(Exception $e) {
                        die('Error loading file "'.pathinfo($inputFileName,PATHINFO_BASENAME).'": '.$e->getMessage());
                    }

                    //  Get worksheet dimensions
                    $sheet          = $objPHPExcel->getSheet(0); 
                    $highestRow     = $sheet->getHighestRow(); 
                    $highestColumn  = $sheet->getHighestColumn();

                    //  Loop through each row of the Excel file.
                    for ($row = 1; $row <= $highestRow; $row++){ 
                        //  Read a row of data into an array
                        $rowData = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE);
                        //Removes all empty values.
                        $rowData = array_filter(array_map('array_filter', $rowData));
                        foreach ($rowData as $index => $datas) {
                            $importMainArray[] =  $datas;
                        }
                    }
                }

                $mainCount = 0;
                $innerCount = 0;
                foreach ($importMainArray as $importMainKey => $importMainVal) {
                    $subCount = 0;
                    $count = count($importMainVal)-4;
                    foreach($importMainVal as $importKey => $importVal) {
                        $counterNum = $importMainKey * $mainCount;
                        if ($importKey == 0) {
                            $tempCount = 0;
                            while ($tempCount < $count) {
                                if ($importVal != 'code'){
                                    $finalImportArray[$counterNum+$tempCount]['code'] = $importVal;
                                }
                                $tempCount++;
                            }
                        }
                        if ($importKey == 1) {
                            $tempCount = 0;
                            while ($tempCount < $count) {
                                if ($importVal != 'site') {
                                    $finalImportArray[$counterNum+$tempCount]['site'] = $importVal;
                                }
                                $tempCount++;
                            }
                        }
                        if ($importKey == 2) {
                            $tempCount = 0;
                            while ($tempCount < $count) {
                                if ($importVal != 'module') {
                                    $finalImportArray[$counterNum+$tempCount]['module'] = $importVal;
                                }
                                $tempCount++;
                            }
                        }
                        if ($importKey == 3) {
                            $tempCount = 0;
                            while ($tempCount < $count) {
                                if ($importVal != 'type') {
                                    $finalImportArray[$counterNum+$tempCount]['type'] = $importVal;
                                }
                                $tempCount++;
                            }
                        }

                        if ($importKey > 3) {
                            if($mainCount == 0) {
                                $languages[$innerCount] = $importVal;
                                $innerCount++;
                            }
                            else {
                                if($importVal != $languages[$subCount]) {
                                    $finalImportArray[$counterNum+$subCount]['language'] = $languages[$subCount];
                                    $finalImportArray[$counterNum+$subCount]['content'] = $importVal;
                                    $finalImportArray[$counterNum+$subCount]['created_at'] = date("Y-m-d H:i:s");
                                }
                                $subCount++;
                            }
                        }
                    }
                    $mainCount+=$count;
                }

                $finalImportArray = array_unique($finalImportArray, SORT_REGULAR);
                //Update Logic.
                $cnt = 0;
                $totalData = array();
                foreach ($finalImportArray as $finalVal) {
                    if(!empty($finalVal) && isset($finalVal)) {
                        $totalData[$cnt]['code']       = $finalVal['code'];
                        $totalData[$cnt]['site']       = $finalVal['site'];
                        $totalData[$cnt]['type']       = $finalVal['type'];
                        $totalData[$cnt]['language']   = $finalVal['language'];
                        $totalData[$cnt]['content']    = $finalVal['content'];
                        $totalData[$cnt]['updated_at'] = date("Y-m-d H:i:s");

                        if(!isset($finalVal['code'])) {
                            echo "Please enter language code.";
                            Language::processFile($files);
                            exit;
                        }
                        if(!isset($finalVal['site'])) {
                            echo "Please enter site";
                            Language::processFile($files);
                            exit;
                        }
                        if(!isset($finalVal['type'])) {
                            echo "Please enter type.";
                            Language::processFile($files);
                            exit;
                        }
                        if(!isset($finalVal['language'])) {
                            echo "Please enter language name.";
                            Language::processFile($files);
                            exit;
                        }
                        if(!isset($finalVal['content'])) {
                            echo "Please enter content.";
                            Language::processFile($files);
                            exit;
                        }

                        $db->where("code", $finalVal['code']);
                        $db->where("site", $finalVal['site']);
                        $db->where("type", $finalVal['type']);
                        $db->where("language", $finalVal['language']);
                        $data = $db->update("language_translation", $totalData[$cnt]);
                        $cnt++;
                    }
                }

                //Used for inserting the New Language Data.
                $newData = array();
                foreach($finalImportArray as $finalImportValues) {
                    $duplicate = false;
                    foreach($dbLanguages as $dbValues) {
                        if(@$finalImportValues['code'] == @$dbValues['code'] && @$finalImportValues['language'] == @$dbValues['language']) {
                            $duplicate = true;
                            unset($finalImportValues['code']);
                            unset($finalImportValues['site']);
                            unset($finalImportValues['type']);
                            unset($finalImportValues['language']);
                            unset($finalImportValues['content']);
                        }
                    }
                    if($duplicate === false) $newData[] = $finalImportValues;
                }

                if(!empty($newData) && isset($newData)) {
                    $ids = $db->insertMulti('language_translation', $newData);
                } 
                else {
                    Language::processFile($files);
                    $message = "Language Translations Successfully Updated, ".count($files)." Files Deleted Successfully.";
                    return $message;
                    exit;
                }
                
                if(!$ids) {
                    return "insert failed ".  $db->getLastError();
                } 
                else {
                    Language::processFile($files);
                    $message =  "Language Translations Successfully Imported, ".count($files)." Files Deleted Successfully.";
                    return $message;
                }
            }
            else {
                $message =  "No files found.";
                return $message;
            }
        }

        /**
         * Enable the Proceesed to 1 and delete the file.
         * @param NULL.
         * @author Rakesh.
        **/
        public function processFile($files) {
            $db = $this->db;
            foreach ($files as $iFile) {
                $fileName = basename($iFile);         // $file is set to "index.php"
                $fileName = basename($iFile, ".xlsx"); // $file is set to "index"

                $updateFileProcess['processed']     = '1';
                $updateFileProcess['updated_at']    = date("Y-m-d H:i:s");

                $db->where('file_name', $fileName.".xlsx");
                $result =$db->update("language_import", $updateFileProcess);

                if($result){
                    unlink(realpath(dirname(__DIR__))."/temp/".$fileName.".xlsx");
                }
            }
            return;
        }
        /** ########### Languagecode End. (class.language.class) ########### **/
    }

?>
