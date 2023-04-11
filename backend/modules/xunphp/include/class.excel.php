<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the Database functionality for System..
 * Date  02/01/2020.
**/

class Excel
{
    function __construct($db, $setting, $message, $log, $general, $cash, $xunAdmin){
		$classAry = get_defined_vars();
    	foreach($classAry AS $className=>$class){
			$className = strtolower($className);
			$this->$className = $class;
    	}
    }
    // function __construct($db, $setting, $message, $provider, $log, $general, $client, $cash, $admin, $dashboard, $document, $activity, $trade, $tree, $report, $bonus){
    //     $classAry = get_defined_vars();
    // 	foreach($classAry AS $className=>$class){
    // 		$this->$className = $class;
    // 	}
    // }

    public function updateSystemStatus($name, $value){
		$db = $this->db;

    	$db->where("name", $name);
    	$ret_value = $db->update('system_settings', array("value" => $value));

    	if($ret_value > 0){
    		return array('status' => "ok", 'code' => 0, 'statusMsg' => "Update Successfully", 'data' => "");
    	}

    	return array('status' => "error", 'code' => 0, 'statusMsg' => "Nothing to Update", 'data' => "");
    }

    public function getExcelReqList($params){
    	$db             = $this->db;
        $general        = $this->general;
        $language       = $this->general->getCurrentLanguage();
        $translations   = $this->general->getTranslations();

        $adminID    = $db->userID;
        if(!$adminID) return array('status' => "error", 'code' => 1, 'statusMsg' => 'Admin ID not found.', 'data' => '');

        $db->where("id", $adminID);
        $adminUsername = $db->getValue("admin", "username");

        $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $limit      = $general->getLimit($pageNumber);

        $searchData = $params['searchData'];

        if(count($searchData) > 0) {
            foreach($searchData as $k => $v) {
                $dataName = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);

                switch($dataName) {

                    case 'date':
                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            $db->where('created_at', date('Y-m-d H:i:s', $dateFrom), '>=');
                        }
                        if(strlen($dateTo) > 0) {
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

        $db->where("creator_id", $adminID);
        $copyDb = $db->copy();
        $db->orderBy("created_at", "DESC");

        $exportList = $db->get('admin_export', $limit, 'id, file_name, type, progress, created_at, start_time, end_time, status, error_msg');

        if(empty($exportList))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00629"][$language], 'data' => "");

        foreach($exportList as &$value) {
        	$value['start_time'] = strtotime($value['start_time']) > 0 ? $value['start_time']: "-";
        	$value['end_time'] = strtotime($value['end_time']) > 0 ? $value['end_time']: "-";
        	$value['error_msg'] = $value['error_msg']? $value['error_msg']: "-";
        	$value['admin_username'] = $adminUsername;
        }

        $totalRecords         = $copyDb->getValue('admin_export', 'count(id)');
        $data['exportList'] = $exportList;
        $data['totalPage']    = ceil($totalRecords/$limit[1]);
        $data['pageNumber']   = $pageNumber;
        $data['totalRecord']  = $totalRecords;
        $data['numRecord']    = $limit[1];

        return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
    }

    public function addExcelReq($params){
    	$db = $this->db;
        $cash = $this->cash;

		$log = $this->log;

		// $params = array("command"     => $_POST['API'],
		// "type"        => 'excel',
		// "titleKey"    => $_POST['titleKey'],
		// "params"      => $_POST['params'],
		// "headerAry"   => $_POST['headerAry'],
		// "totalAry"   => $_POST['totalAry'],
		// "keyAry"      => $_POST['keyAry'],
		// "fileName"    => $_POST['fileName']
		// );

    	if(!$params['command'])
    		return array('status' => "error", 'code' => 0, 'statusMsg' => "Invalid Command", 'data' => "");

    	if(!$params['params'] || !is_array($params['params']))
    		return array('status' => "error", 'code' => 0, 'statusMsg' => "Invalid Filter", 'data' => "");

    	if(!$params['type'])
    		return array('status' => "error", 'code' => 0, 'statusMsg' => "Invalid file Type", 'data' => "");

    	if(!$params['titleKey'])
    		return array('status' => "error", 'code' => 0, 'statusMsg' => "Invalid title", 'data' => "");

    	if(!$params['headerAry'] || !is_array($params['headerAry']))
    		return array('status' => "error", 'code' => 0, 'statusMsg' => "Invalid header", 'data' => "");

    	if(!$params['fileName'])
    		return array('status' => "error", 'code' => 0, 'statusMsg' => "Invalid fileName", 'data' => "");

    	// incase API != function name
    	// $replaceAPI = array("getFundInListing" => "getFundInListing2");

    	$command = $params['command'];
    	// if($replaceAPI[$params['command']]){
    	// 	$command = $replaceAPI[$params['command']];
    	// }

    	$fileName = $params['fileName']."_".date("Ymd_His").".xlsx";
    	$insert = array(
    		"command" => $command,
    		"params" => json_encode($params['params']), // filter search
    		"type" => $params['type'], // excel
    		"file_name" => $fileName,
    		"title_key" => $params['titleKey'], // data[transactionList]
    		"header_ary" => json_encode($params['headerAry']), // headerDisplay
    		"key_ary" => ($params['keyAry']? json_encode($params['keyAry']):""), // keyToRearrange
    		"total_ary" => ($params['totalAry']? json_encode($params['totalAry']):""), //keyToSumUp
    		"creator_id" => $cash->creatorID,
			"creator_type" => $cash->creatorType,
    		"status" => "Pending",
    		"created_at" => date("Y-m-d H:i:s"),
    	);

		// print_r($insert);
		$log->write($insert);
		$rowId = $db->insert("admin_export", $insert);
		if(!$rowId){
			$log->write("\n dbErrror " . $db->getLastError());
		}
    	return array('status' => "ok", 'code' => 0, 'statusMsg' => "Update Successfully", 'data' => "");
    }

    public function updateExcelReqStatus($update, $id){
    	$db = $this->db;

    	$db->where("id", $id);
    	$db->update("admin_export", $update);

    	if($db->count > 0){
    		return array('status' => "ok", 'code' => 0, 'statusMsg' => "Update Successfully", 'data' => "");
    	}
    	return array('status' => "error", 'code' => 0, 'statusMsg' => "Nothing to Update", 'data' => "");
    }

    public function excelReqFailed($msg, $id){
    	$db = $this->db;

    	$update = array(
			"status" => "Failed", 
			"end_time" => date("Y-m-d H:i:s"), 
			"error_msg" => $msg
		);

    	$db->where("id", $id);
    	$db->update("admin_export", $update);
    }

    public function exportExcel($exportAry, $headerAry, $keyAry, $totalAry, $fileName, $id, $titleAry){

    	$objPHPExcel 	 = new PHPExcel();

    	$char = "A"; $rows = 1;
    	if($titleAry){
    		$style = array(
		        'alignment' => array(
		            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
		        )
		    );

    		$objPHPExcel->getActiveSheet()->getStyle($rows)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    		foreach($titleAry AS $title){

    			$from = $char.$rows;
    			$count = 0;
    			while($count != $title['colspan']){
    				$char++; $count++;
    			}
    			$to = $char.$rows;

    			$objPHPExcel->getActiveSheet()->mergeCells($from.':'.$to);
    			$objPHPExcel->getActiveSheet()->getCell($from)->setValue($title['value']);

    			$char++;
    		} // foreach($titleAry

    		$rows ++;
    	} // if($titleAry){

		$char = "A";
		foreach($headerAry AS $header){
			if(!$header || $header == "" || empty($header)){
				// skip empty header for tickBox & button
				continue;
			}
			$objPHPExcel->getActiveSheet()->SetCellValue($char.$rows, $header);
			$char ++;
		}

		if($headerAry){
			$rows ++;
		}
    	
    	$count = 0;
    	$total = count($exportAry);
	    foreach($exportAry AS $row){
			$char = "A"; 

			if(empty($keyAry)){ 
				foreach($row as $key=>$column){

					if(strlen($column) > 12){
						$objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $column, PHPExcel_Cell_DataType::TYPE_STRING);
					}else{
						$objPHPExcel->getActiveSheet()->SetCellValue($char.$rows, $column);
					}

					if(in_array($key, $totalAry))
						$keyTotal[$char] += str_replace(",", "", $column);

					$char ++;
				} // auto arrange 0 - last
			}else{
				foreach($keyAry AS $key){

					if(strlen($row[$key]) > 12){
						$objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row[$key], PHPExcel_Cell_DataType::TYPE_STRING);
					}else{
						$objPHPExcel->getActiveSheet()->SetCellValue($char.$rows, $row[$key]);
					}

					if(in_array($key, $totalAry)) 
						$keyTotal[$char] += str_replace(",", "", $row[$key]);

					$char ++;
				} // arrange by key
			}
			$count++;
			$percentage = 10 + (($count/$total)*80);
			$this->updateExcelReqStatus(array("progress" => $percentage), $id);
			$rows ++;
		}

		if($keyTotal){
			$objPHPExcel->getActiveSheet()->getStyle($rows)->getFont()->setBold( true );
		}

		foreach($keyTotal AS $char=>$total){
			$total = number_format($total, 2, '.', ',');
			if(strlen($total) > 12){
				$objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $total, PHPExcel_Cell_DataType::TYPE_STRING);
			}else{
				$objPHPExcel->getActiveSheet()->SetCellValue($char.$rows, $total);
			}
		}

