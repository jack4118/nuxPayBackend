<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * Date 20/04/2018.
    **/

    class Report {
        
        function __construct($db, $general, $setting) {
            $this->db = $db;
            $this->general = $general;
            $this->setting = $setting;
        }

        // function getLeaderGroupSalesReport($params, $clientID, $adminID, $site) {
        function getLeaderGroupSalesReport($params) {

            $db = $this->db;
            $general = $this->general;
            $setting = $this->setting;

            $searchData = $params['searchData'];
            $genealogy = $params['genealogy'] ? $params['genealogy'] : "tree_sponsor";

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = $general->getLimit($pageNumber);
            $decimalPlaces  = $setting->getSystemDecimalPlaces();

            $genealogyArray = array("tree_sponsor", "tree_placement");

            if(!in_array($genealogy, $genealogyArray))
                $genealogy = "tree_sponsor";

            // If Member, Select Only Member Himself Only
            // if($site == "Member") {
            //     $db->where('client_id', $clientID);
            //     $clientTraceKey = $db->getValue($genealogy, 'trace_key');
            //     if(empty($clientTraceKey))
            //         return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            //     $db->where('trace_key', $clientTraceKey."%", 'like');
            //     $clientDownlines = $db->get($genealogy, null, 'client_id');
            //     if(empty($clientDownlines))
            //         return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            //     $db->where('client_id', $clientDownlines, 'in');
            // }
            // else {
            //     ********************************************************************************
            //     $db->get('mlm')
            //     $memberIDQuery = "SELECT value, reference 
            //     FROM mlmAdminSetting 
            //     WHERE name = 'leaderUsername' AND adminID= '".mysql_escape_string($adminID)."'";
            //     $memberIDRes = $db->dbSql($memberIDQuery);
            //     $memberIDRow = mysql_fetch_assoc($memberIDRes);

            //     $memberClientID = $memberIDRow["reference"];

            //     if($memberClientID!=""){
            //         $ruleAry[] = " a.clientID IN (".implode(",", $client->specialAdminSearchSpecificMember($memberClientID)).") ";
            //     }
            //     ********************************************************************************
            // }

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'product':
                            $db->where('name', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $productIDs = $db->getValue('mlm_product', 'id', null);
            if(empty($productIDs))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            // UI option's data
            $db->orderBy('id', 'asc');
            $productNameList = $db->getValue('mlm_product', 'name', null);
            if(empty($productNameList))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>"");
                                    
                                $db->where('created_at', date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Date from cannot be later than date to.', 'data'=>$data);

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

            $db->where('product_id', $productIDs, 'IN');
            $db->orderBy('created_at', 'desc');
            $db->orderBy('id', 'desc');
            $copyDb = $db->copy();
            $getCountryName = "(SELECT country.name FROM country WHERE country.id=(SELECT country_id FROM client WHERE client.id=client_id)) AS country_name";
            $getProductName = "(SELECT mlm_product.name FROM mlm_product WHERE mlm_product.id=product_id) AS product_name";
            $result = $db->get('mlm_client_portfolio', $limit, 'created_at, client_id, portfolio_type, bonus_value, product_price, unit_price,'.$getCountryName.', '.$getProductName);
            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No result found.", 'data' => "");

            $totalBV = 0;
            $totalAmount = 0;
            foreach($result as $value) {
                $portfolio['created_at'] = $value['created_at'];
                $portfolio['client_id'] = $value['client_id'];
                $portfolio['country_name'] = $value['country_name'];
                $portfolio['product_name'] = $value['product_name'];
                $portfolio['portfolio_type'] = $value['portfolio_type'];
                $portfolio['bonus_value'] = number_format($value['bonus_value'], $decimalPlaces, '.', '');
                $portfolio['amount'] = $value['product_price'] * $value['unit_price'];
                $portfolio['amount'] = number_format($portfolio['amount'], $decimalPlaces, '.', '');

                $portfolioList[] = $portfolio;

                $totalBV += $value["bonus_value"];
                $totalAmount += $portfolio['amount'];
            }
            unset($result);

            $total = '<tr><td></td><td></td><td></td><td></td><th class="text-right">Total :</th><th>'.number_format($totalBV, $decimalPlaces, '.', '').'</th><th>'.number_format($totalAmount, $decimalPlaces, '.', '').'</th></tr>';

            $totalRecord = $copyDb->getValue('mlm_client_portfolio', 'count(id)');
            $data['portfolioList'] = $portfolioList;
            $data['total'] = $total;
            $data['productNameList'] = $productNameList;
            $data['totalPage'] = ceil($totalRecords/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord'] = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        // function getSalesPlacementReport($params,$adminID) {
        function getSalesPlacementReport($params) {
            $db = $this->db;
            $general = $this->general;
            $setting = $this->setting;

            $searchData = $params['searchData'];
            $genealogy = $params['genealogy'] ? $params['genealogy'] : "tree_sponsor";

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = $general->getLimit($pageNumber);
            $decimalPlaces  = $setting->getSystemDecimalPlaces();

            $genealogyArray = array("tree_sponsor", "tree_placement");

            if(!in_array($genealogy, $genealogyArray))
                $genealogy = "tree_sponsor";

            // *********************************************************************************
            // $db->where('name', 'leaderUsername');
            // $db->where('admin_id', $adminID);
            // $reference = $db->getValue('mlm_admin_setting', 'reference', null);
            // if(!empty($reference)) {
            //     $db->where('client_id', $reference)
            //     $clientTraceKey = $db->getValue($genealogy, 'trace_key');
            //     if(empty($clientTraceKey))
            //         return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            //     $db->where('trace_key', $clientTraceKey."%", 'like');
            //     $clientDownlines = $db->get($genealogy, null, 'client_id');
            //     if(empty($clientDownlines))
            //         return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            //     $db->where('client_id', $clientDownlines, 'in');
            // }
            // *********************************************************************************

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'product':
                            $db->where('name', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $getBonusValue = "(SELECT value FROM mlm_product_setting setting WHERE setting.product_id=product.id AND name='bonusValue') AS bonus_value";
            $getProductNameTranslation = "(SELECT content FROM language_translation translation WHERE translation.code=translation_code AND language='english') AS product_name_translation";
            $product = $db->get('mlm_product product', null, 'id, name, '.$getBonusValue.', '.$getProductNameTranslation);
            if(empty($product))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");
            foreach($product as $value) {
                $productIDs[] = $value['id'];
                $productNames[] = $value['name'];
                $productNamesTranslation[] = $value['product_name_translation'];
                $productBonusValue[$value['name']] = $value['bonus_value'];
            }

            // UI option's data
            $db->orderBy('id', 'asc');
            $productNameList = $db->getValue('mlm_product', 'name', null);
            if(empty($productNameList))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>"");
                                    
                                $db->where('created_at', date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Date from cannot be later than date to.', 'data'=>$data);

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

            $db->where('product_id', $productIDs, 'IN');
            $db->where('bonus_value', 0, '>');
            $db->where('product_price', 0, '>');
            $db->groupBy('DATE(created_at)');
            $db->groupBy('product_id');
            $db->orderBy('created_at', 'desc');
            $copyDb = $db->copy();

            $getProductName = "(SELECT name FROM mlm_product WHERE mlm_product.id=product_id) AS product_name";
            $result = $db->get('mlm_client_portfolio portfolio', $limit, 'COUNT(id) AS quantity, SUM(bonus_value) AS amount, DATE(created_at) AS date, '.$getProductName);
            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No result found.", 'data' => "");

            foreach($result as $value) {
                $report[$value['date']][$value['product_name']] = $value;
                $report[$value['date']]['quantity'] += $value['quantity'];
                $report[$value['date']]['amount'] += $value['amount'];
            }
            unset($result);

            $settingIDs = $db->subQuery();
            $settingIDs->where('name', "isWallet");
            $settingIDs->where('value', 1);
            $settingIDs->get('credit_setting', null, 'credit_id');

            $db->where('id', $settingIDs, 'IN');
            $copyCreditType = $db->copy();
            $creditType = $db->getValue('credit','name', null);
            if(empty($creditType))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");
            $creditTypeCount = $copyCreditType->getValue('credit', 'COUNT(*)');

            foreach(array_keys($report) as $value) {

                foreach($creditType as $type) {
                    $paymentMethod[$value][$type] = 0;
                }

                $portfolioIDs = $db->subQuery();
                $portfolioIDs->where('product_id', $productIDs, 'IN');
                $portfolioIDs->where('bonus_value', 0, '>');
                $portfolioIDs->where('product_price', 0, '>');
                $portfolioIDs->where('DATE(created_at)', $value);
                $portfolioIDs->get('mlm_client_portfolio', null, 'id');

                $invoiceItemIDs = $db->subQuery();
                $invoiceItemIDs->where('portfolio_id', $portfolioIDs, 'IN');
                $invoiceItemIDs->get('mlm_invoice_item', null, 'id');

                $db->where('invoice_item_id', $invoiceItemIDs, 'IN');
                $result = $db->get('mlm_invoice_item_payment', null, 'credit_type, amount');
                if(empty($result))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

                foreach($result as $val) {
                    $paymentMethod[$value][$val['credit_type']] += $val['amount'];
                }
            }

            // table data
            foreach($report as $key => $value) {
                $record[] = date("d/m/Y", strtotime($key));
                $record[] = $value['quantity'];
                $record[] = number_format($value['amount'], $decimalPlaces, '.', '');

                // each product
                foreach($productNames as $val) {
                    $record[] = empty($value[$val]['quantity']) ? "0" : $value[$val]['quantity'];
                    $record[] = $productBonusValue[$val];
                    $record[] = empty($value[$val]['amount']) ? "0.00" : number_format($value[$val]['amount'], $decimalPlaces, '.', '');
                }

                // each payment method
                $totalAmount = 0;
                foreach($creditType as $type) {
                    $record[] = number_format($paymentMethod[$key][$type], $decimalPlaces, '.', '');
                    $totalAmount += $paymentMethod[$key][$type];
                }

                // total payment method
                $record[] = number_format($totalAmount, $decimalPlaces, '.', '');

                $salesReport[] = $record;
                unset($record);
            }

            // first header
            $firstTableHeader = '<th colspan="1"></th><th colspan="2" class="text-center">Total</th>';
            foreach($productNamesTranslation as $value) {
                $firstTableHeader = $firstTableHeader.'<th colspan="3" class="text-center">'.$value.'</th>';
            }
            $firstTableHeader = $firstTableHeader.'<th colspan="'.$creditTypeCount.'" class="text-center">Payment Method</th><th class="text-center">Total</th>';

            // second header
            $secondTableHeader = 0;
            foreach($productNames as $value) {
                $secondTableHeader++;
            }

            $totalRecord = $copyDb->getValue('mlm_client_portfolio', 'COUNT(*)');
            $data['productNameList'] = $productNameList;
            $data['report'] = $salesReport;
            $data['firstTableHeader'] = $firstTableHeader;
            $data['secondTableHeader'] = $secondTableHeader;
            $data['creditType'] = $creditType;
            $data['totalPage'] = ceil($totalRecord/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord'] = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        function getSalesPurchaseReport($params) {
            $db = $this->db;
            $general = $this->general;
            $setting = $this->setting;

            $searchData = $params['searchData'];
            $genealogy = $params['genealogy'] ? $params['genealogy'] : "tree_sponsor";

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = $general->getLimit($pageNumber);
            $decimalPlaces  = $setting->getSystemDecimalPlaces();

            $genealogyArray = array("tree_sponsor", "tree_placement");

            if(!in_array($genealogy, $genealogyArray))
                $genealogy = "tree_sponsor";

            // *********************************************************************************
            // $db->where('name', 'leaderUsername');
            // $db->where('admin_id', $adminID);
            // $reference = $db->getValue('mlm_admin_setting', 'reference', null);
            // if(!empty($reference)) {
            //     $db->where('client_id', $reference)
            //     $clientTraceKey = $db->getValue($genealogy, 'trace_key');
            //     if(empty($clientTraceKey))
            //         return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            //     $db->where('trace_key', $clientTraceKey."%", 'like');
            //     $clientDownlines = $db->get($genealogy, null, 'client_id');
            //     if(empty($clientDownlines))
            //         return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            //     $db->where('client_id', $clientDownlines, 'in');
            // }
            // *********************************************************************************

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'product':
                            $db->where('name', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $getBonusValue = "(SELECT value FROM mlm_product_setting setting WHERE setting.product_id=product.id AND name='bonusValue') AS bonus_value";
            $getProductNameTranslation = "(SELECT content FROM language_translation translation WHERE translation.code=translation_code AND language='english') AS product_name_translation";
            $product = $db->get('mlm_product product', null, 'id, name, '.$getBonusValue.', '.$getProductNameTranslation);
            if(empty($product))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");
            foreach($product as $value) {
                $productIDs[] = $value['id'];
                $productNames[] = $value['name'];
                $productNamesTranslation[] = $value['product_name_translation'];
                $productBonusValue[$value['name']] = $value['bonus_value'];
            }

            // UI option's data
            $db->orderBy('id', 'asc');
            $productNameList = $db->getValue('mlm_product', 'name', null);
            if(empty($productNameList))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>"");
                                    
                                $db->where('created_at', date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Date from cannot be later than date to.', 'data'=>$data);

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

            $db->where('product_id', $productIDs, 'IN');
            $db->where('bonus_value', 0, '>');
            $db->where('product_price', 0, '>');
            $db->groupBy('DATE(created_at)');
            $db->groupBy('product_id');
            $db->orderBy('created_at', 'desc');
            $copyDb = $db->copy();

            $getProductName = "(SELECT name FROM mlm_product WHERE mlm_product.id=product_id) AS product_name";
            $result = $db->get('mlm_client_portfolio portfolio', $limit, 'COUNT(id) AS quantity, SUM(bonus_value) AS amount, DATE(created_at) AS date, '.$getProductName);
            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No result found.", 'data' => "");

            foreach($result as $value) {
                $report[$value['date']][$value['product_name']] = $value;
                $report[$value['date']]['quantity'] += $value['quantity'];
                $report[$value['date']]['amount'] += $value['amount'];
            }
            unset($result);

            // table data
            foreach($report as $key => $value) {
                $record[] = date("d/m/Y", strtotime($key));
                $record[] = $value['quantity'];
                $record[] = number_format($value['amount'], $decimalPlaces, '.', '');

                // each product
                foreach($productNames as $val) {
                    $record[] = empty($value[$val]['quantity']) ? "0" : $value[$val]['quantity'];
                    $record[] = $productBonusValue[$val];
                    $record[] = empty($value[$val]['amount']) ? "0.00" : number_format($value[$val]['amount'], $decimalPlaces, '.', '');
                }

                $salesReport[] = $record;
                unset($record);
            }

            // first header
            $firstTableHeader = '<th colspan="1"></th><th colspan="2" class="text-center">Total</th>';
            foreach($productNamesTranslation as $value) {
                $firstTableHeader = $firstTableHeader.'<th colspan="3" class="text-center">'.$value.'</th>';
            }

            // second header
            $secondTableHeader = 0;
            foreach($productNames as $value) {
                $secondTableHeader++;
            }

            $totalRecord = $copyDb->getValue('mlm_client_portfolio', 'COUNT(*)');
            $data['productNameList'] = $productNameList;
            $data['report'] = $salesReport;
            $data['firstTableHeader'] = $firstTableHeader;
            $data['secondTableHeader'] = $secondTableHeader;
            $data['totalPage'] = ceil($totalRecord/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord'] = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }
    }
?>