<?php

    /**
     * Accounting Class:
     * Used for retrieving and calculating client's credit balance in the system
     */
    
    class Account
    {
        
        function __construct($db, $setting, $message, $provider, $log) {
            $this->db = $db;
            $this->setting = $setting;
            $this->message = $message;
            $this->provider = $provider;
            $this->log = $log;
            
            $this->creatorID = "";
            $this->creatorType = "";
        }
        
        public function setCreator($creatorID, $creatorType) {
            $this->creatorID = $creatorID;
            $this->creatorType = $creatorType;
        }

        public function insertDebitTransaction($userID, $type, $amount, $referenceID, $transactionDate){
            $db = $this->db;
            $setting = $this->setting;


            if(!trim($transactionDate)) {
                $transactionDate = date("Y-m-d H:i:s");
			}
            $tblDate = date('Ymd', strtotime($transactionDate));

            $result = $db->rawQuery("CREATE TABLE IF NOT EXISTS xun_acc_credit_".$db->escape($tblDate)." LIKE xun_acc_credit");
            
            if($amount < 0){
                return false;
            }

            // $decimalPlaces = $setting->getSystemDecimalPlaces();
            $decimalPlaces = 8;
            
            // Format amount to according to decimal places
            $amount = number_format($amount, $decimalPlaces, ".", "");

            // Get balance from acc_closing & acc_credit_%
            $accountBalance = $this->getBalance($userID, $type, "", false);
            $currBalance = $accountBalance;
            $accountBalance += $amount; 
            
            // Set fields for xun_acc_credit table
            $insertData = array(
                "user_id" => $userID,
                "type" => $type,
                "debit" => $amount,
                "credit" => 0,
                "balance" => $accountBalance,
                "reference_id" => $referenceID,
                "created_at" => $transactionDate
            );

            $debitID = $db->insert("xun_acc_credit_".$tblDate, $insertData);
            if(!$debitID){
                return false;
            }
            
            $this->updateClientCacheBalance($userID, $type, $accountBalance);

            return true;
        }

        public function insertTAccount($userID, $type, $amount, $subject, $referenceID, $transactionDate=null, $remark="") {
			$db = $this->db;
            $setting = $this->setting;
            
            // $type - name of currency
            // $amount
            // $subject - transaction subject
            // $referenceID - additional ID to keep track when needed.
            // $transactionDate - enter in this format --> date("Y-m-d H:i:s")
            // $remark - Remark for credit_transaction
            
            if(!trim($transactionDate)) {
                $transactionDate = date("Y-m-d H:i:s");
			}
            $tblDate = date('Ymd', strtotime($transactionDate));

			$result = $db->rawQuery("CREATE TABLE IF NOT EXISTS xun_acc_credit_".$db->escape($tblDate)." LIKE xun_acc_credit");
            
            // Check for negative amount
            if ($amount < 0) {
                // Send message to Xun
                $find = array("%%subject%%", "%%errorMessage%%", "%%userID%%", "%%creditType%%", "%%amount%%", "%%currentBalance%%");
                $replace = array($subject, 'This transaction amount is negative.', $userID, $type, $amount, "");
                // $message->createMessageOut('90006', NULL, NULL, $find, $replace);

				return false;
			}
            
            // $decimalPlaces = $setting->getSystemDecimalPlaces();
            $decimalPlaces = 8;
            
            // Format amount to according to decimal places
            $amount = number_format($amount, $decimalPlaces, ".", "");

            // Get balance from acc_closing & acc_credit_%
            $accountBalance = $this->getBalance($userID, $type, "", false);
            // Check if balance is negative after deducting amount
            $currBalance = $accountBalance;
            $accountBalance -= $amount; // Credit - minus
            if($accountBalance < 0) {
                // Send message to Xun
                $find = array("%%subject%%", "%%errorMessage%%", "%%userID%%", "%%creditType%%", "%%amount%%", "%%currentBalance%%");
                $replace = array($subject, 'Sender balance is insufficient to perform this transaction.', $userID, $type, $amount, 'Current Balance : '.$currBalance);
                // $message->createMessageOut('90006', NULL, NULL, $find, $replace);

                return false;
            }    
            
            // Set fields for xun_acc_credit table
            $insertData = array(
                "user_id" => $userID,
                "type" => $type,
                "debit" => 0,
                "credit" => $amount,
                "balance" => $accountBalance,
                "reference_id" => $referenceID,
                "created_at" => $transactionDate
            );

            $creditID = $db->insert("xun_acc_credit_".$tblDate, $insertData);
            if(!$creditID){
                return false;
            }
            
            // 2nd checking on balance > 0 after insert credit, pass the flag as false so that it won't update the cache balance first
            $accountBalance = $this->getBalance($userID, $type, "", false);
            
            // Check if balance is negative after deducting amount
            if($accountBalance < 0) {
                $data = array('deleted' => 1);
                $db->where('id', $creditID);
                $result = $db->update('xun_acc_credit_'.$tblDate, $data);

                // Send message to Xun
                $find = array("%%subject%%", "%%errorMessage%%", "%%userID%%", "%%creditType%%", "%%amount%%", "%%currentBalance%%");
                $replace = array($subject, 'Sender balance is negative after performing this transaction.', $userID, $type, $amount, 'Current Balance : '.$accountBalance);
                // $message->createMessageOut('90006', NULL, NULL, $find, $replace);

                return false; // Stop here after updating the credit row in acc_credit
            }
            
            $this->updateClientCacheBalance($userID, $type, $accountBalance);

            return true;
		}
        
        // Get balance from acc_closing & acc_credit_%
        public function getBalance($userID, $type, $date='', $updateCache=true) {
            global $xunCrypto, $xunPaymentGateway;
            $db = $this->db;
            $setting = $this->setting;
            
            // $decimalPlaces = $setting->getSystemDecimalPlaces();
            $decimalPlaces = 8;
            $db->where('active', 1);
            $db->where('user_id', $userID);
            $db->where('address_type', 'nuxpay_wallet');
            $internal_address = $db->getValue('xun_crypto_user_address','address');

            if(!$internal_address){
                $company_address_list = $xunCrypto->company_wallet_address();

                foreach($company_address_list as $key => $value){
                    if($userID == $value['type']){
                        $internal_address = $key;
                        break;
                    }
                }
            }
            
            $external_address = $xunCrypto->get_external_address($internal_address, $type);
            
            $db->where('user_id', $userID);
            $db->where('type', $type);
            if ($date) {
                // If date is passed in as argument, we only want to select the range up till the given date
                $db->where('date', $date, '<=');
                $tsCondition = strtotime($date);
            }
            $count = $db->getValue('xun_acc_closing', 'count(id)');

            // 0 means no rows exist in the acc_closing for this client
            if($count == 0) {
                $balance = 0;
                $latestDate = '';
            }
            else {
                
                // Get the latest acc_closing date for this client
                $db->where('user_id', $userID);
                $db->where('type', $type);
                $db->orderBy('date', "DESC");
                $accClosingResults = $db->getOne('xun_acc_closing', null, 'balance, date');
                
                $latestDate = $accClosingResults["date"];
                $balance = $accClosingResults["balance"];
                
            }
            
            $tsLatest = strtotime($latestDate);
            $totalCredit = 0;
            $totalDebit = 0;
            
            // Get all acc_credit_% tables
            $result = $db->rawQuery('SHOW TABLES LIKE "xun_payment_transaction_history%"');
            foreach ($result as $array) {
                foreach ($array as $key=>$val) {
                    $val = explode('_', $val);
                    $dateCredit = end($val);
                    $tsCredit = strtotime($dateCredit);
                    // Compare the date with the latest acc_closing date
                    // For eg. there exist tables
                    // acc_credit_20170801, acc_credit_20170802, acc_credit_20170803, acc_credit_20170804
                    // Condition 1: If acc_closing on 20170802,
                    // This 'if' part will sum up acc_credit_20170803 and acc_credit_20170804 debit & credit
                    // Condition 2: If acc_closing on 20170804,
                    // This 'if' part won't run
                    if($tsCredit > $tsLatest) {
                        if ($tsCondition && $tsCredit > $tsCondition) {
                            // If it exceeds the time of the date argument, breka from the loop
                            break;
                        }

                        $addressArr = array($internal_address, $external_address);
                        // $db->where('account_id', $userID);
                        $db->where('gateway_type', 'BC');
                        // $db->where('recipient_user_id', $userID);
                        $db->where('wallet_type', $type);
                        $db->where('recipient_address', $addressArr, 'IN');
                        $db->where('status', array('success', 'completed', 'wallet_success'), 'in');
                        // $db->where('status', 'failed', '!=');
                        // $db->where('deleted', 0);
                        $creditRes1 = $db->getOne('xun_payment_transaction_history_'.$dateCredit, 'SUM(amount) as credit');

                        $db->where('gateway_type', 'PG');
                        $db->where('transaction_type', 'auto_fund_out');
                        $db->where('recipient_address', $addressArr, 'IN');
                        $db->where('wallet_type', $type);
                        $db->where('status', 'failed', '!=');
                        // $db->where('recipient_address', $addressArr, 'IN');
                        // $db->where('deleted', 0);
                        $creditRes2 = $db->getOne('xun_payment_transaction_history_'.$dateCredit, 'SUM(amount) as credit');

                        $db->where('gateway_type', 'BC');
                        // $db->where('sender_user_id', $userID);
                        $db->where('wallet_type', $type);
                        $db->where('sender_address', $addressArr, 'IN');
                        // $db->where('status', array('success', 'completed', 'wallet_success'), 'in');
                        $db->where('status', 'failed', '!=');
                        $debitRes = $db->getOne('xun_payment_transaction_history_'.$dateCredit, 'SUM(amount) as debit');

                        if($debitRes){
                            $db->where('gateway_type', 'BC');
                            // $db->where('sender_user_id', $userID);
                            $db->where('fee_wallet_type', $type);
                            $db->where('sender_address', $addressArr, 'IN');
                            // $db->where('status', array('success', 'completed', 'wallet_success'), 'in');
                            $db->where('status', 'failed', '!=');
                            $feeRes = $db->getOne('xun_payment_transaction_history_'.$dateCredit, 'SUM(fee_amount) as fee');
                        }
                       else{
                           $feeRes['fee'] = '0';
                       }

                        $credit1 = $creditRes1['credit'] ? $creditRes1['credit'] : 0;
                        $credit2 = $creditRes2['credit'] ? $creditRes2['credit'] : 0;
                        $credit = bcadd($credit1, $credit2, $decimalPlaces);
                        $totalCredit = bcadd($totalCredit, $credit, $decimalPlaces);
                        $totalDebit = bcadd($totalDebit,bcadd($debitRes['debit'], $feeRes['fee'], $decimalPlaces), $decimalPlaces);
                        // $totalCredit += $creditRes['credit'];
                        // $totalDebit += $debitRes['debit'];
                    }
                }
            }
            
            // $balance = number_format(($balance + $totalCredit - $totalDebit), $decimalPlaces, '.', '');

            $total = bcsub($totalCredit, $totalDebit, $decimalPlaces);
            $balance = bcadd($balance, $total, $decimalPlaces);

            //Offset amount
            $offset_amount = $xunPaymentGateway->get_offset_balance($internal_address, $wallet_type);

            if($offset_amount != 0){
                $db->where('currency_id', $wallet_type);
                $unit_conversion = $db->getValue('xun_marketplace_currencies', 'unit_conversion');

                $balance_satoshi = bcmul($balance, $unit_conversion);

                $remaining_balance = bcadd($balance_satoshi, $offset_amount);

                $balance = bcdiv($remaining_balance, $unit_conversion, 8);
        }
            
            // //  update to client_setting table
            // if ($updateCache && !$date) {
            //     // Update cache balance for the clientID
            //     $this->updateClientCacheBalance($userID, $type, $balance);
            // }
            
            return $balance;
        }
        
        public function getClientCacheBalance($userID, $creditType) {
            $db = $this->db;
            $setting = $this->setting;
            
            // $decimalPlaces = $setting->getSystemDecimalPlaces();
            $decimalPlaces = 8;
            
            $db->where('user_id', $userID);
            $db->where('name', $creditType . "Balance");
            $result = $db->getOne('xun_user_setting', 'value');
            
            return $result['value']? number_format($result['value'], $decimalPlaces, '.', '') : 0;
        }
        
         public function updateClientCacheBalance($userID, $creditType, $balance) {
            $db = $this->db;
            
            $creditSetting = $creditType . "Balance";
            $db->where('user_id', $userID);
            $db->where('name', $creditSetting);
            $count = $db->getValue("xun_user_setting", "count(user_id)");
            
            if ($count == 0) {
                // Insert new record
                $insertData = array(
                    'user_id' => $userID,
                    'name' => $creditSetting,
                    'value' => $balance,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                );
                $db->insert("xun_user_setting", $insertData);
            }
            else {
                $data = array('value' => $balance, 'updated_at' => date('Y-m-d H:i:s'));
                $db->where('user_id', $userID);
                $db->where('name', $creditSetting);
                $db->update("xun_user_setting", $data);
            }

        }
        
        /**
         * Accountings closing function
         * Used for calculating the total day's balance for each client and carry forward to the next date
         */
        public function closing($closingDate) {
            global $xunCrypto, $xunCurrency, $post;
            $db = $this->db;
            $log = $this->log;
            $message = $this->message;
            
            // Convert to timestamp for comparison
            $closingTimestamp = strtotime($closingDate);

            $log->write(date("Y-m-d H:i:s")." Deleting closing date $closingDate onwards.\n");
            
            if ($this->deleteClosing($closingDate)) {
                $log->write(date("Y-m-d H:i:s")." Successfully deleted closing from $closingDate onwards.\n");
            }
            
            //Get Business with NuxPay record
            $db->where('address_type', 'nuxpay_wallet');
            $db->where('active', 1);
            $nuxpay_address_data = $db->map('user_id')->ArrayBuilder()->get('xun_crypto_user_address');
            
            //  Select all users
            //$db->where('id', '16692');
            $userRes = $db->get("xun_user", null, "id, username, type, created_at");

            $wallet_type_list = $xunCurrency->get_cryptocurrency_list('a.currency_id');
            // foreach($userRes as $userRow){
            //     if($nuxpay_address_data[$userRow['id']]){ 
            //         foreach($wallet_type_list as $key => $value){
            //             $userRow["credit_type"] = $value;
            //             $userArray[] = $userRow;
            //         }
            //     }
               

            $userArray = $userRes;
            foreach ($userArray as $userRow) {
                
                $user_id = $userRow['id'];
                // $creditTypeList = $userRow['credit_type_list'];
                // $db->where("user_id", $userRow["id"]);
                // $db->where("type", $creditType);
                // $db->orderBy("date", "DESC");
                // $accClosingResults = $db->getOne('xun_acc_closing');
                // $lq = $db->getLastQuery();

                // $lastClosingDate = $accClosingResults["date"];
                // $lastBalance = $accClosingResults["balance"]? $accClosingResults["balance"] : 0;

                // $withholdCreditType = $creditType.'Withholding';
                // $db->where("user_id", $userRow["id"]);
                // $db->where("type", $withholdCreditType);
                // $db->orderBy("date", "DESC");
                // $withholdClosingResults = $db->getOne('xun_acc_closing');
                // $withhold_lq = $db->getLastQuery();
                // $withholdLastBalance = $withholdClosingResults['balance'] ? $withholdClosingResults['balance'] : 0;
                // $lastWithholdClosingDate = $withholdClosingResults['date'];

                $internal_address = $nuxpay_address_data[$userRow['id']]['address'];
                if(!$internal_address){
                    continue;
                }
                
                $userWalletQuery = "SELECT tmp.user_id, tmp.type, IF (cls2.balance IS NULL, 0, cls2.balance) as lastClosingBalance, 
                IF (tmp.lastClosingDate = '-', '2021-03-26', DATE_FORMAT(DATE_ADD(tmp.lastClosingDate, INTERVAL 1 DAY), '%Y-%m-%d')) as nextClosingDate
                FROM (SELECT  cls.user_id, cls.type, IF (MAX(cls.date) IS NULL, '-', MAX(cls.date)) as lastClosingDate
                    FROM `xun_acc_closing` cls WHERE cls.user_id='".$user_id."' GROUP BY cls.type ) tmp LEFT JOIN `xun_acc_closing` cls2 ON (tmp.user_id=cls2.user_id and tmp.type=cls2.type and tmp.lastClosingDate=cls2.date)";
                
                $accClosingResults = $db->rawQuery($userWalletQuery);

                $accClosingResults = array_column($accClosingResults, null, 'type');

                $log->write(date("Y-m-d H:i:s")." User ".$user_id." LQ: $userWalletQuery\n");

                //if($accClosingResults){
                    foreach($wallet_type_list as $wallet_key => $creditType){
                        // echo "wallet_value:".$creditType."\n";
                        $withholdCreditType = $creditType."Withholding";

                        if($accClosingResults){
                            $lastClosingDate = $accClosingResults[$creditType]['nextClosingDate'] ? $accClosingResults[$creditType]['nextClosingDate'] : "2021-03-26";
                            $lastBalance = $accClosingResults[$creditType]['lastClosingBalance'];
                
                            $log->write(date("Y-m-d H:i:s")." Last closing date for user ".$userRow["username"]." Credit Type: $creditType is $lastClosingDate.  LQ: $lq\n");
    
                            $withholdLastClosingDate = $accClosingResults[$withholdCreditType]['nextClosingDate'] ? $accClosingResults[$withholdCreditType]['nextClosingDate'] : "2021-03-26";
                            $withholdLastBalance = $accClosingResults[$withholdCreditType]['lastClosingBalance'];
                        }
                        else
                        {
                            $lastClosingDate = date('Y-m-d', strtotime($userRow['created_at']));
                            $lastBalance = 0;
            
                            $log->write(date("Y-m-d H:i:s")." Last closing date for user ".$userRow["username"]." Credit Type: $creditType is $lastClosingDate.  LQ: $lq\n");
    
                            $withholdLastClosingDate =  date('Y-m-d', strtotime($userRow['created_at']));
                            $withholdLastBalance = 0;
                        }

                       
                        $external_address = $xunCrypto->get_external_address($internal_address, $creditType);

                        $addressArr = array($internal_address, $external_address);

                        // Convert to timestamp for comparison
                        $lastClosingTimestamp = strtotime($lastClosingDate);

                        while ($lastClosingTimestamp <= $closingTimestamp) {
                            
                            $lastBalance = $this->closeNuxPayClientAccount($userRow["id"], $lastClosingDate, $lastBalance, $creditType, $addressArr);
                            
                            // Increment by 1 day for next iteration
                            $lastClosingDate = date("Y-m-d", strtotime("+1 day", strtotime($lastClosingDate)));
                            $lastClosingTimestamp = strtotime($lastClosingDate);
                            
                        }

                        $log->write(date("Y-m-d H:i:s")." Finish closing $creditType for ".$userRow["username"]."[".$userRow["id"]."]. Balance: ".$lastBalance."\n");

                        $log->write(date("Y-m-d H:i:s")." Last Withhold Closing date for user ".$userRow["username"]." Credit Type: $withholdCreditType is $lastClosingDate.  LQ: $lq\n");

                        $withholdLastClosingTimestamp = strtotime($withholdLastClosingDate);
                        
                        while ($withholdLastClosingTimestamp <= $closingTimestamp) {
                            $withholdLastBalance = $this->closeNuxPayWithholdingClientAccount($userRow["id"], $withholdLastClosingDate, $withholdLastBalance, $withholdCreditType, $addressArr);
                            // Increment by 1 day for next iteration
                            $withholdLastClosingDate = date("Y-m-d", strtotime("+1 day", strtotime($withholdLastClosingDate)));
                            $withholdLastClosingTimestamp = strtotime($withholdLastClosingDate);
                            
                        }

                        $log->write(date("Y-m-d H:i:s")." Finish closing $withholdCreditType for ".$userRow["username"]."[".$userRow["id"]."]. Balance: ".$withholdLastBalance."\n");
                    }
                }
            
                
               
                // Update client's latest cache balance for current currency
                // $balance = $this->getBalance($userRow["id"], $creditType); 

                // $log->write(date("Y-m-d H:i:s")." Finish closing $creditType for ".$userRow["username"]."[".$userRow["id"]."]. Balance: ".$lastBalance."\n");

            $company_address_list = $xunCrypto->company_wallet_address();

            foreach($company_address_list as $address_key => $address_value){
                $address_type = $address_value['type'];
                $internal_address = $address_key;

                if($address_type == 'payment_gateway'){
                    continue;
                }

                $userWalletQuery = "SELECT tmp.user_id, tmp.type, IF (cls2.balance IS NULL, 0, cls2.balance) as lastClosingBalance, 
                IF (tmp.lastClosingDate = '-', '2021-03-26', DATE_FORMAT(DATE_ADD(tmp.lastClosingDate, INTERVAL 1 DAY), '%Y-%m-%d')) as nextClosingDate
                FROM (SELECT  cls.user_id, cls.type, IF (MAX(cls.date) IS NULL, '-', MAX(cls.date)) as lastClosingDate
                    FROM `xun_acc_closing` cls WHERE cls.user_id='".$address_type."' GROUP BY cls.type ) tmp LEFT JOIN `xun_acc_closing` cls2 ON (tmp.user_id=cls2.user_id and tmp.type=cls2.type and tmp.lastClosingDate=cls2.date)";
            
                $accClosingResults = $db->rawQuery($userWalletQuery);

                $accClosingResults = array_column($accClosingResults, null, 'type');

                $log->write(date("Y-m-d H:i:s")." User ".$address_type." LQ: $userWalletQuery\n");

                if($accClosingResults){
                    foreach($wallet_type_list as $wallet_key => $creditType){
                        // echo "wallet_value:".$creditType."\n";

                        $lastClosingDate = $accClosingResults[$creditType]['nextClosingDate'] ? $accClosingResults[$creditType]['nextClosingDate'] : "2021-03-26";
                        $lastBalance = $accClosingResults[$creditType]['lastClosingBalance'];
                        
                        $log->write(date("Y-m-d H:i:s")." Last closing date for user ".$address_type." Credit Type: $creditType is $lastClosingDate.  LQ: $lq\n");

                        $external_address = $xunCrypto->get_external_address($internal_address, $creditType);

                        $addressArr = array($internal_address, $external_address);

                        // Convert to timestamp for comparison
                        $lastClosingTimestamp = strtotime($lastClosingDate);
                
                        while ($lastClosingTimestamp <= $closingTimestamp) {
                            $external_address = $xunCrypto->get_external_address($internal_address, $creditType);
                            $addressArr = array($internal_address, $external_address);
                            
                            $lastBalance = $this->closeNuxPayClientAccount($address_type, $lastClosingDate, $lastBalance, $creditType, $addressArr);
                            
                            // Increment by 1 day for next iteration
                            $lastClosingDate = date("Y-m-d", strtotime("+1 day", strtotime($lastClosingDate)));
                            $lastClosingTimestamp = strtotime($lastClosingDate);
                            
                        }
        
                        // Update client's latest cache balance for current currency
                        //  $balance = $this->getBalance($address_type, $wallet_type); 
        
                        $log->write(date("Y-m-d H:i:s")." Finish closing $creditType for ".$address_type."[".$address_type."]. Balance: ".$lastBalance."\n");
                                

                    }
        
                }
            
                    // Update client's latest cache balance for current currency
                //  $balance = $this->getBalance($address_type, $wallet_type); 

            }
        }


        
        private function closeClientAccount($userID, $closingDate, $previousBalance=0, $creditType) {
            $db = $this->db;
            $setting = $this->setting;
            $log = $this->log;
            
            // $decimalPlaces = $setting->getSystemDecimalPlaces();
            $decimalPlaces = 8;
            
            // Create the xun_acc_credit daily table if not exists
            // $db->rawQuery("CREATE TABLE IF NOT EXISTS xun_acc_credit_".date("Ymd", strtotime($closingDate))." LIKE xun_acc_credit");
            $db->rawQuery("CREATE TABLE IF NOT EXISTS xun_payment_transaction_history_".date("Ymd", strtotime($closingDate))." LIKE xun_payment_transaction_history");
            
            $db->where('user_id', $userID);
            $db->where('type', $creditType);
            $db->where('deleted', 0);
            $accRes = $db->getOne('xun_acc_credit_'.date("Ymd", strtotime($closingDate)), 'SUM(debit) AS debit, SUM(credit) AS credit');
            
            $log->write(date("Y-m-d H:i:s")." Last query: ".$db->getLastQuery()."\n");
            
            $credit = $accRes["credit"]? $accRes["credit"] : 0;
            $debit = $accRes["debit"]? $accRes["debit"] : 0;
            $total = number_format(($debit - $credit), $decimalPlaces, ".", "");
            $balance = number_format(($previousBalance + $total), $decimalPlaces, ".", "");
            $log->write(date("Y-m-d H:i:s")." PreviousBalance: $previousBalance, Debit: $debit, Credit: $credit, Total: $total, Balance: $balance\n");
            
            // Insert client's closing record into xun_acc_closing
            $arrayData = array(
                "user_id" => $userID,
                "type" => $creditType,
                "date" => $closingDate,
                "total" => $total,
                "balance" => $balance,
                "created_at" => date("Y-m-d H:i:s")
            );
            $db->insert('xun_acc_closing', $arrayData);
            
            return $balance; // Return the latest balance
        }

        public function deleteClosing($closingDate) {
            $db = $this->db;
            $log = $this->log;
            
            $db->where('date', $closingDate, " >= ");
            $db->delete('xun_acc_closing');
            // Optmize the table after deletion
            $db->optimize('xun_acc_closing');
        }


        function memberPaymentTransaction($params,$clientID)
        {
            $db = $this->db;
            $setting = $this->setting;

            $downlineID = trim($params["downlineID"]);
            $amount = trim($params["amount"]);
            $paymentType = trim($params["paymentType"]);
            $creditType = trim($params["creditType"]);

            if(strlen($creditType) == 0)
                $creditType = "cash";

            if(empty($downlineID))
                return array("status"=>"error","code"=>"1","statusMsg"=>"Downline id is empty.","data"=>"");

            if(empty($amount))
                return array("status"=>"ok","code"=>"1","statusMsg"=>"Please enter amount.","data"=>"");
            
            if(!is_numeric($amount))
                return array("status"=>"ok","code"=>"1","statusMsg"=>"Please enter a valid amount.","data"=>"");

            if(empty($paymentType))
                return array("status"=>"ok","code"=>"1","statusMsg"=>"Please select your payment option.","data"=>"");

            if($paymentType != "pay" && $paymentType != "receive")
                return array("status"=>"ok","code"=>"1","statusMsg"=>"Invalid payment option.","data"=>"");

            //get downlineName
            $db->where("id",$downlineID);
            $downline = $db->get("client",1,"name");
            if(!empty($downline))
                $downlineName = $downline[0]["name"];

            $db->where("type","Internal");
            $db->where("name","payout");
            $payoutRes = $db->get("client",1,"id");
            if(!empty($payoutRes))
                $payoutID = $payoutRes[0]["id"];

            $fields = array("id","subject","type","from_id","to_id","client_id","amount","remark","belong_id","reference_id","batch_id","deleted","creator_id","creator_type","created_at");
            if($paymentType == "pay")
            {
                $belong = $db->getNewID();
                // insert upline pay to downline
                // $this->insertTAccount($clientID,$payoutID,$creditType,$amount,"Payout to downline",$belong,"",date("Y-m-d H:i:s"),$belong,$clientID);
                // insert downline receive payment from upline
                $this->insertTAccount($payoutID,$downlineID,$creditType,$amount,"Receive payment from upline",$belong,"",date("Y-m-d H:i:s"),$belong,$downlineID);
            }
            else if($paymentType == "receive")
            {
                $belong = $db->getNewID();
                // receive payment from downline
                $this->insertTAccount($downlineID,$payoutID,$creditType,$amount,"Payout to upline",$belong,"",date("Y-m-d H:i:s"),$belong,$downlineID);
                // downline pay to upline
                // $this->insertTAccount($payoutID,$clientID,$creditType,$amount,"Receive payment from downline",$belong,"",date("Y-m-d H:i:s"),$belong,$clientID);
            }

            //get client balance
            $balance = $this->getClientCacheBalance($downlineID,$creditType);

            $db->where("deleted","0");
            $db->where("client_id",$downlineID);
            // $db->where ("(from_id = ? or to_id = ?)", Array($clientID,$clientID));
            $getRes = $db->get("credit_transaction",null,"id,created_at,subject,amount");

            if(!empty($getRes))
            {
                foreach($getRes as $value)
                {
                   
                    if($value["subject"] == "Receive payment from upline" || $value["subject"] == "Payout to upline")
                    {
                        $id[] = $value["id"];
                        $transDate[] = $value["created_at"];

                        $tempSub = $value["subject"];
                        if($tempSub == "Payout to upline")
                        {
                            $subject[] = "Receive payment from $downlineName";
                        }
                        else{
                            $subject[] = "Payout to $downlineName";
                        }

                        $transAmount[] = $value["amount"];
                    }
                }
                $output["id"] = $id;
                $output["date"] = $transDate;
                $output["subject"] = $subject;
                $output["payout"] = $transAmount;
                $data["paymentList"] = $output;
                $data["balance"] = $balance;
            }
            else{
                return array("status"=>"error","code"=>1,"statusMsg"=>"No payment found.","data"=>"");
            }

            return array("status"=>"ok", "code"=>"0","statusMsg"=>"Add Payment successfull.","data"=>$data);
        }

        private function insertCreditTransaction($accountID, $receiverID, $type, $amount, $subject, $belongID, $referenceID, $transactionDate, $batchID, $clientID, $remark)
        {
            $db = $this->db;
            $general = $this->general;
            
            $creatorID = $this->creatorID ? $this->creatorID:0;
            $creatorType = $this->creatorType ? $this->creatorType:'System';

            $fields = array("id", "subject", "type", "from_id", "to_id", "client_id", "amount", "remark", "belong_id", "reference_id", "batch_id", "deleted", "creator_id", "creator_type", "created_at");

            $values = array($db->getNewID(), $subject, $type, $accountID, $receiverID, $clientID, $amount, $remark, $belongID, $referenceID, $batchID, "0", $creatorID, $creatorType, $transactionDate);

            $arrayData = array_combine($fields,$values);
            
            $result = $db->insert("credit_transaction",$arrayData);
            if($result)
                return true;
            
            return false;
        }

        public function insertXunTransaction($params) {

            $insertTx = array(
                "business_id" => trim($params['businessID']),
                "sender_address" => trim($params['senderAddress']),
                "recipient_address" => trim($params['recipientAddress']),
                "amount" => trim($params['amount']),
                "amount_satoshi" => trim($params['amountSatoshi']),
                "wallet_type" => trim($params['walletType']),
                "credit" => trim($params['credit']),
                "debit" => trim($params['debit']),
                "transaction_type" => trim($params['transactionType']),
                "reference_id" => trim($params['referenceID']),
                "created_at" => trim($params['transactionDate']),
            );

            $id = $this->db->insert('xun_payment_gateway_invoice_transaction', $insertTx);

            return $id;
        }

        public function closeNuxPayClientAccount($userID, $closingDate, $previousBalance=0, $creditType, $addressArr ) {
            global $general, $xunCrypto;
            $db = $this->db;
            $setting = $this->setting;
            $log = $this->log;
            
            // $decimalPlaces = $setting->getSystemDecimalPlaces();
            $decimalPlaces = 8;
            
            // Create the xun_acc_credit daily table if not exists
            // $db->rawQuery("CREATE TABLE IF NOT EXISTS xun_acc_credit_".date("Ymd", strtotime($closingDate))." LIKE xun_acc_credit");
            $db->rawQuery("CREATE TABLE IF NOT EXISTS xun_payment_transaction_history_".date("Ymd", strtotime($closingDate))." LIKE xun_payment_transaction_history");
            
            $db->where('gateway_type', 'BC');
            $db->where('sender_address', $addressArr, 'IN');
            $db->where('wallet_type', $creditType);
            // $db->where('status', array('success', 'completed'), 'in');
            $db->where('status', 'failed', '!=');
            $debitResult = $db->getOne('xun_payment_transaction_history_'.date("Ymd", strtotime($closingDate)), 'SUM(amount) AS debit');

            $log->write(date("Y-m-d H:i:s")." Debit Last query: ".$db->getLastQuery()."\n");

            if($debitResult){
                $db->where('gateway_type', 'BC');
                $db->where('sender_address', $addressArr, 'IN');
                $db->where('fee_wallet_type', $creditType);
                // $db->where('status', array('success', 'completed'), 'in');
                $db->where('status', 'failed', '!=');
                $feeResult = $db->getOne('xun_payment_transaction_history_'.date("Ymd", strtotime($closingDate)),'SUM(fee_amount) AS fee');    

                $log->write(date("Y-m-d H:i:s")." Fee Last query: ".$db->getLastQuery()."\n");
                
            }
           
            $db->where('gateway_type', 'BC');
            $db->where('recipient_address', $addressArr, 'IN');
            $db->where('wallet_type', $creditType);
            // $db->where('status', array('success', 'completed'), 'in');
            $db->where('status', 'failed', '!=');
            $creditResult = $db->getOne('xun_payment_transaction_history_'.date("Ymd", strtotime($closingDate)), 'SUM(amount) AS credit');
            
            $db->where('gateway_type', 'PG');
            $db->where('transaction_type', 'auto_fund_out');
            $db->where('recipient_address', $addressArr, 'IN');
            $db->where('wallet_type', $creditType);
            $db->where('status', 'failed', '!=');
            // $db->where('recipient_address', $addressArr, 'IN');
            // $db->where('deleted', 0);
            $creditResult2 = $db->getOne('xun_payment_transaction_history_'.date("Ymd", strtotime($closingDate)), 'SUM(amount) AS credit');


            $log->write(date("Y-m-d H:i:s")." Credit Last query: ".$db->getLastQuery()."\n");
            
            $credit1 = $creditResult['credit'] ? $creditResult['credit'] : 0;
            $credit2 = $creditResult2['credit'] ? $creditResult2['credit'] : 0;
            $debit = $debitResult['debit'] ? $debitResult['debit'] : 0;
            $fee = $feeResult['fee'] ? $feeResult['fee'] : 0;

            $credit = bcadd($credit1, $credit2, $decimalPlaces);
            $total = bcsub($credit, $debit, $decimalPlaces);
            $total = bcsub($total, $fee, $decimalPlaces);
            $balance = bcadd($previousBalance, $total, $decimalPlaces);
            $log->write(date("Y-m-d H:i:s")." PreviousBalance: $previousBalance, Debit: $debit, Credit: $credit, Fee: $fee, Total: $total, Balance: $balance\n");

            if($balance < '0'){
                $notification_message = "User ID: ".$userID."\n";
                $notification_message .= "Wallet Type:".$creditType."\n";
                $notification_message .= "Previous Balance:".$previousBalance."\n";
                $notification_message .= "Total:".$total."\n";
                $notification_message .= "Balance:".$balance."\n";
              
                $notification_tag = "Negative Balance";
                $general->send_thenux_notification(array('tag'=>$notification_tag, 'message'=>$notification_message), "thenux_issues");

                $log->write(date("Y-m-d H:i:s")." NEGATIVE BALANCE USER: $userID , Wallet Type: $creditType , PreviousBalance: $previousBalance, Debit: $debit, Credit: $credit, Total: $total, Balance: $balance\n");          
            }

            // $wallet_info_data = $xunCrypto->get_wallet_info($addressArr[0], $creditType);
            // $bc_balance = $wallet_info_data[$creditType]["balance"];
            // $unit_conversion = $wallet_info_data[$creditType]["unitConversion"];

            $balance_data = $xunCrypto->get_live_internal_balance($addressArr[0], $creditType, '', $closingDate);
            $bc_balance_decimal = $balance_data['finalBalance'];
            $converted_bc_balance = bcmul($bc_balance_decimal, 1, 8);


            if($converted_bc_balance != $balance){
                $notification_message = "User ID: ".$userID."\n";
                $notification_message .= "Wallet Type:".$creditType."\n";
                $notification_message .= "Previous Balance:".$previousBalance."\n";
                $notification_message .= "Total:".$total."\n";
                $notification_message .= "Balance:".$balance."\n";
                $notification_message .= "BC Balance:".$bc_balance_decimal."\n";
              
                $notification_tag = "Balance not tally";
                $general->send_thenux_notification(array('tag'=>$notification_tag, 'message'=>$notification_message), "thenux_wallet_transaction");

                $log->write(date("Y-m-d H:i:s")." BALANCE NOT TALLY USER: $userID , Wallet Type: $creditType , PreviousBalance: $previousBalance, Debit: $debit, Credit: $credit, Total: $total, Balance: $balance, BC Balance: $bc_balance_decimal\n");          
            }

            // Insert client's closing record into xun_acc_closing
            $arrayData = array(
                "user_id" => $userID,
                "type" => $creditType,
                "date" => $closingDate,
                "total" => $total,
                "balance" => $balance,
                "created_at" => date("Y-m-d H:i:s")
            );
            $db->insert('xun_acc_closing', $arrayData);
            
            return $balance; // Return the latest balance
        }

        public function closeNuxPayWithholdingClientAccount($userID, $closingDate, $previousBalance=0, $creditType ) {
            global $general, $xunCrypto;
            $db = $this->db;
            $setting = $this->setting;
            $log = $this->log;

            // $decimalPlaces = $setting->getSystemDecimalPlaces();
            $decimalPlaces = 8;
            
            $wallet_type = str_replace('Withholding', '', $creditType);

            // Create the xun_acc_credit daily table if not exists
            // $db->rawQuery("CREATE TABLE IF NOT EXISTS xun_acc_credit_".date("Ymd", strtotime($closingDate))." LIKE xun_acc_credit");
            $db->rawQuery("CREATE TABLE IF NOT EXISTS xun_payment_transaction_history_".date("Ymd", strtotime($closingDate))." LIKE xun_payment_transaction_history");
            
            $db->where('gateway_type', 'PG');
            $db->where('wallet_type', $wallet_type);
            $db->where('transaction_type', 'auto_fund_out', '!=');
            // $db->where('status', array('success', 'completed', 'received'), 'in');
            $db->where('status', 'failed', '!=');
            $db->where('sender_user_id', $userID);
            $debitResult = $db->getOne('xun_payment_transaction_history_'.date("Ymd", strtotime($closingDate)), 'SUM(amount) AS debit');

            $db->where('gateway_type', 'PG');
            $db->where('wallet_type', $wallet_type);
            $db->where('transaction_type', 'auto_fund_out', '!=');
            // $db->where('status', array('success', 'completed', 'received'), 'in');
            $db->where('status', 'failed', '!=');
            $db->where('recipient_user_id', $userID);
            $creditResult = $db->getOne('xun_payment_transaction_history_'.date("Ymd", strtotime($closingDate)), 'SUM(amount) AS credit');
            
            $PG_address = $db->subQuery();
            $PG_address->where("s.business_id",$userID);
            $PG_address->where("s.status", 1);
            $PG_address->where("c.status", 1);
            $PG_address->where("s.type", $wallet_type);
            $PG_address->join("xun_crypto_wallet s", "s.id=c.wallet_id", "INNER");
            $PG_address->getValue('xun_crypto_address c','c.crypto_address',null);

            $db->where('gateway_type', 'PG');
            $db->where('wallet_type', $wallet_type);
            $db->where('transaction_type', 'auto_fund_out');
            $db->where('recipient_address', $PG_address, 'IN');
            $db->where('status', 'failed', '!=');
            $creditResult2 = $db->getOne('xun_payment_transaction_history_'.date("Ymd", strtotime($closingDate)), 'SUM(amount) AS credit');
            
            $log->write(date("Y-m-d H:i:s")." Last query: ".$db->getLastQuery()."\n");
            
            $credit1 = $creditResult['credit'] ? $creditResult['credit'] : 0;
            $credit2 = $creditResult2['credit'] ? $creditResult2['credit'] : 0;
            $debit = $debitResult['debit'] ? $debitResult['debit'] : 0;

            $credit = bcadd($credit1, $credit2, $decimalPlaces);
            $total = bcsub($credit, $debit, $decimalPlaces);
            $balance = bcadd($previousBalance, $total, $decimalPlaces);
            $log->write(date("Y-m-d H:i:s")." PreviousBalance: $previousBalance, Debit: $debit, Credit: $credit, Total: $total, Balance: $balance\n");

            if($balance < '0'){
                $notification_message = "User ID: ".$userID."\n";
                $notification_message .= "Wallet Type:".$creditType."\n";
                $notification_message .= "Previous Balance:".$previousBalance."\n";
                $notification_message .= "Total:".$total."\n";
                $notification_message .= "Balance:".$balance."\n";
              
                $notification_tag = "Negative Balance";
                $general->send_thenux_notification(array('tag'=>$notification_tag, 'message'=>$notification_message), "thenux_issues");

                $log->write(date("Y-m-d H:i:s")." NEGATIVE BALANCE USER: $userID , Wallet Type: $creditType , PreviousBalance: $previousBalance, Debit: $debit, Credit: $credit, Total: $total, Balance: $balance\n");          
            }

            // $wallet_info_data = $xunCrypto->get_wallet_info($addressArr[0], $wallet_typ);
            
            // $balance = $wallet_info_data["balance"];
            // $unit_conversion = $wallet_info_data["unitConversion"];

            // $bc_balance_decimal = bcdiv($balance, $unit_conversion, 8);

            // if($bc_balance_decimal != $balance){
            //     $notification_message = "User ID: ".$userID."\n";
            //     $notification_message .= "Wallet Type:".$creditType."\n";
            //     $notification_message .= "Previous Balance:".$previousBalance."\n";
            //     $notification_message .= "Total:".$total."\n";
            //     $notification_message .= "Balance:".$balance."\n";
            //     $notification_message .= "BC Balance:".$bc_balance_decimal."\n";
              
            //     $notification_tag = "Balance not tally";
            //     $general->send_thenux_notification(array('tag'=>$notification_tag, 'message'=>$notification_message), "thenux_wallet_transaction");

            //     $log->write(date("Y-m-d H:i:s")." BALANCE NOT TALLY USER: $userID , Wallet Type: $creditType , PreviousBalance: $previousBalance, Debit: $debit, Credit: $credit, Total: $total, Balance: $balance, BC Balance: $bc_balance_decimal\n");          
            // }

            // Insert client's closing record into xun_acc_closing
            $arrayData = array(
                "user_id" => $userID,
                "type" => $creditType,
                "date" => $closingDate,
                "total" => $total,
                "balance" => $balance,
                "created_at" => date("Y-m-d H:i:s")
            );
            $db->insert('xun_acc_closing', $arrayData);
            
            return $balance; // Return the latest balance
        }

         // Get balance from acc_closing & acc_credit_%
         public function getWithholdBalance($userID, $type, $date='') {
            global $xunCrypto;
            $db = $this->db;
            $setting = $this->setting;
            
            // $decimalPlaces = $setting->getSystemDecimalPlaces();
            $decimalPlaces = 8;
            // $db->where('user_id', $userID);
            // $db->where('address_type', 'nuxpay_wallet');
            // $internal_address = $db->getValue('xun_crypto_user_address','address');
            
            // $external_address = $xunCrypto->get_external_address($internal_address, $type);
            
            $db->where('user_id', $userID);
            $db->where('type', $type);
            if ($date) {
                // If date is passed in as argument, we only want to select the range up till the given date
                $db->where('date', $date, '<=');
                $tsCondition = strtotime($date);
            }
            $count = $db->getValue('xun_acc_closing', 'count(id)');

            // 0 means no rows exist in the acc_closing for this client
            if($count == 0) {
                $balance = 0;
                $latestDate = '';
            }
            else {
                
                // Get the latest acc_closing date for this client
                $db->where('user_id', $userID);
                $db->where('type', $type);
                $db->orderBy('date', "DESC");
                $accClosingResults = $db->getOne('xun_acc_closing', null, 'balance, date');
                
                $latestDate = $accClosingResults["date"];
                $balance = $accClosingResults["balance"];
                
            }
            
            $tsLatest = strtotime($latestDate);
            $totalCredit = 0;
            $totalDebit = 0;
            
            // Get all acc_credit_% tables
            $result = $db->rawQuery('SHOW TABLES LIKE "xun_payment_transaction_history%"');
            
            $wallet_type = str_replace('Withholding', '', $type);

            foreach ($result as $array) {
                foreach ($array as $key=>$val) {
                    $val = explode('_', $val);
                    $dateCredit = end($val);
                    $tsCredit = strtotime($dateCredit);
                    // Compare the date with the latest acc_closing date
                    // For eg. there exist tables
                    // acc_credit_20170801, acc_credit_20170802, acc_credit_20170803, acc_credit_20170804
                    // Condition 1: If acc_closing on 20170802,
                    // This 'if' part will sum up acc_credit_20170803 and acc_credit_20170804 debit & credit
                    // Condition 2: If acc_closing on 20170804,
                    // This 'if' part won't run
                    if($tsCredit > $tsLatest) {
                        if ($tsCondition && $tsCredit > $tsCondition) {
                            // If it exceeds the time of the date argument, breka from the loop
                            break;
                        }

                        $addressArr = array($internal_address, $external_address);
                        // $db->where('account_id', $userID);
                        $db->where('gateway_type', 'PG');
                        $db->where('transaction_type', 'fund_in');
                        $db->where('recipient_user_id', $userID);
                        $db->where('wallet_type', $wallet_type);
                        $db->where('status', 'failed', '!=');
                        // $db->where('recipient_address', $addressArr, 'IN');
                        // $db->where('deleted', 0);
                        $creditRes = $db->getOne('xun_payment_transaction_history_'.$dateCredit, 'SUM(amount) as credit');

                        $db->where('gateway_type', 'PG');
                        $db->where('transaction_type', 'fund_out');
                        $db->where('sender_user_id', $userID);
                        $db->where('wallet_type', $wallet_type);
                        $db->where('status', 'failed', '!=');
                        // $db->where('sender_address', $addressArr, 'IN');
                        $debitRes = $db->getOne('xun_payment_transaction_history_'.$dateCredit, 'SUM(amount) as debit');
                        $totalCredit =  bcadd($totalCredit, $creditRes['credit'], $decimalPlaces);
                        $totalDebit = bcadd($totalDebit, $debitRes['debit'], $decimalPlaces);
                        // $totalDebit += $debitRes['debit'];
                    }
                }
            }
            
            $balance = bcadd($balance, bcsub($totalCredit, $totalDebit, $decimalPlaces), $decimalPlaces);
            // $balance = number_format(($balance + $totalCredit - $totalDebit), $decimalPlaces, '.', '');
            
            // //  update to client_setting table
            // if ($updateCache && !$date) {
            //     // Update cache balance for the clientID
            //     $this->updateClientCacheBalance($userID, $type, $balance);
            // }
            
            return $balance;
        }

	}

?>