		$this->updateExcelReqStatus(array("progress" => 90), $id);
		// Save Excel 2007 file
		$objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);
		$currentDirectory = __DIR__;
		$objWriter->save($currentDirectory."/../xlsx/".$fileName);
    }

    public function checkFunctionClass($function){
    	// $apirequest = $this->apirequest;

    	$ignore = array('stdClass', 'Exception', 'ErrorException', 'Closure', 'Generator', 'DateTime', 'DateTimeImmutable', 'DateTimeZone', 'DateInterval', 'DatePeriod', 'LibXMLError', 'SQLite3', 'SQLite3Stmt', 'SQLite3Result', 'CURLFile', 'DOMException', 'DOMStringList', 'DOMNameList', 'DOMImplementationList', 'DOMImplementationSource', 'DOMImplementation', 'DOMNode', 'DOMNameSpaceNode', 'DOMDocumentFragment', 'DOMDocument', 'DOMNodeList', 'DOMNamedNodeMap', 'DOMCharacterData', 'DOMAttr', 'DOMElement', 'DOMText', 'DOMComment', 'DOMTypeinfo', 'DOMUserDataHandler', 'DOMDomError', 'DOMErrorHandler', 'DOMLocator', 'DOMConfiguration', 'DOMCdataSection', 'DOMDocumentType', 'DOMNotation', 'DOMEntity', 'DOMEntityReference', 'DOMProcessingInstruction', 'DOMStringExtend', 'DOMXPath', 'finfo', 'GMP', 'LogicException', 'BadFunctionCallException', 'BadMethodCallException', 'DomainException', 'InvalidArgumentException', 'LengthException', 'OutOfRangeException', 'RuntimeException', 'OutOfBoundsException', 'OverflowException', 'RangeException', 'UnderflowException', 'UnexpectedValueException', 'RecursiveIteratorIterator', 'IteratorIterator', 'FilterIterator', 'RecursiveFilterIterator', 'CallbackFilterIterator', 'RecursiveCallbackFilterIterator', 'ParentIterator', 'LimitIterator', 'CachingIterator', 'RecursiveCachingIterator', 'NoRewindIterator', 'AppendIterator', 'InfiniteIterator', 'RegexIterator', 'RecursiveRegexIterator', 'EmptyIterator', 'RecursiveTreeIterator', 'ArrayObject', 'ArrayIterator', 'RecursiveArrayIterator', 'SplFileInfo', 'DirectoryIterator', 'FilesystemIterator', 'RecursiveDirectoryIterator', 'GlobIterator', 'SplFileObject', 'SplTempFileObject', 'SplDoublyLinkedList', 'SplQueue', 'SplStack', 'SplHeap', 'SplMinHeap', 'SplMaxHeap', 'SplPriorityQueue', 'SplFixedArray', 'SplObjectStorage', 'MultipleIterator', 'Collator', 'NumberFormatter', 'Normalizer', 'Locale', 'MessageFormatter', 'IntlDateFormatter', 'ResourceBundle', 'Transliterator', 'IntlTimeZone', 'IntlCalendar', 'IntlGregorianCalendar', 'Spoofchecker', 'IntlException', 'IntlIterator', 'IntlBreakIterator', 'IntlRuleBasedBreakIterator', 'IntlCodePointBreakIterator', 'IntlPartsIterator', 'UConverter', 'SessionHandler', '__PHP_Incomplete_Class', 'php_user_filter', 'Directory', 'mysqli_sql_exception', 'mysqli_driver', 'mysqli', 'mysqli_warning', 'mysqli_result', 'mysqli_stmt', 'PDOException', 'PDO', 'PDOStatement', 'PDORow', 'PharException', 'Phar', 'PharData', 'PharFileInfo', 'ReflectionException', 'Reflection', 'ReflectionFunctionAbstract', 'ReflectionFunction', 'ReflectionParameter', 'ReflectionMethod', 'ReflectionClass', 'ReflectionObject', 'ReflectionProperty', 'ReflectionExtension', 'ReflectionZendExtension', 'SimpleXMLElement', 'SimpleXMLIterator', 'SoapClient', 'SoapVar', 'SoapServer', 'SoapFault', 'SoapParam', 'SoapHeader', 'XMLReader', 'XMLWriter', 'XSLTProcessor', 'ZipArchive', 'msgpack', 'MysqliDb', 'Setting', 'Language', 'Provider', 'PHPExcel', 'PHPExcel_Autoloader', 'PHPExcel_Shared_String', 'Log', 'validation');

		$allClasses = get_declared_classes();

    	foreach($allClasses AS $key=>$classes){
			if(in_array($classes, $ignore)) continue;

			if(method_exists($classes, $function)){
				$declareClass = strtolower($classes);
				return $this->$declareClass;
			}
		}

	} // checkFunctionClass
	
	public function simpleExportExcel($exportAry, $headerAry, $titleAry, $fileName){

		$objPHPExcel = new PHPExcel();
		$char = "A"; $rows = 1;

		echo "Inserting Header...\n";
		
		foreach ($headerAry as $header) {
			$objPHPExcel->getActiveSheet()->SetCellValue($char . $rows, $header);
			$char++;
		}
		
		echo "Inserting Data...\n";
		$rows = 2;
		foreach ($exportAry as $data) {
			$char = "A";
			foreach ($titleAry as $title) {
				$objPHPExcel->getActiveSheet()->SetCellValue($char . $rows, $data[$title]);
				$char++;
			}
			$rows++;
		}
		
		echo "Generating Excel...\n";
		
		// Save Excel 2007 file
		$objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);
		$currentDirectory = __DIR__;
		$finalPath = $currentDirectory."/../xlsx/".$fileName;
		$objWriter->save($finalPath);

		return $finalPath;
	}
	
	public function exportExcelBase64($data,$headerArr,$dataKeyArr){
		include_once 'PHPExcel.php';
		include_once 'PHPExcel/Writer/Excel2007.php';
		// Create new PHPExcel object
		$objPHPExcel = new PHPExcel();

		$objPHPExcel->setActiveSheetIndex(0);
		$excelRow = 0;

		$excelRow += 1;
		$alphaRow = A;

		/* header bonus list */
		foreach ($headerArr as $headerRow) {
			$objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $headerRow);
		}

		foreach ($data as $key => $data) {
			$excelRow++;
			$alphaRow = A;

			foreach ($dataKeyArr as $dataKey) {
				if(strlen($data[$dataKey]) > 13) $objPHPExcel->getActiveSheet()->setCellValueExplicit($alphaRow++.$excelRow, $data[$dataKey], PHPExcel_Cell_DataType::TYPE_STRING);
				else $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $data[$dataKey]);
			}
		}
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
		ob_start();
		$objWriter->save('php://output');
		$excelOutput = ob_get_clean();
		$rawFile = base64_encode($excelOutput);
		return $rawFile;
	}

	public function simpleExportExcelMultipleSheet($exportAry, $headerAry, $titleAry, $fileName){

		$objPHPExcel = new PHPExcel();
		$sheet = 0;
		foreach($exportAry as $key => $exportData){
			$char = "A"; $rows = 1;
			// Create a new worksheet, after the default sheet
			$objPHPExcel->createSheet();

			// Add some data to the second sheet, resembling some different data types
			$objPHPExcel->setActiveSheetIndex($sheet);
			$counter = $sheet+1;
			echo "Worksheet: ".$counter."\n";
			echo "Inserting Header...\n";
			
			foreach ($headerAry as $header) {
				$objPHPExcel->getActiveSheet()->SetCellValue($char . $rows, $header);
				$char++;
			}

			$objPHPExcel->getActiveSheet()->setTitle("$key");
			
			echo "Inserting Data...\n";
			$rows = 2;
			foreach ($exportData as $data) {
				$char = "A";
				foreach ($titleAry as $title) {
					$objPHPExcel->getActiveSheet()->SetCellValue($char . $rows, $data[$title]);
					$char++;
				}
				$rows++;
			}

			$sheet++;
		}
		
		echo "Generating Excel...\n";
		
		// Save Excel 2007 file
		$objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);
		$currentDirectory = __DIR__;
		$finalPath = $currentDirectory."/../xlsx/".$fileName;
		$objWriter->save($finalPath);

		return $finalPath;
	}
}?>