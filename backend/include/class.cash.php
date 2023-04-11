<?php

    /**
     * Cash Class:
     * Used for retrieving and calculating client's credit balance in the system
     */
    
    class Cash
    {
        
        //Commented on 15/11/2017 - removed last param
        //function __construct($db, $setting, $message, $provider, $log) {
        function __construct($db, $setting, $message, $provider) {
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

        public function insertTAccount($accountID, $receiverID, $type, $amount, $subject, $belongID, $referenceID, $transactionDate, $batchID, $clientID, $remark="") {
			$db = $this->db;
            $setting = $this->setting;
            
            // $accountID - From
            // $receiverID - To
            // $type - name of currency
            // $amount
            // $subject - transaction subject
            // $belongID - to link to another account besides the credit and debit ID
            // $referenceID - additional ID to keep track when needed.
            // $transactionDate - enter in this format --> date("Y-m-d H:i:s")
            // $batchID - an ID when perform a task so that we can remove or edit in a batch when needed
            // $remark - Remark for credit_transaction
            
            $db->where("type",$type);
            $db->where("name","allowNegativeBalance");
            $allowNegativeBalanceFlag = $db->getValue("credit_setting", "value");
            
            if(!trim($transactionDate)) {
                $transactionDate = date("Y-m-d H:i:s");
			}
            $tblDate = date('Ymd', strtotime($transactionDate));

			$result = $db->rawQuery("CREATE TABLE IF NOT EXISTS acc_credit_".$db->escape($tblDate)." LIKE acc_credit");
            
            // Check for negative amount
            if ($amount < 0) {
                // Send message to Xun
                $find = array("%%subject%%", "%%errorMessage%%", "%%clientID%%", "%%creditType%%", "%%amount%%", "%%currentBalance%%");
                $replace = array($subject, 'This transaction amount is negative.', $clientID, $type, $amount, "");
                $message->createMessageOut('90006', NULL, NULL, $find, $replace);

				return false;
			}
            
            $decimalPlaces = $setting->getSystemDecimalPlaces();
            
            // Format amount to according to decimal places
            $amount = number_format($amount, $decimalPlaces, ".", "");

            // Check whether accountID is an internal account, and what type of account it is
            // Expenses (Allow negative balance)
            // Suspense (Intermediate accounts)
            // Earnings (Always positive balance)
            $db->where('id', $accountID);
            $accountData = $db->getOne("client", "type, description");
            
            // Get balance from acc_closing & acc_credit_%
            $accountBalance = $this->getBalance($accountID, $type, "", false);
            
            if($allowNegativeBalanceFlag)
            {
                $accountBalance -= $amount; // Debit - minus
            }else{
                if ($accountData['type'] == "Internal" && in_array($accountData['description'], array("Expenses"))) {
                    // Do nothing here
                }
                else {
                    // Check if balance is negative after deducting amount
                    $accountBalance -= $amount; // Debit - minus
                    if($accountBalance < 0) {
                        // Send message to Xun
                        $find = array("%%subject%%", "%%errorMessage%%", "%%clientID%%", "%%creditType%%", "%%amount%%", "%%currentBalance%%");
                        $replace = array($subject, 'Sender balance is insufficient to perform this transaction.', $clientID, $type, $amount, 'Current Balance : '.$currBalance);
                        $message->createMessageOut('90006', NULL, NULL, $find, $replace);

                        return false;
                    }    
                }
            }
            
            // Set fields for acc_credit table
            $fields = array("id", "subject", "type", "account_id", "receiver_id", "credit", "debit", "balance", "belong_id", "reference_id", "batch_id", "deleted", "created_at");
            
            $values = array($subject, $type, $accountID, $receiverID, 0, $amount, $accountBalance, $belongID, $referenceID, $batchID, 0, $transactionDate);
            $arrayData = array_combine($fields, $values);
            $debitID = $db->insert("acc_credit_".$tblDate, $arrayData);
            if(!$debitID)
                return false;
            
            if ($accountData['type'] == "Internal" && in_array($accountData['description'], array("Expenses"))) {
                // Update cache balance for the account that debit
                $this->updateClientCacheBalance($accountID, $type, $accountBalance);
            }
            else {
                // 2nd checking on balance > 0 after insert debit, pass the flag as false so that it won't update the cache balance first
                $accountBalance = $this->getBalance($accountID, $type, "", false);
                
                // Check if balance is negative after deducting amount
                if($accountBalance < 0 && !$allowNegativeBalanceFlag) {
                    $data = array('deleted' => 1);
                    $db->where('id', $debitID);
                    $result = $db->update('acc_credit_'.$tblDate, $data);

                    // Send message to Xun
                    $find = array("%%subject%%", "%%errorMessage%%", "%%clientID%%", "%%creditType%%", "%%amount%%", "%%currentBalance%%");
                    $replace = array($subject, 'Sender balance is negative after performing this transaction.', $clientID, $type, $amount, 'Current Balance : '.$accountBalance);
                    $message->createMessageOut('90006', NULL, NULL, $find, $replace);

                    return false; // Stop here after updating the debit row in acc_credit
                }
                else {
                    // Update cache balance for the account that debit
                    $this->updateClientCacheBalance($accountID, $type, $accountBalance);
                }
            }

            // Get latest balance and update cache balance
            $receiverBalance = $this->getBalance($receiverID, $type, "", false);

            $receiverBalance += $amount; // Credit - plus
           
            // 1st checking on balance > 0 before insert credit
            //$receiverBalance = $this->getBalance($receiverID, $type);
            //$receiverBalance = $db->escape($receiverBalance);
            //$receiverBalance += $amount; // Credit - plus
            //if($receiverBalance < 0)
            //    return false;
            $values = array($subject, $type, $receiverID, $accountID, $amount, 0, $receiverBalance, $belongID, $referenceID, $batchID, 0, $transactionDate);
            $arrayData = array_combine($fields, $values);
            $creditRes = $db->insert("acc_credit_".$tblDate, $arrayData);
            if(!$creditRes)
                return false;

            // Update cache balance for the account that debit
            $this->updateClientCacheBalance($receiverID, $type, $receiverBalance);

            $creditTransactionRes = $this->insertCreditTransaction($accountID, $receiverID, $type, $amount, $subject, $belongID, $referenceID, $transactionDate, $batchID, $clientID, $remark);
            if(!$creditTransactionRes)
                return false;
            
            return true;
		}
        
        // Get balance from acc_closing & acc_credit_%
        public function getBalance($clientID, $type, $date="", $updateCache=true) {
            $db = $this->db;
            $setting = $this->setting;
            
            $decimalPlaces = $setting->getSystemDecimalPlaces();
            
            $db->where('client_id', $clientID);
            $db->where('type', $type);
            if ($date) {
                // If date is passed in as argument, we only want to select the range up till the given date
                $db->where('date', $date, '<=');
                $tsCondition = strtotime($date);
            }
            $count = $db->getValue('acc_closing', 'count(id)');
            
            // 0 means no rows exist in the acc_closing for this client
            if($count == 0) {
                $balance = 0;
                $latestDate = '';
            }
            else {
                
                // Get the latest acc_closing date for this client
                $db->where('client_id', $clientID);
                $db->where('type', $type);
                $db->orderBy('date', "DESC");
                $accClosingResults = $db->getOne('acc_closing', null, 'balance, date');
                
                $latestDate = $accClosingResults["date"];
                $balance = $accClosingResults["balance"];
                
            }
            
            $tsLatest = strtotime($latestDate);
            $totalCredit = 0;
            $totalDebit = 0;
            
            // Get all acc_credit_% tables
            $result = $db->rawQuery('SHOW TABLES LIKE "acc_credit_%"');
            foreach ($result as $array) {
                foreach ($array as $key=>$val) {
                    $val = explode('_', $val);
                    $dateCredit = $val[2];
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
                        $db->where('account_id', $clientID);
                        $db->where('type', $type);
                        $db->where('deleted', 0);
                        $creditRes = $db->getOne('acc_credit_'.$dateCredit, 'SUM(credit) AS credit, SUM(debit) AS debit');
                        $totalCredit += $creditRes['credit'];
                        $totalDebit += $creditRes['debit'];
                    }
                }
            }
            
            $balance = number_format(($balance + $totalCredit - $totalDebit), $decimalPlaces, '.', '');
            
            if ($updateCache && !$date) {
                // Update cache balance for the clientID
                $this->updateClientCacheBalance($clientID, $type, $balance);
            }
            
            return $balance;
        }
        
        public function getClientCacheBalance($clientID, $creditType) {
            $db = $this->db;
            $setting = $this->setting;
            
            $decimalPlaces = $setting->getSystemDecimalPlaces();
            
            $db->where('client_id', $clientID);
            $db->where('name', $creditType);
            $db->where('type', 'Credit Balance');
            $result = $db->getOne('client_setting', 'value');
            
            return $result['value']? number_format($result['value'], $decimalPlaces, '.', '') : 0;
        }
        
         public function updateClientCacheBalance($clientID, $creditType, $balance) {
            $db = $this->db;
            
            $db->where('client_id', $clientID);
            $db->where('name', $creditType);
            $db->where('type', 'Credit Balance');
            $count = $db->getValue("client_setting", "count(client_id)");
            
            if ($count == 0) {
                // Insert new record
                $fields = array('name', 'value', 'type', 'reference', 'client_id');
                $values = array($creditType, $balance, 'Credit Balance', '', $clientID);
                //$values = array($rowID, $creditType, $balance, 'Credit Balance', '', $clientID);
                $arrayData = array_combine($fields, $values);
                $db->insert("client_setting", $arrayData);
            }
            else {
                $data = array('value' => $balance);
                $db->where('client_id', $clientID);
                $db->where('name', $creditType);
                $db->where('type', 'Credit Balance');
                $db->update("client_setting", $data);
            }

        }
        
        /**
         * Accountings closing function
         * Used for calculating the total day's balance for each client and carry forward to the next date
         */
        public function closing($closingDate) {
            $db = $this->db;
            $log = $this->log;
            $message = $this->message;
            
            $log->write(date("Y-m-d H:i:s")." Deleting closing date $closingDate onwards.\n");
            
            if ($this->deleteClosing($closingDate)) {
                $log->write(date("Y-m-d H:i:s")." Successfully deleted closing from $closingDate onwards.\n");
            }
            
            // Convert to timestamp for comparison
            $closingTimestamp = strtotime($closingDate);
            
            // Select all client accounts and internal accounts
            $clientFields = array('id', 'username', 'DATE(created_at) AS created_at', 'description');
            $clientRes = $db->get('client', null, $clientFields);
            
            foreach ($clientRes as $clientRow) {
                if ($clientRow["description"] == "Expenses") {
                    // Expenses accounts means they will always be negative balance
                    $expensesArray[] = $clientRow["id"];
                }
                $clientArray[] = $clientRow;
            }
            unset($clientRes);
            //print_r($clientArray);
            
            // Select all existing currencies
            $creditRes = $db->get('credit', null, array('name'));
            foreach ($creditRes as $creditRow) {
                
                $creditType = $creditRow["name"];
                
                $log->write(date("Y-m-d H:i:s")." Closing $creditType now.\n");
                
                foreach ($clientArray as $clientRow) {
                    
                    $db->where('client_id', $clientRow["id"]);
                    $db->where('`type`', $creditType);
                    $db->orderBy('`date`', "DESC");
                    $accClosingResults = $db->getOne('acc_closing');
                    
                    $lastClosingDate = $accClosingResults["date"];
                    $lastBalance = $accClosingResults["balance"]? $accClosingResults["balance"] : 0;
                    
                    //echo "Last closing date from DB: $lastClosingDate [".$clientRow["id"]."]\n";
                    
                    if ($lastClosingDate) {
                        // Increment by 1 day from the last closing date
                        $lastClosingDate = date("Y-m-d", strtotime("+1 day", strtotime($lastClosingDate)));
                    }
                    else {
                        // Set to client joined date if did not perform closing previously
                        $lastClosingDate = $clientRow["created_at"];
                    }
                    
                    $log->write(date("Y-m-d H:i:s")." Last closing date for client ".$clientRow["username"]." is $lastClosingDate.\n");
                    
                    // Convert to timestamp for comparison
                    $lastClosingTimestamp = strtotime($lastClosingDate);
                    
                    while ($lastClosingTimestamp <= $closingTimestamp) {
                        
                        $lastBalance = $this->closeClientAccount($clientRow["id"], $clientRow["username"], $lastClosingDate, $lastBalance, $creditType);
                        
                        // Increment by 1 day for next iteration
                        $lastClosingDate = date("Y-m-d", strtotime("+1 day", strtotime($lastClosingDate)));
                        $lastClosingTimestamp = strtotime($lastClosingDate);
                        
                    }
                    
                    // Update client's latest cache balance for current currency
                    $balance = $this->getBalance($clientRow["id"], $creditType);
                    
                    $log->write(date("Y-m-d H:i:s")." Finish closing $creditType for ".$clientRow["username"]."[".$clientRow["id"]."]. Balance: ".$balance."\n");
                    
                }
                
                // Audit the credit type (total issued - total spending = balance on all accounts)
                $db->where('client_id', $expensesArray, 'IN');
                $db->where('type', $creditType);
                $db->where('date', $closingDate);
                $expensesBalance = $db->getValue('acc_closing', 'SUM(balance)');
                
                $db->where('client_id', $expensesArray, 'NOT IN');
                $db->where('type', $creditType);
                $db->where('date', $closingDate);
                $incomeBalance = $db->getValue('acc_closing', 'SUM(balance)');
                
                $companyBalance = $incomeBalance + $expensesBalance;
                
                $log->write(date("Y-m-d H:i:s")." Finish closing for $creditType. Total issued: $incomeBalance + Total spending: $expensesBalance = $companyBalance\n");
                
                if ($companyBalance != 0) {
                    // If company balance is less than 0, means there might be a problem
                    $notTallyArray[] = $creditType." balance is not tally. Amount: $companyBalance\n";
                }
                
            }
            
            if (count($notTallyArray) > 0) {
                $content = "Closing result on $closingDate\n\n";
                $content .= implode("\n\n", $notTallyArray);
                // 10005 => balance not tally
                $message->createMessageOut(90005, $content);
            }
            
        }
        
        private function closeClientAccount($clientID, $clientUsername, $closingDate, $previousBalance=0, $creditType) {
            $db = $this->db;
            $setting = $this->setting;
            $log = $this->log;
            
            $decimalPlaces = $setting->getSystemDecimalPlaces();
            
            // Create the acc_credit daily table if not exists
            $db->rawQuery("CREATE TABLE IF NOT EXISTS acc_credit_".date("Ymd", strtotime($closingDate))." LIKE acc_credit");
            
            $db->where('account_id', $clientID);
            $db->where('type', $creditType);
            $db->where('deleted', 0);
            $accRes = $db->getOne('acc_credit_'.date("Ymd", strtotime($closingDate)), 'SUM(debit) AS debit, SUM(credit) AS credit');
            
            $log->write(date("Y-m-d H:i:s")." Last query: ".$db->getLastQuery()."\n");
            
            $credit = $accRes["credit"]? $accRes["credit"] : 0;
            $debit = $accRes["debit"]? $accRes["debit"] : 0;
            $total = number_format(($credit - $debit), $decimalPlaces, ".", "");
            $balance = number_format(($previousBalance + $total), $decimalPlaces, ".", "");
            
            $log->write(date("Y-m-d H:i:s")." PreviousBalance: $previousBalance, Debit: $debit, Credit: $credit, Total: $total, Balance: $balance\n");
            
            // Insert client's closing record into acc_closing
            $fields = array("id", "client_id", "type", "date", "total", "balance", "created_at");
            $values = array($db->getNewID(), $clientID, $creditType, $closingDate, $total, $balance, date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);
            $db->insert('acc_closing', $arrayData);
            
            return $balance; // Return the latest balance
        }

        public function deleteClosing($closingDate) {
            $db = $this->db;
            $log = $this->log;
            
            $db->where('date', $closingDate, " >= ");
            $db->delete('acc_closing');
            // Optmize the table after deletion
            $db->optimize('acc_closing');
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
	}

?>
