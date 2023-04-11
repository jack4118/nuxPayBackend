<?php
/**
 * Date 19/08/2020
 */

    class XunReseller {
     
        public function __construct($db, $setting, $general, $post) {
            $this->db      = $db;
            $this->setting = $setting;
            $this->general = $general;
            $this->post    = $post;
        }

        public function get_distributor($userID) {

            $db = $this->db;

            $db->where("deleted", 0);
            $db->where("id", $userID);
            $siteAdminDetail = $db->getOne("reseller");

            $arr_distributor = array(0);

            if($siteAdminDetail) {

                if($siteAdminDetail['type']=="siteadmin") {

                    $db->where("deleted", 0);
                    $db->where("source", $siteAdminDetail['source']);
                    $db->where("type", "distributor");
                    $distributorDetail = $db->get("reseller", null, "id");

                    foreach($distributorDetail as $distributor) {
                        $arr_distributor[] = $distributor['id'];
                    }

                } else if($siteAdminDetail['type']=="distributor") {
                    $arr_distributor[] = $userID;
                }
                

            }

            if(count($arr_distributor)==0) {
                $arr_distributor[] = -1;
            }

            return $arr_distributor;
        }

        public function merchant_get_reseller($userID) {

            $db = $this->db;

            $db->where("deleted", 0);
            $db->where("id", $userID);
            $resellerDetail = $db->getOne("reseller", "type, source");
            $userType = $resellerDetail['type'];
            $source = $resellerDetail['source'];

            $arr_reseller = array();

            if($userType=="distributor") {
                $arr_reseller[] = $userID;
                $db->where("deleted", 0);
                $db->where("distributor_id", $userID);
                $db->where("source", $source);
                $db->where("type", "reseller");
                $resellerDetail = $db->get("reseller", null, "id");
                foreach($resellerDetail as $reseller) {
                    $arr_reseller[] = $reseller['id'];
                }

            } else if($userType=="siteadmin") {

                $db->where("deleted", 0);
                $db->where("source", $source);
                $db->where("type", "reseller");
                $resellerDetail = $db->get("reseller", null, "id");
                foreach($resellerDetail as $reseller) {
                    $arr_reseller[] = $reseller['id'];
                }

                $arr_reseller[] = 0;  

            } else if($userType=="reseller") {
                $arr_reseller[] = $userID;
            }
            
            if(count($arr_reseller)==0) {
                $arr_reseller[] = -1;
            }
            return $arr_reseller;
        }


        public function get_reseller($userID) {

            $db = $this->db;

            $db->where("deleted", 0);
            $db->where("id", $userID);
            $resellerDetail = $db->getOne("reseller", "type, source");
            $userType = $resellerDetail['type'];
            $source = $resellerDetail['source'];

            $arr_reseller = array();

            if($userType=="distributor") {

                $db->where("deleted", 0);
                $db->where("distributor_id", $userID);
                $db->where("type", "reseller");
                $resellerDetail = $db->get("reseller", null, "id");

                foreach($resellerDetail as $reseller) {
                    $arr_reseller[] = $reseller['id'];
                }

            } else if($userType=="siteadmin") {

                $db->where("deleted", 0);
                $db->where("source", $source);
                $db->where("type", "reseller");
                $resellerDetail = $db->get("reseller", null, "id");

                foreach($resellerDetail as $reseller) {
                    $arr_reseller[] = $reseller['id'];
                }

                $arr_reseller[] = 0;  

            } else if($userType=="reseller") {
                $arr_reseller[] = $userID;
            }
            
            if(count($arr_reseller)==0) {
                $arr_reseller[] = -1;
            }

            return $arr_reseller;
        }

        public function reseller_nuxpay_latest_transaction_list($params,$userID){            
            global $xunCurrency;
            $db = $this->db;
            $setting = $this->setting;

            $limit = $params['limit'];
            $tx_type = $params['tx_type']; //fund_in/fund_out

            if($limit == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00505') /*Limit is required.*/, 'developer_msg' => 'Limit is required.');
            }

            $arr_reseller_id = $this->get_reseller($userID);

            $db->where('id', $userID);
            $db->where('deleted', 0);
            $reseller_data = $db->getOne('reseller');

            if(!$reseller_data){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00571') /*Reseller not found.*/, 'developer_msg' => 'Reseller not found.');
            }
            
            $source = $reseller_data['source'];

            if($tx_type == 'fund_out'){
                // xun_crypto_fund_out_details a
                $db->where('a.status', 'confirmed'); 
                                    
                // filter xun_user to corresponding reseller  
                $db->where('b.reseller_id', $arr_reseller_id, "IN");                
                $db->where('b.register_site', $source);
                
                $db->orderBy('a.id', 'DESC');
                $db->join('xun_user b', 'a.business_id = b.id', 'LEFT');
                $crypto_fund_out_list = $db->get('xun_crypto_fund_out_details a', array(0, $limit), 'a.business_id, a.wallet_type, a.amount, a.service_charge_amount as service_fee, a.service_charge_wallet_type as tx_fee_wallet_type,  a.status, a.created_at');

                // $db->where('a.status', 'confirmed');
                // $db->where('b.type', 'reseller'); // RESELLER
                // $db->where('b.reseller_id', $userID); // Reseller                
                // $db->orderBy('a.id', 'DESC');
                // $db->join('xun_user b', 'a.business_id = b.business_id', 'LEFT');
                // $crypto_fund_out_list = $db->get('xun_crypto_fund_out_details a', array(0, $limit), 'a.business_id, a.wallet_type, a.amount, a.service_charge_amount as service_fee, a.service_charge_wallet_type as tx_fee_wallet_type,  a.status, a.created_at');
    
                if(!$crypto_fund_out_list){
                    return array('status' => 'ok', 'code' => 0, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
                } 
        
                $cryptocurrency_list = $xunCurrency->get_all_cryptocurrency_list('id, currency_id, symbol');
        
                $business_id_arr = [];
                foreach($crypto_fund_out_list as $key => $value){
                    $amount_receive = $value['amount'];
                    $wallet_type = strtolower($value['wallet_type']);
                    $business_id = $value['business_id'];
                    $tx_fee_wallet_type = strtolower($value['tx_fee_wallet_type']);
                    $symbol = strtoupper($cryptocurrency_list[$wallet_type]['symbol']);
                    $tx_fee_symbol = strtoupper($cryptocurrency_list[$tx_fee_wallet_type]['symbol']);
        
                    $crypto_fund_out_list[$key]['symbol']= $symbol;
                    $crypto_fund_out_list[$key]['wallet_type'] = $wallet_type;
                    $crypto_fund_out_list[$key]['tx_fee_symbol'] = $tx_fee_symbol;
        
                    if(!in_array($business_id, $business_id_arr)){
                        array_push($business_id_arr, $business_id);
                    }
                }
        
                $db->where('id', $business_id_arr, 'IN');
                $xun_user = $db->map('id')->ArrayBuilder()->get('xun_user', null, 'id, nickname, service_charge_rate');
        
                foreach($crypto_fund_out_list as $key => $value){
                    $business_id = $value['business_id'];
                    $business_name = $xun_user[$business_id]['nickname'];
                    $service_charge_rate = $xun_user[$business_id]['service_charge_rate'] ? $xun_user[$business_id]['service_charge_rate']  : '0.2';
         
                    $crypto_fund_out_list[$key]['business_name'] = $business_name;
                    $crypto_fund_out_list[$key]['service_charge_rate'] = $service_charge_rate;
        
                }        
            } else{
                // fund in
                // xun_crypto_history a
                $db->where('a.status','success');
                $db->where('b.register_site', $source);
                // filter xun_user to corresponding reseller  
                $db->where('b.reseller_id', $arr_reseller_id, "IN");
                
                $db->orderBy('a.id','DESC');
                $db->join('xun_user b', 'a.business_id = b.id', 'INNER');
                $crypto_history = $db->get('xun_crypto_history a', array(0, $limit), 'a.business_id, a.wallet_type, a.amount_receive as amount, a.transaction_fee as service_fee, a.tx_fee_wallet_type,  a.status, a.created_at');


                // $db->where('a.status', 'success');
                // $db->where('b.type', 'reseller'); // Reseller
                // $db->where('b.reseller_id', $userID); // Reseller
                // $db->orderBy('a.id', 'DESC');
                // $db->join('xun_user b', 'a.business_id = b.id', 'LEFT');
                // $crypto_history = $db->get('xun_crypto_history a', array(0, $limit), 'a.business_id, a.wallet_type, a.amount_receive as amount, a.transaction_fee as service_fee, a.tx_fee_wallet_type,  a.status, a.created_at');
    
                if(!$crypto_history){
                    return array('status' => 'ok', 'code' => 0, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
                } 
        
                $cryptocurrency_list = $xunCurrency->get_all_cryptocurrency_list('id, currency_id, symbol');
        
                $business_id_arr = [];
                foreach($crypto_history as $key => $value){
                    $amount_receive = $value['amount'];
                    $wallet_type = strtolower($value['wallet_type']);
                    $business_id = $value['business_id'];
                    $tx_fee_wallet_type = strtolower($value['tx_fee_wallet_type']);
                    $symbol = strtoupper($cryptocurrency_list[$wallet_type]['symbol']);
                    $tx_fee_symbol = strtoupper($cryptocurrency_list[$tx_fee_wallet_type]['symbol']);
        
                    $crypto_history[$key]['symbol']= $symbol;
                    $crypto_history[$key]['wallet_type'] = $wallet_type;
                    $crypto_history[$key]['tx_fee_symbol'] = $tx_fee_symbol;
        
                    if(!in_array($business_id, $business_id_arr)){
                        array_push($business_id_arr, $business_id);
                    }
                }
        
                $db->where('id', $business_id_arr, 'IN');                
                $xun_user = $db->map('id')->ArrayBuilder()->get('xun_user', null, 'id, nickname, service_charge_rate');
        
                foreach($crypto_history as $key => $value){
                    $business_id = $value['business_id'];
                    $business_name = $xun_user[$business_id]['nickname'];
                    $service_charge_rate = $xun_user[$business_id]['service_charge_rate'] ? $xun_user[$business_id]['service_charge_rate']  : '0.2';
         
                    $crypto_history[$key]['business_name'] = $business_name;
                    $crypto_history[$key]['service_charge_rate'] = $service_charge_rate;
        
                }        
            }

            if($tx_type == 'fund_out'){
                $data = $crypto_fund_out_list;
            }
            else{
                $data = $crypto_history;
            }
            
            // return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00271') /*Admin NuxPay Latest Transaction List.*/, 'data' => $data);                        
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00298') /*Reseller Latest Transaction List.*/, 'data' => $data);                        
        }                       

        public function reseller_nuxpay_dashboard_statistics($params, $userID){
            global $xunCurrency;
            $db= $this->db;
            $setting = $this->setting;
    
            $to_date           = $params["date_to"];
            $from_date         = $params["date_from"];
    
            $arr_reseller_id = $this->get_reseller($userID);

            if($from_date){
                $from_datetime = date("Y-m-d H:i:s", $from_date);
                $db->where('a.created_at', $from_datetime, '>');                
            }
    
            if($to_date){
                $to_datetime  = date("Y-m-d H:i:s", $to_date);
                $db->where('a.created_at' , $to_datetime, '<=');
            }
    
            $copyDb = $db->copy();
            $copyDb2 = $db->copy();
            

            // RESELLER            
            $db->where('b.reseller_id', $arr_reseller_id, "IN"); // Reseller
            
            //fund in commision;
            $db->orderBy('a.id', 'ASC');
            $db->join('xun_user b', 'a.business_id = b.id', 'INNER');
            $crypto_history = $db->get('xun_crypto_history a', null, 'a.business_id, a.wallet_type, a.amount_receive as amount,a.transaction_fee, a.tx_fee_wallet_type, a.status, a.created_at');
                     
            //fundout commission
            $copyDb->where('a.status', 'confirmed');            
            $copyDb->where('b.reseller_id', $arr_reseller_id, "IN"); // Reseller
            $copyDb->join('xun_user b', 'a.business_id = b.id', 'INNER');            
            $crypto_fund_out_details = $copyDb->get('xun_crypto_fund_out_details a', null, 'a.business_id, a.wallet_type, a.amount, a.service_charge_amount as transaction_fee, a.service_charge_wallet_type as tx_fee_wallet_type, a.status, a.created_at');
    
            $copyDb2->groupBy('a.status');            
            $copyDb2->where('b.reseller_id', $arr_reseller_id, "IN"); // Reseller
            $copyDb2->join('xun_user b', 'a.business_id = b.id', 'INNER');            
            $fund_out_total_transaction = $copyDb2->map('status')->ArrayBuilder()->get('xun_crypto_fund_out_details a', null, 'CONCAT(UPPER(SUBSTRING(a.status,1,1)),LOWER(SUBSTRING(a.status,2))) as status, count(a.id)');
    
            $marketplace_currencies = $xunCurrency->get_cryptocurrency_list('a.id, a.currency_id, a.symbol');
            $crypto_rate = $xunCurrency->get_cryptocurrency_rate_with_stable_coin($marketplace_currencies);
            
            $db->where('id', $userID);
            $distributor = $db->getOne("reseller", "type");

            // CALCULATE TOTAL COMMISSION IN USD
            $db->where('a.reseller_id', $userID);
            $db->join('xun_marketplace_currencies c', 'a.wallet_type = c.currency_id', 'LEFT');
            $db->groupBy('a.wallet_type');
            $commissionDetails = $db->get('xun_marketer_commission_transaction a', null, 'a.wallet_type, SUM(a.credit) as total_credit, SUM(a.debit) as total_debit, SUM(a.credit) - SUM(a.debit) as balance, c.symbol');

            $totalUSD = 0;
            foreach($commissionDetails as $key => $value){                
                if($value['wallet_type']=="tetherUSD"){
                    $walletType = "tetherusd";
                }else{
                    $walletType = $value['wallet_type'];
                }
                $totalUSD +=  bcmul($value['balance'], $crypto_rate[$walletType], 2);
                
            }
            $total_usd = round($totalUSD, 2);

            // LATEST WITHDRAWAL HISTORY
            $lastestWithdrawn = 0;
            $db->where('id', $userID);
            $marketer_id = $db->getValue('reseller', 'marketer_id');
            $db->orderBy('a.created_at', 'DESC');
            $db->where('b.marketer_id', $marketer_id);
            $db->where('a.type', 'Fund Out');
            $where = '(status="wallet_success" or status = "completed")';
            $db->where($where);
            $db->join('xun_business_marketer_commission_scheme b', 'a.business_marketer_commission_id=b.id', 'LEFT');
            $db->join('xun_wallet_transaction c', 'a.reference_id=c.id', 'LEFT');
            $commissionWithdrawals = $db->getOne('xun_marketer_commission_transaction a', 'a.id, a.created_at, a.wallet_type, c.amount');

            if($commissionWithdrawals['wallet_type']=="tetherUSD"){
                $walletType = "tetherusd";
            }else{
                $walletType = $commissionWithdrawals['wallet_type'];
            }
            $lastestWithdrawn =  bcmul($commissionWithdrawals['amount'], $crypto_rate[$walletType], 2);
            $withdrawnDate = $commissionWithdrawals['created_at'];          
            $lastest_withdrawn = round($lastestWithdrawn, 2);   

            $returnTotal = [];
            
            $returnTotal['total_usd'] = $total_usd;
            $returnTotal['latest_withdrawn'] = $lastest_withdrawn;
            $returnTotal['withdrawn_date'] = $withdrawnDate;

            // Total Merchant Query
            $copyDb3 = $db->copy();
            $copyDb3->where('id', $userID);
            $source = $copyDb3->getValue("reseller", "source");

            $arr_reseller_id = $this->merchant_get_reseller($userID);
            $copyDb3->where('a.reseller_id', $arr_reseller_id, "IN");

            $copyDb3->join("reseller r", "(r.id=a.reseller_id AND r.type='reseller')", "LEFT");

            if($reseller) {
                $copyDb3->where("r.username", $reseller);
            }
            $copyDb3->where("a.register_site", $source);
            $copyDb3->where('a.type', 'business');
            $copyDb3->where('a.disable_type', 'deleted', '!=');
            $copyDb3->join('xun_business b', 'b.user_id = a.id', 'LEFT');
            $copyDb3->join('xun_business_account c', 'c.user_id = a.id', 'LEFT');
            $copyDb3->orderBy('a.id', 'DESC');
            $copyDb = $copyDb3->copy();
            $totalMerchant = $copyDb3->getValue('xun_user a', 'count(a.id)');
            // End Of Total Merchant Query

            // Direct Merchant Query
            $copyDb4 = $db->copy();
            $source = $copyDb4->getValue("reseller", "source");
            $copyDb4->where('a.reseller_id', $userID);
            $copyDb4->join("reseller r", "(r.id=a.reseller_id AND r.type='reseller')", "LEFT");
            if($reseller) {
                $copyDb4->where("r.username", $reseller);
            }
            $copyDb4->where("a.register_site", $source);
            $copyDb4->where('a.type', 'business');
            $copyDb4->where('a.disable_type', 'deleted', '!=');
            $copyDb4->join('xun_business b', 'b.user_id = a.id', 'LEFT');
            $copyDb4->join('xun_business_account c', 'c.user_id = a.id', 'LEFT');
            $copyDb4->orderBy('a.id', 'DESC');
            $copyDb = $copyDb4->copy();
            $DirectMerchant = $copyDb4->getValue('xun_user a', 'count(a.id)');
            // End of Direct Merchant Query
            
            if($distributor['type']=='reseller'){
                $db->where('reseller_id', $userID);
                $db->where('type', 'business');
                $totalMerchant = $db->getValue('xun_user', "count(id)");
                $returnTotal['totalMerchant'] = $totalMerchant;
            } else {
                $db->where('r.distributor_id', $userID);
                $db->where('x.type', 'business');
                $db->join('reseller r', 'r.id = x.reseller_id', 'INNER');
                $indirectMerchant = $db->getValue('xun_user x', 'count(x.id)');
                $db->where('distributor_id', $userID);
                $totalReseller = $db->getValue('reseller', 'count(id)');
                $returnTotal['totalMerchant'] = $totalMerchant;
                $returnTotal['directMerchant'] = $DirectMerchant;
                $returnTotal['indirectMerchant'] = $indirectMerchant;
                $returnTotal['totalReseller'] = $totalReseller;
            } 
            $total_profit = 0;
            $total_transaction = 0;
            $total_transacted_amount = 0;
            $business_id_arr = [];
            $wallet_type_arr = [];
            $len = count($crypto_history);
            $end = $len-1;
            $is_all_time = 0;//is all time or filter by days
            $total_fund_in_reseller_id = [];
            $amount_receive_sum =0;
            if(!$crypto_history){
                $from_date = strtotime($distributor['created_at']);

            }
            foreach($crypto_history as $key => $value){
    
                if(!$from_datetime && !$to_datetime){
                    $is_all_time = 1;
                    if($key == 0){
                        $start_created_at = $value['created_at'];
                        $from_date = strtotime($start_created_at);
                    }
        
                    if($key == $end){
                        $end_created_at = $value['created_at'];
                        $to_date = strtotime($end_created_at);
                    }
                }
              
                
                $wallet_type = strtolower($value['tx_fee_wallet_type']); //change wallet_type to tx_fee wallet_type
                $transaction_fee = $value['transaction_fee'];
                $status = ucfirst($value['status']);
                $amount = $value['amount'];
                $business_id = $value['business_id'];
    
                if($status == 'Success'){
    
                    $amount_profit_by_coin = $total_profit_by_coin[$wallet_type]['amount'];
                    $converted_total_profit_usd =  bcmul($transaction_fee, $crypto_rate[$wallet_type], 2);
                    $total_profit = bcadd($total_profit, $converted_total_profit_usd, 2);
    
                    $amount_usd = bcmul($amount, $crypto_rate[$wallet_type], 2);
                    $total_transacted_amount = bcadd($total_transacted_amount, $amount_usd, 2);
    
                    // $amount_receive_sum += $total_transacted_amount;
                    $total_fund_in_reseller_id[$key] = $total_transacted_amount; 
                    if(!$total_profit_by_coin[$wallet_type]){
                        $total_profit_by_coin[$wallet_type]['amount'] = $converted_total_profit_usd;
                    }
                    else{
                        $new_amount_profit = bcadd($amount_profit_by_coin, $converted_total_profit_usd, 2);
                        $total_profit_by_coin[$wallet_type]['amount'] = $new_amount_profit;
                    }
    
    
                    if(!$total_profit_by_merchant[$business_id]){
                        $total_profit_by_merchant[$business_id]['amount'] = $converted_total_profit_usd;
                    }
                    else{
                        $total_profit_amount_by_merchant = $total_profit_by_merchant[$business_id]['amount'];
                        $new_amount_profit = bcadd($total_profit_amount_by_merchant, $converted_total_profit_usd, 2);
                        $total_profit_by_merchant[$business_id]['amount'] = $new_amount_profit;
                    }
    
                
                    if(!$total_transaction_amount_by_coin[$wallet_type]){
                        $total_transaction_amount_by_coin[$wallet_type] = array(
                            "amount" => $amount_usd,
                        );
                    }
                    else{
                        $total_amount_usd = $total_transaction_amount_by_coin[$wallet_type]["amount"];
        
                        $new_total_amount_usd = bcadd($total_amount_usd, $amount_usd, 2);
                        $total_transaction_amount_by_coin_arr = array(
                            "amount" => $new_total_amount_usd,
                        );
        
                        $total_transaction_amount_by_coin[$wallet_type] = $total_transaction_amount_by_coin_arr;
                    }
    
                    if(!in_array($business_id, $business_id_arr)){
                        array_push($business_id_arr, $business_id);
                    }
    
                    if(!in_array($wallet_type, $wallet_type_arr)){
                        array_push($wallet_type_arr, $wallet_type);
                    }
        
                }
    
                if(!$total_transaction_by_status[$status]){
                    $total_transaction_by_status[$status]["amount"] = 1;
                }
                else{
                    $total_transaction_by_status[$status]["amount"]++;
                }
    
            
               $total_transaction++;
            }
            $amount_receive_sum = 0;
            if(!empty($total_fund_in_reseller_id)){
                $db->where('a.reseller_id', $arr_reseller_id, "IN");
                $db->where('a.type', "business");
                $db->where("c.status", "success");
                $db->join("xun_crypto_history c", "c.business_id=a.id", "INNER");
                $db->join('reseller b', "b.id=a.reseller_id", "INNER");
                $fund_in_by_reseller = $db->get("xun_user a", null, 'a.id, a.reseller_id, b.username, c.amount_receive, c.exchange_rate');
                $newArr = array();

                foreach($fund_in_by_reseller as $key => $value){
                    $amount_receive = $value['amount_receive'];
                    $exchange_rate = $value['exchange_rate'];
                    $amount_receive_usd =  bcmul($amount_receive, $exchange_rate, 2);
                    $fund_in_by_reseller[$key]['amount_receive_usd'] = $amount_receive_usd;
                    $amount_receive_sum += $amount_receive_usd;
                    $newArr[$value['reseller_id']][$key] = $value;
                    $newArr[$value['reseller_id']][$key]['amount_receive_usd'] = $amount_receive_usd;
                }
                
                foreach($newArr as $key => $innerArr){
                    $total_amount_receive_usd = 0;
                    $percentage_amount_total_receive = 0;
                    foreach($innerArr as $innerRow => $value){

                        $reseller_id = $value['reseller_id'];
                        $resellerId = $fund_in_by_reseller[$innerRow]['reseller_id'];
                        $total_amount_receive_usd += $value['amount_receive_usd'];
                        $profitAmountOverTotalProfit= bcdiv($value['amount_receive_usd'], $amount_receive_sum, 8);
                        $percentage_amount = bcmul($profitAmountOverTotalProfit, 100, 4);
                        $total_profit_percentage_reseller = bcsub($total_profit_percentage_reseller, $percentage_amount, 4);
                        $percentage_amount_total_receive += $percentage_amount;

                        if($resellerId == $reseller_id){
                                                            
                            $result = array(
                                'reseller_username' => $value['username'],
                                'total_amount_receive_usd'      => $total_amount_receive_usd,
                                'percentage_amount' => $percentage_amount_total_receive

                            );
                            $total_fund_in_by_reseller[$resellerId] = $result;
                        }
                    }
                }

                usort($total_fund_in_by_reseller, function($a, $b) {
                    return $a['total_amount_receive_usd'] < $b['total_amount_receive_usd'];
                });
                
                foreach($total_fund_in_by_reseller as $reseller_key => $reseller_value){
    
                    if($reseller_key > 4){
                        $amount = $reseller_value['total_amount_receive_usd'];
                        $percentage = $reseller_value['percentage_amount'];
                        $others_total_amount_reseller = bcadd($others_total_amount_reseller,$amount, 2 );
                        $profitAmountOverTotalProfit= bcdiv($amount, $amount_receive_sum, 8);
                        $others_total_percentage = bcmul($profitAmountOverTotalProfit, 100, 4);
                        $others_total_percentage_reseller += $others_total_percentage;
                        unset($total_fund_in_by_reseller[$reseller_key]);
    
                        $total_fund_in_by_reseller[5] = array(
                            "reseller_username" => "Others",
                            "total_amount_receive_usd" => $others_total_amount_reseller,
                            "percentage_amount" => $others_total_percentage_reseller
                        );
                    }
                }
    
                if($total_fund_in_by_reseller[5]['total_amount_receive_usd'] <=0){
                    unset($total_fund_in_by_reseller[5]);
                }
        
            }
            foreach($crypto_fund_out_details as $key => $value){
    
                if(!$from_datetime && !$to_datetime){
                    $is_all_time = 1;
                    if($key == 0){
                        $start_created_at = $value['created_at'];
                        $from_date = strtotime($start_created_at);
                    }
        
                    if($key == $end){
                        $end_created_at = $value['created_at'];
                        $to_date = strtotime($end_created_at);
                    }
                }
              
                
                $wallet_type = strtolower($value['wallet_type']); //change wallet_type to tx_fee wallet_type
                $transaction_fee = $value['transaction_fee'];
                $status = $value['status'];
                $amount = $value['amount'];
                $business_id = $value['business_id'];
    
                if($status == 'confirmed'){
    
                    $amount_profit_by_coin = $total_profit_by_coin[$wallet_type]['amount'];
                    $converted_total_profit_usd =  bcmul($transaction_fee, $crypto_rate[$wallet_type], 2);
                    $total_profit = bcadd($total_profit, $converted_total_profit_usd, 2);
                    $amount_usd = bcmul($amount, $crypto_rate[$wallet_type], 2);
           
                    $total_fund_out_transacted_amount = bcadd($total_fund_out_transacted_amount, $amount_usd, 2);
                    if(!$total_profit_by_coin[$wallet_type]){
                        $total_profit_by_coin[$wallet_type]['amount'] = $converted_total_profit_usd;
                    }
                    else{
                        $new_amount_profit = bcadd($amount_profit_by_coin, $converted_total_profit_usd, 2);
                        $total_profit_by_coin[$wallet_type]['amount'] = $new_amount_profit;
                    }
    
    
    
                    if(!$total_profit_by_merchant[$business_id]){
                        $total_profit_by_merchant[$business_id]['amount'] = $converted_total_profit_usd;
                    }
                    else{
                        $total_profit_amount_by_merchant = $total_profit_by_merchant[$business_id]['amount'];
                        $new_amount_profit = bcadd($total_profit_amount_by_merchant, $converted_total_profit_usd, 2);
                        $total_profit_by_merchant[$business_id]['amount'] = $new_amount_profit;
                    }
    
                
                    if(!$total_fund_out_tx_amount_by_coin[$wallet_type]){
                        $total_fund_out_tx_amount_by_coin[$wallet_type] = array(
                            "amount" => $amount_usd,
                        );
    
                    }
                    else{
                        $total_fund_out_amount_usd = $total_fund_out_tx_amount_by_coin[$wallet_type]["amount"];
        
                        $new_total_amount_usd = bcadd($total_fund_out_amount_usd, $amount_usd, 2);
                        $total_fund_out_tx_amount_by_coin_arr = array(
                            "amount" => $new_total_amount_usd,
                        );
        
                        $total_fund_out_tx_amount_by_coin[$wallet_type] = $total_fund_out_tx_amount_by_coin_arr;
                    }
    
                    if(!in_array($business_id, $business_id_arr)){
                        array_push($business_id_arr, $business_id);
                    }
    
                    if(!in_array($wallet_type, $wallet_type_arr)){
                        array_push($wallet_type_arr, $wallet_type);
                    }
        
                }
            }
            
            if($business_id_arr){
                $db->where('id', $business_id_arr, 'IN');                
                $db->where('reseller_id', $arr_reseller_id, "IN"); // RESELLER
                $xun_user = $db->map('id')->ArrayBuilder()->get('xun_user', null, 'id, nickname');
            }
            
            $total_fund_out_transaction = 0;
            foreach($fund_out_total_transaction as $status_key => $status_value){
                $total_fund_out_transaction = bcadd($total_fund_out_transaction, $status_value);
            }
            
            foreach($fund_out_total_transaction as $status_key => $status_value){
    
                $statusAmountOverTotalTransaction = bcdiv($status_value, $total_fund_out_transaction, 8);
                $status_percentage = bcmul($statusAmountOverTotalTransaction, 100, 2);
    
                $total_fund_out_transaction_by_status[$status_key]['amount'] = $status_value;
                $total_fund_out_transaction_by_status[$status_key]['percentage'] = $status_percentage;
                $total_fund_out_transaction_by_status[$status_key]['status']= $status_key;
            }
    
            $total_fund_out_transaction_by_status = array_values($total_fund_out_transaction_by_status);
    
            foreach($total_transaction_by_status as $status_key => $status_value){
    
                $status_amount = $status_value['amount'];
                $statusAmountOverTotalTransaction = bcdiv($status_amount, $total_fund_out_transaction, 8);
                $status_percentage = bcmul($statusAmountOverTotalTransaction, 100, 2);
    
                $total_transaction_by_status[$status_key]['percentage'] = $status_percentage;
                $total_transaction_by_status[$status_key]['status']= $status_key;
            }
    
            $total_transaction_by_status = array_values($total_transaction_by_status);
    
            $total_transaction_percentage = 100;
            foreach($total_fund_out_tx_amount_by_coin as $tx_key => $tx_value){
                $tx_amount = $tx_value["amount"];
                $txAmountOverTotalTransacted = bcdiv($tx_amount, $total_fund_out_transacted_amount, 4);
                $tx_percentage = bcmul($txAmountOverTotalTransacted, 100, 4);
                $symbol = strtoupper($marketplace_currencies[$tx_key]['symbol']);
    
                $total_transaction_percentage = bcsub($total_transaction_percentage, $tx_percentage, 4);
                $total_fund_out_tx_amount_by_coin [$tx_key]['percentage']= $tx_percentage;
                $total_fund_out_tx_amount_by_coin [$tx_key]['symbol']= $symbol;
            }
    
            usort($total_fund_out_tx_amount_by_coin, function($a, $b) {
                return $a['amount'] < $b['amount'];
            });
    
            foreach($total_fund_out_tx_amount_by_coin as $tx_key => $tx_value){
    
                if($tx_key > 4){
                    $amount = $tx_value['amount'];
                    $percentage = $tx_value['percentage'];
                    $others_total_amount = bcadd($others_total_amount,$amount, 2 );
                    // $others_total_percentage = bcadd($others_total_percentage, $percentage, 2);
                    $remaining_percentage = $total_transaction_percentage;
                    unset($total_fund_out_tx_amount_by_coin[$tx_key]);
                    $total_fund_out_tx_amount_by_coin[5] = array(
                        "amount" => $others_total_amount,
                        "percentage" => $remaining_percentage,
                        "symbol" => "Others",
                    );
                }
    
            }
    
            if($total_fund_out_tx_amount_by_coin[5]['amount'] <= 0){
                unset($total_fund_out_tx_amount_by_coin[5]);
            }
    
            foreach($total_transaction_amount_by_coin as $tx_key => $tx_value){
                $tx_amount = $tx_value["amount"];
                $txAmountOverTotalTransacted = bcdiv($tx_amount, $total_transacted_amount, 4);
                $tx_percentage = bcmul($txAmountOverTotalTransacted, 100, 4);
                $symbol = strtoupper($marketplace_currencies[$tx_key]['symbol']);
    
                $total_transaction_percentage = bcsub($total_transaction_percentage, $tx_percentage, 4);
                $total_transaction_amount_by_coin [$tx_key]['percentage']= $tx_percentage;
                $total_transaction_amount_by_coin [$tx_key]['symbol']= $symbol;
            }
    
            usort($total_transaction_amount_by_coin, function($a, $b) {
                return $a['amount'] < $b['amount'];
            });
    
            foreach($total_transaction_amount_by_coin as $tx_key => $tx_value){
    
                if($tx_key > 4){
                    $amount = $tx_value['amount'];
                    $percentage = $tx_value['percentage'];
                    $others_total_amount = bcadd($others_total_amount,$amount, 2 );
                    // $others_total_percentage = bcadd($others_total_percentage, $percentage, 2);
                    $remaining_percentage = $total_transaction_percentage;
                    unset($total_transaction_amount_by_coin[$tx_key]);
                    $total_transaction_amount_by_coin[5] = array(
                        "amount" => $others_total_amount,
                        "percentage" => $remaining_percentage,
                        "symbol" => "Others",
                    );
                }
    
            }
    
            if($total_transaction_amount_by_coin[5]['amount'] <= 0){
                unset($total_transaction_amount_by_coin[5]);
            }
         
            $total_profit_percentage = 100;
            $total_profit_by_merchantID = [];
            foreach($total_profit_by_merchant as $merchant_key => $merchant_value){
                $profit_amount = $merchant_value["amount"];
                $profitAmountOverTotalProfit= bcdiv($profit_amount, $total_profit, 8);
                $profit_percentage = bcmul($profitAmountOverTotalProfit, 100, 4);
                $merchant_name = $xun_user[$merchant_key];
                $total_profit_percentage = bcsub($total_profit_percentage, $profit_percentage, 4);
                $total_profit_by_merchant[$merchant_key]['percentage']= $profit_percentage;
                $total_profit_by_merchant[$merchant_key]['name'] = $merchant_name;
                array_push($total_profit_by_merchantID, $merchant_key);
                
            }

            //GET RESELLER ID
            if(!empty($total_profit_by_merchantID)){
            $db->where('id', $total_profit_by_merchantID, 'IN');
            $reseller_id = $db->map('id')->ArrayBuilder()->get('xun_user', null, 'id, reseller_id');
            
            //GET RESELLER NAME
            $db->where('id', $reseller_id, 'IN');
            $reseller_username = $db->map('id')->ArrayBuilder()->get('reseller', null, 'id, username');
            }

            $sum = 0;
            $new_arr = array();
            foreach($total_profit_by_merchant as $merchant_key => $merchant_value){
  
                $merchant_reseller_id = $reseller_id[$merchant_key];
                $total_profit_by_merchant[$merchant_key]['reseller_id'] = $merchant_reseller_id;
                if($merchant_reseller_id){
                $sum += $total_profit_by_merchant[$merchant_key]['amount'];
                $new_arr[$merchant_reseller_id][$merchant_key] = $merchant_value;
                $new_arr[$merchant_reseller_id][$merchant_key]['reseller_id'] = $merchant_reseller_id;
                }
            }
            $result = [];
            
            $total_percentage = 0;
            $total_profit_percentage_reseller = 100;
            foreach($new_arr as $arr_key => $innerArr){
                $total_amount = 0;
                $percentage_amount_total = 0;
                foreach($innerArr as $innerRow => $value){
                    
                    $resellerID = $value['reseller_id'];
                    $merchant_reseller_id = $reseller_id[$innerRow];
                    $merchant_reseller_username = $reseller_username[$merchant_reseller_id];
                    $total_amount += $value['amount'];
                    $profitAmountOverTotalProfit= bcdiv($value['amount'], $sum, 8);
                    $percentage_amount = bcmul($profitAmountOverTotalProfit, 100, 4);
                    $total_profit_percentage_reseller = bcsub($total_profit_percentage_reseller, $percentage_amount, 4);
                    $percentage_amount_total += $percentage_amount;        
                    
                    if($resellerID == $merchant_reseller_id){
                        $result = array(
                            'reseller_username' => $merchant_reseller_username,
                            'total_amount'      => $total_amount,
                            'percentage_amount' => $percentage_amount_total

                        );
                            $total_profit_by_reseller[$merchant_reseller_id] = $result;
                    }         
                }
            }
            usort($total_profit_by_reseller, function($a, $b) {
                return $a['total_amount'] < $b['total_amount'];
            });
            $others_total_percentage_reseller=0;
            $others_total_amount_profit_reseller=0;
            foreach($total_profit_by_reseller as $reseller_key => $reseller_value){
    
                if($reseller_key > 4){
                    $amount = $reseller_value['total_amount'];
                    $percentage = $reseller_value['percentage_amount'];
                    $others_total_amount_profit_reseller = bcadd($others_total_amount_profit_reseller,$amount, 2 );
                    $profitAmountOverTotalProfit= bcdiv($amount, $sum, 8);
                    $others_total_percentage = bcmul($profitAmountOverTotalProfit, 100, 4);
                    $others_total_percentage_reseller += $others_total_percentage;
                    unset($total_profit_by_reseller[$reseller_key]);

                    $total_profit_by_reseller[5] = array(
                        "reseller_username" => "Others",
                        "total_amount" => $others_total_amount_profit_reseller,
                        "percentage_amount" => $others_total_percentage_reseller
                    );
                }
            }

            if($total_profit_by_reseller[5]['total_amount'] <=0){
                unset($total_profit_by_reseller[5]);
            }
    
            usort($total_profit_by_merchant, function($a, $b) {
                return $a['amount'] < $b['amount'];
            });
            foreach($total_profit_by_merchant as $merchant_key => $merchant_value){
    
                if($merchant_key > 4){
                    $amount = $merchant_value['amount'];
                    $percentage = $merchant_value['percentage'];
                    $others_total_amount = bcadd($others_total_amount,$amount, 2 );
                    $others_total_percentage = $total_profit_percentage;
                    unset($total_profit_by_merchant[$merchant_key]);
                    $total_profit_by_merchant[5] = array(
                        "amount" => $others_total_amount,
                        "percentage" => $others_total_percentage,
                        "name" => "Others",
                    );
                }
            }
    
            if($total_profit_by_merchant[5]['amount'] <=0){
                unset($total_profit_by_merchant[5]);
            }
    
            $total_profit_by_coin_percentage = 100;
            foreach($total_profit_by_coin as $profit_key => $profit_value){
                $symbol = strtoupper($marketplace_currencies[$profit_key]['symbol']);
                $amount = $profit_value['amount'];
                $profitOverTotalProfit = bcdiv($amount, $total_profit, 4);
                $percentage = bcmul($profitOverTotalProfit, 100, 4);
                $total_profit_by_coin_percentage = bcsub($total_profit_by_coin_percentage, $percentage, 4);
                $total_profit_by_coin[$profit_key]['percentage'] = $percentage;
                $total_profit_by_coin[$profit_key]['symbol'] = $symbol;
                
            }
    
            usort($total_profit_by_coin, function($a, $b) {
                return $a['amount'] < $b['amount'];
            });
    
            $others_total_amount = 0;
            $others_total_percentage = 0;
            foreach($total_profit_by_coin as $profit_key => $profit_value){
                if($profit_key > 4){
                    $amount = $profit_value['amount'];
                    $percentage = $profit_value['percentage'];
                    $others_total_amount = bcadd($others_total_amount,$amount, 2 );
                    $others_total_percentage = $total_profit_by_coin_percentage;
                    unset($total_profit_by_coin[$profit_key]);
                    $total_profit_by_coin[5] = array(
                        "amount" => $others_total_amount,
                        "percentage" => $others_total_percentage,
                        "symbol" => "Others",
                    );      
                }
            }
    
            if($total_profit_by_coin[5]['amount'] <= 0){
                unset($total_profit_by_coin[5]);
            }
    
            if($is_all_time == 1){
    
                $dateFrom = date("Y-m-d 00:00:00", $from_date);
                $dateTo = date("Y-m-d 00:00:00", $to_date);
                
                $d1 = strtotime($dateFrom);
                $d2 = strtotime($dateTo);
    
                $diff = $d2 - $d1;
        
                $days = $diff / (60 * 60 * 24); //get the difference in days
    
                $chart_data = [];
                $profit_list = [];
    
                //loop the days and push each day into the date arr
                for($i = 0; $i <= $days; $i++){
                    if($i == 0){
                        $date_time = $dateFrom;
                    }
                    else{
    
                        $date_time = date('Y-m-d 00:00:00', strtotime('+1 days', strtotime($date_time)));
                    }
                
                    $profit_arr = array(
                        "date" => $date_time,
                        "value" => strval(0)
                    );
    
                    $profit_list[$date_time] = $profit_arr;
                }
    
            }
            else{
                $dateFrom = date("Y-m-d H:00:00", $from_date);
                $dateTo = date("Y-m-d H:00:00", $to_date);
    
                // echo "hello testing".$dateFrom.$dateTo." \n";
                $d1 = strtotime($from_datetime);
                $d2 = strtotime($to_datetime);
    
                
                $diff = $d2 - $d1;
                $hours = $diff / (60 *60); //get the difference in hours
    
                $chart_data = [];
                $profit_list = [];
    
                //loop the hours and push each hour into the date arr
                for($i = 0; $i <= $hours; $i++){
                    if($i == 0){
                        $date_time = $dateFrom;
                        // echo "hello3".$date_time ."\n";
                    }
                    else{
    
                        $date_time = date('Y-m-d H:00:00', strtotime('+1 hour', strtotime($date_time)));
                    }
                
                    $profit_arr = array(
                        "date" => $date_time,
                        "value" => strval(0)
                    );
    
                    $profit_list[$date_time] = $profit_arr;
                }
            }
    
           
            foreach($crypto_history as $crypto_key => $crypto_value){
                $created_at = $crypto_value['created_at'];
                
                $transaction_fee = $crypto_value["transaction_fee"];
                $wallet_type = $crypto_value['wallet_type'];
                $status = ucfirst($crypto_value['status']);
                if($is_all_time == 1){
                    $date = date("Y-m-d 00:00:00", strtotime($created_at));
                }
                else{
                    $date = date("Y-m-d H:00:00",strtotime($created_at));
                }
    
                if($status == 'Success'){
                    $converted_total_profit_usd =  bcmul($transaction_fee, $crypto_rate[$wallet_type], 2);
    
                    if($profit_list[$date]){
                        $profit_amount = $profit_list[$date]['value'];
                        $total_amount = $profit_amount + $converted_total_profit_usd;
                        $profit_list[$date]["value"] = strval($total_amount);
                    
                    }
                }
                
            }
    
            unset($crypto_history);
            foreach($crypto_fund_out_details as $crypto_key => $crypto_value){
                $created_at = $crypto_value['created_at'];
                
                $transaction_fee = $crypto_value["transaction_fee"];
                $wallet_type = $crypto_value['wallet_type'];
                $status = ucfirst($crypto_value['status']);
                if($is_all_time == 1){
                    $date = date("Y-m-d 00:00:00", strtotime($created_at));
                }
                else{
                    $date = date("Y-m-d H:00:00",strtotime($created_at));
                }
    
                if($status == 'Confirmed'){
                    $converted_total_profit_usd =  bcmul($transaction_fee, $crypto_rate[$wallet_type], 2);
    
                    if($profit_list[$date]){
                        $profit_amount = $profit_list[$date]['value'];
                        $total_amount = $profit_amount + $converted_total_profit_usd;
                        $profit_list[$date]["value"] = strval($total_amount);
                    
                    }
                }
                
            }
            unset($crypto_fund_out_details);
             $chart_data = array_values($profit_list);
    
            $total_transacted_amount_data = array(
                "total_transacted_amount"=> $total_transacted_amount,
                "piechart_data" => $total_transaction_amount_by_coin,
            );
    
            $total_transaction_by_status_data = array(
                "total_transaction" => $total_transaction,
                "piechart_data" => $total_transaction_by_status,
            );
    
            $total_fund_out_transacted_amount_data = array(
                "total_transacted_amount"=> $total_fund_out_transacted_amount,
                "piechart_data" => $total_fund_out_tx_amount_by_coin,
            );
    
            $total_fund_out_tx_by_status_data = array(
                "total_transaction" => $total_fund_out_transaction,
                "piechart_data" => $total_fund_out_transaction_by_status,
            );
    
            $return_data = array(
                "total_fund_in_by_reseller" => $total_fund_in_by_reseller,
                "total_profit_by_reseller"  => $total_profit_by_reseller,
                "chart_data" => $chart_data,
                "amount_receive_sum" => $amount_receive_sum,
                "sum"   =>  $sum,
                "return_total"  =>  $returnTotal,
                "total_profit" => $total_profit,
                "total_profit_by_coin" => $total_profit_by_coin,
                "total_profit_by_merchant" => $total_profit_by_merchant,
                "total_transacted_amount_data" => $total_transacted_amount_data,
                "total_transaction_by_status_data" => $total_transaction_by_status_data,
                "total_fund_out_transacted_amount_data" => $total_fund_out_transacted_amount_data,
                "total_fund_out_tx_by_status_data" => $total_fund_out_tx_by_status_data,
                 
            );
    
            // return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00278') /*Admin Nuxpay Dashboard.*/, 'data' => $return_data);    
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00278') /*Reseller Dashboard.*/, 'data' => $return_data);    
        }

        public function reseller_nuxpay_merchant_list($params,$userID){
            global $xunCurrency;
            $db = $this->db;
            $setting = $this->setting;
    
            $reseller_page_limit      = $setting->getResellerPageLimit();
            $page_number           = $params["page"];
            $page_size             = $params["page_size"] ? $params["page_size"] : $reseller_page_limit;
            $createDateFrom           = $params["create_date_from"];
            $createDateTo           = $params["create_date_to"];
            $loginDateFrom           = $params["login_date_from"];
            $loginDateTo           = $params["login_date_to"];
            $business_id           = $params["business_id"];
            $business_name         = $params["business_name"];
            $email                 = $params["email"];
            $phone_number          = $params["phone_number"];
            $business_owner_phone_number = $params["business_owner_phone_number"];
            $reseller = $params["reseller"];
            $distributor = $params["distributor"];
            $display_type = $params["display_type"];

            // if( $business_id == '' && $business_name == '' && $business_owner_phone_number == '' && $reseller == '' && $distributor == '' && $distributor == ''){
            //     return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00504') /*Searching parameter is required.*/, 'developer_msg' => 'Searching parameter is required.');
            // }
     
            if ($page_number < 1) {
                $page_number = 1;
            }
    
            $start_limit = ($page_number - 1) * $page_size;
            $limit       = array($start_limit, $page_size);
    
            $db->where('id', $userID);
            $source = $db->getValue("reseller", "source");

            $arr_reseller_id = $this->merchant_get_reseller($userID);

            if($business_id){
                $db->where('a.id',$business_id);
            }
    
            if($business_name){
                $db->where('a.nickname', "%$business_name%", 'LIKE');
            }

            if($email){
                $db->where('a.email', "%$email%", 'LIKE');
            }

            if($phone_number){
                $db->where('a.username', "%$phone_number%", 'LIKE');
            }
    
            if($business_owner_phone_number){
                $db->where('b.main_mobile', "%$business_owner_phone_number%" , 'LIKE');
            }

            //Create Date 
            if($createDateFrom){
                $createDateFrom = date("Y-m-d H:i:s", $createDateFrom);
                $db->where('a.created_at', $createDateFrom, '>');
            }
    
            if($createDateto){
                $createDateto  = date("Y-m-d H:i:s", $createDateto);
                $db->where('a.created_at' , $createDateto, '<=');
            } 

            //Last Login Date
            if($loginDateFrom){
                $loginDateFrom = date("Y-m-d H:i:s", $loginDateFrom);
                $db->where('c.last_login', $loginDateFrom, '>');
            }
    
            if($loginDateTo){
                $loginDateTo  = date("Y-m-d H:i:s", $loginDateTo);
                $db->where('c.last_login' , $loginDateTo, '<=');
            } 
            
            // filter xun_user to corresponding reseller                 
            $db->where('a.reseller_id', $arr_reseller_id, "IN");

            $db->join("reseller r", "(r.id=a.reseller_id AND r.type='reseller')", "LEFT");

            if($reseller) {
                $db->where("r.username", $reseller);
            }

            if($display_type && $display_type != "totalMerchant")
            {
                if ($display_type == "direct") {
                    $display_type = "IS NULL";
                }
                else if ($display_type == "indirect")
                {
                    $display_type = "IS NOT NULL";
                }
                
                elseif ($display_type != "") {
                    $db->where("r.username", $display_type);
                }
                
                if ($display_type) {
                    $db->where("(r.username = '-' OR r.username $display_type)");
                }
            }

            $db->join("reseller r2", "(r2.id=a.reseller_id AND r2.type='distributor')", "LEFT");
            if($distributor) {
                $db->where("r2.username", $distributor);
                
            }

            $db->where("a.register_site", $source);
            $db->where('a.type', 'business');
            $db->where('a.disable_type', 'deleted', '!=');
            $db->join('xun_business b', 'b.user_id = a.id', 'LEFT');
            $db->join('xun_business_account c', 'c.user_id = a.id', 'LEFT');
            // $db->join('xun_business_account b', 'b.user_id = a.id', 'INNER');
            $db->orderBy('a.id', 'DESC');
            $copyDb = $db->copy();
            $nuxpay_user = $db->get('xun_user a', $limit, 'a.id, a.nickname, a.created_at, a.email, c.last_login, a.username as phone_number, IF(r.username is null, "-", r.username) as reseller_name, IF(r2.username is null, "-", r2.username) as distributor_name');
            
            if(!$nuxpay_user){ 
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
            }
            
            $totalRecord = $copyDb->getValue('xun_user a', 'count(a.id)');
    
            $business_owner_mobile_arr = [];
            $business_id_arr = [];
            foreach($nuxpay_user as $key =>$value){
                $business_id = $value['id'];
                if(!in_array($business_id, $business_id_arr)){
                    array_push($business_id_arr, $business_id);
                }
            }
    
            $db->where('user_id', $business_id_arr, 'IN');
            $db->where('name', 'ipCountry');
            $user_country = $db->map('user_id')->ArrayBuilder()->get('xun_user_setting');

            foreach($nuxpay_user as $user_key => $user_value){
                $business_owner_mobile = $user_value['phone_number'] ? $user_value['phone_number'] : '-';
                $business_id = $user_value['id'];
                $business_created_at = $user_value['created_at'];
                $business_name = $user_value['nickname'];
                $last_login = $user_value['last_login'];
                $business_register_site = $user_value['register_site'];
                $business_email = $user_value['email'] ? $user_value['email'] : '-';
                $business_country = $user_country[$business_id] ? $user_country[$business_id]['value'] : '-';
                $reseller_name = $user_value['reseller_name'];
                $distributor_name = $user_value['distributor_name'];

                if(!in_array($business_owner_mobile, $business_owner_mobile_arr)){
                    array_push($business_owner_mobile_arr, $business_owner_mobile);
                }
    
                if(!in_array($business_id, $business_id_arr)){
                    array_push($business_id_arr, $business_id);
                }

                $db->where('a.id', $business_id);
                $db->join("reseller r", "r.id=a.reseller_id", "LEFT");
                $db->join('xun_business c', 'c.user_id = a.id', 'INNER');
                $db->join('xun_business_account b', 'b.user_id = a.id', 'INNER');
                $db->orderBy('a.id', 'DESC');
                $copyDb = $db->copy();
                $nuxpay_user = $db->getOne('xun_user a', $col);
                
                $merch_id = $nuxpay_user['distributor_id'];
                // if distributor name = ""
                if($distributor_name == "-")
                {
                    $db->where('type', 'distributor');
                    $db->where('id', $merch_id);
                    $distributor_name = $db->getValue("reseller", "username");
                }
    
                $merchant_arr = array(
                    "business_id" => $business_id,
                    "business_name" => $business_name,
                    "reseller"  => $reseller_name,
                    "distributor" => $distributor_name,
                    "business_created_at" => $business_created_at,
                    "business_main_mobile" => $business_owner_mobile,
                    "last_login" => $last_login,
                    "total_swap" => '0',
                    "total_withdrawal" => '0',
                    "total_redeem" => '0',
                    "total_send_fund" => '0',
                    "total_external_transfer" => '0',
                    "total_received" => '0',
                    'total_api_intergration' => '0',
                    //"business_owner_name" => '',
                    "business_country" => $business_country,
                    "business_email" => $business_email,
                    "total_fund_in" => '0',
                    "total_transaction" => '0',
                    "total_transaction_usd" => '0.00',
                    "total_commission_usd" => '0.00',
    
                );
    
                $merchant_list[$business_id] = $merchant_arr;
            }
    
            // $db->where('username', $business_owner_mobile_arr, 'IN');
            // $db->where('type', 'user');
            // $business_owner = $db->map('username')->ArrayBuilder()->get('xun_user');
    
            // foreach($merchant_list as $merchant_key => $merchant_value){
            //     $business_owner_mobile = $merchant_value['business_main_mobile'];
            //     $business_id = $merchant_value['business_id'];
    
            //     $business_owner_name = $business_owner[$business_owner_mobile]['nickname'];
            //     $merchant_list[$business_id]['business_owner_name'] = $business_owner_name ? $business_owner_name : '';
            // }

            //swap record
            $db->where('status', 'completed');
            $db->where('business_id', $business_id_arr, 'in');
            $swap_history = $db->get('xun_swap_history');

            foreach($swap_history as $key => $value){
                $business_id = $value['business_id'];
                
                $total_swap = $merchant_list[$business_id]['total_swap'];
                $merchant_list[$business_id]['total_swap'] = $total_swap + 1;
            }

            //redeem record
            $db->where('status', 'success');
            $db->where('tx_type', 'redeem_code');
            $db->where('redeemed_by', $business_id_arr, 'in');
            $redeem_history = $db->get('xun_payment_gateway_send_fund');

            foreach($redeem_history as $key => $value){
                $business_id = $value['business_id'];

                $total_redeem = $merchant_list[$business_id]['total_redeem'];
                $merchant_list[$business_id]['total_redeem'] = $total_redeem + 1;
            }

            //Send Fund record
            $db->where('status', 'success');
            $db->where('tx_type', 'fund_in');
            $db->where('business_id', $business_id_arr, 'in');
            $sendFund_history = $db->get('xun_payment_gateway_send_fund');

            foreach($sendFund_history as $key => $value){
                $business_id = $value['business_id'];

                $total_send_fund = $merchant_list[$business_id]['total_send_fund'];
                $merchant_list[$business_id]['total_send_fund'] = $total_send_fund + 1;
            }

            //External Transfer record
            $db->where('status', 'confirmed');
            $db->where('business_id', $business_id_arr, 'in');
            $external_transfer_history = $db->get('xun_crypto_fund_out_details');

            foreach($external_transfer_history as $key => $value){
                $business_id = $value['business_id'];

                $total_external_transfer = $merchant_list[$business_id]['total_external_transfer'];
                $merchant_list[$business_id]['total_external_transfer'] = $total_external_transfer + 1;
            }

            //Received Fund record
            $db->where('a.gw_type', 'PG');
            $db->where('b.status', 'success');
            $db->where('a.business_id', $business_id_arr, 'in');
            $db->join('xun_payment_gateway_invoice_detail b', 'b.payment_address = a.address', 'LEFT');
            $received_fund_history = $db->get('xun_crypto_history a');

            foreach($received_fund_history as $key => $value){
                $business_id = $value['business_id'];

                $total_received = $merchant_list[$business_id]['total_received'];
                $merchant_list[$business_id]['total_received'] = $total_received + 1;
            }

            //API Intergration record
            
            $db->where('business_id', $business_id_arr, 'in');
            $crypto_wallet = $db->get('xun_crypto_wallet');


            if($crypto_wallet){
                foreach($crypto_wallet as $value){
                    $wallet_id = $value["id"];
    
                    $wallet_id_arr [] = $wallet_id;
                }
    
                $db->where('wallet_id', $wallet_id_arr, 'in');
                $crypto_address = $db->get('xun_crypto_address');
                foreach($crypto_address as $value){
                    $address = $value["crypto_address"];
    
                    $address_arr [] = $address;
                }
    
                $db->where('address', $address_arr, 'in');
                $crypto_history = $db->get('xun_crypto_history');
    
                foreach($crypto_history as $key => $value){
                    $business_id = $value['business_id'];
                    
                    $total_api_intergration = $merchant_list[$business_id]['total_api_intergration'];
                    $merchant_list[$business_id]['total_api_intergration'] = $total_api_intergration + 1;
                }
    
            }
            else {
                foreach($business_id_arr as $business_value){
                    $merchant_list[$business_value]['total_api_integration'] = 0;
                }
            }
            
            // $db->where('a.business_id', $business_id_arr, 'in');
            // $db->join('xun_crypto_address b', 'b.crypto_address = a.address', 'LEFT');
            // $apiIntergration_history = $db->get('xun_crypto_history a');
            
            // foreach($apiIntergration_history as $key => $value){
            //     $business_id = $value['business_id'];

            //     $total_api_intergration = $merchant_list[$business_id]['total_api_intergration'];
            //     $merchant_list[$business_id]['total_api_intergration'] = $total_api_intergration + 1;
            // }

            //withdrawal record
            $db->where('status', 'success');
            $db->where('transaction_type', 'manual_withdrawal');
            $db->where('business_id', $business_id_arr, 'in');
            $withdrawal_history = $db->get('xun_payment_gateway_withdrawal');

            foreach($withdrawal_history as $key => $value){
                $business_id = $value['business_id'];
                
                $total_withdrawal = $merchant_list[$business_id]['total_withdrawal'];
                $merchant_list[$business_id]['total_withdrawal'] = $total_withdrawal + 1;
            }

            // Fund In Record
            $db->where('gw_type', 'BC');
            $db->where('status', 'success');
            $db->where('business_id', $business_id_arr, 'in');
            $fund_in_history = $db->get('xun_crypto_history');
            foreach($fund_in_history as $key => $value){
                $business_id = $value['business_id'];
                
                $total_fund_in = $merchant_list[$business_id]['total_fund_in'];
                $merchant_list[$business_id]['total_fund_in'] = $total_fund_in + 1;
            }

            // Total Transaction amount & commission
            $db->where('status', 'success');
            $db->where('business_id', $business_id_arr, 'in');
            $crypto_history = $db->get('xun_crypto_history');
    
            $marketplace_currencies = $xunCurrency->get_cryptocurrency_list('a.id, a.currency_id, a.symbol');
            $crypto_rate = $xunCurrency->get_cryptocurrency_rate_with_stable_coin($marketplace_currencies);
            
            foreach($crypto_history as $key => $value){
                $business_owner_mobile = $value['main_mobile'];
                $amount_receive = $value['amount_receive'];
                $transaction_fee = $value['transaction_fee'];
                $wallet_type = strtolower($value['wallet_type']);
                $business_id = $value['business_id'];
    
                $total_transaction = $merchant_list[$business_id]['total_transaction'];
                $total_transaction_usd = $merchant_list[$business_id]['total_transaction_usd'];
                $total_commission_usd = $merchant_list[$business_id]['total_commission_usd'];
    
                $converted_total_transaction_usd =  bcmul($amount_receive, $crypto_rate[$wallet_type], 2);
                $converted_total_commission_usd =  bcmul($transaction_fee, $crypto_rate[$wallet_type], 2);
    
                $new_total_transaction_usd = bcadd($total_transaction_usd, $converted_total_transaction_usd, 2);
                $new_total_commission_usd = bcadd($total_commission_usd, $converted_total_commission_usd, 2);
    
                $merchant_list[$business_id]['total_transaction_usd'] = $new_total_transaction_usd;
                $merchant_list[$business_id]['total_commission_usd'] = $new_total_commission_usd;
                $merchant_list[$business_id]['total_transaction'] = $total_transaction + 1;
            }
    
            $returnData['merchant_listing'] = $merchant_list;
            $returnData["totalRecord"] = (int) $totalRecord;
            $returnData["numRecord"] = (int) $page_size;
            $returnData["totalPage"] = ceil($totalRecord/$page_size);
            $returnData["pageNumber"] = (int) $page_number;
                
            // return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00275') /*Admin Nuxpay Merchant List.*/, 'data' => $returnData);
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00300') /*Reseller Merchant List.*/, 'data' => $returnData);
    
        }

        // public function reseller_nuxpay_merchant_list($params,$userID){
        //     global $xunCurrency;
        //     $db = $this->db;
        //     $setting = $this->setting;
    
        //     $admin_page_limit      = $setting->getAdminPageLimit();
        //     $page_number           = $params["page"];
        //     $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        //     $business_id           = $params["business_id"];
        //     $business_name         = $params["business_name"];
        //     $business_owner_phone_number = $params["business_owner_phone_number"];
    
        //     if($business_id == '' && $business_name == '' && $business_owner_phone_number == ''){
        //         return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00504') /*Searching parameter is required.*/, 'developer_msg' => 'Searching parameter is required.');
        //     }
     
        //     if ($page_number < 1) {
        //         $page_number = 1;
        //     }
    
        //     $start_limit = ($page_number - 1) * $page_size;
        //     $limit       = array($start_limit, $page_size);
    
        //     if($business_id){
        //         $db->where('a.id',$business_id);
        //     }
    
        //     if($business_name){
        //         $db->where('a.nickname', "%$business_name%", 'LIKE');
        //     }
    
        //     if($business_owner_phone_number){
        //         $db->where('a.username', "%$business_owner_phone_number%" , 'LIKE');
        //     }
    
        //     $db->where('a.type', 'reseller');
        //     // filter xun_user to corresponding reseller                 
            
        //     $db->where('a.reseller_id', $userID);
        //     // $db->join('xun_business_account b', 'b.user_id = a.id', 'LEFT');

        //     $db->orderBy('a.id', 'DESC');
        //     $copyDb = $db->copy();
        //     $nuxpay_user = $db->get('xun_user a', $limit, 'a.id, a.nickname, a.created_at, a.username');
        //     if(!$nuxpay_user){
        //         return array('status' => 'ok', 'code' => 0, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
        //     }
            
        //     $totalRecord = $copyDb->getValue('xun_user a', 'count(a.id)');
    
        //     $business_owner_mobile_arr = [];
        //     $business_id_arr = [];
        //     foreach($nuxpay_user as $user_key => $user_value){
        //         $business_owner_mobile = $user_value['main_mobile'];
        //         $business_id = $user_value['id'];
        //         $business_created_at = $user_value['created_at'];
        //         $business_name = $user_value['nickname'];
    
        //         if(!in_array($business_owner_mobile, $business_owner_mobile_arr)){
        //             array_push($business_owner_mobile_arr, $business_owner_mobile);
        //         }
    
        //         if(!in_array($business_id, $business_id_arr)){
        //             array_push($business_id_arr, $business_id);
        //         }
    
        //         $merchant_arr = array(
        //             "business_id" => $business_id,
        //             "business_name" => $business_name,
        //             "business_created_at" => $business_created_at,
        //             "business_main_mobile" => $business_owner_mobile,
        //             "business_owner_name" => '',
        //             "total_transaction" => '0',
        //             "total_transaction_usd" => '0.00',
        //             "total_commission_usd" => '0.00',
    
        //         );
    
        //         $merchant_list[$business_id] = $merchant_arr;
        //     }
    
        //     $db->where('username', $business_owner_mobile_arr, 'IN');
        //     $db->where('type', 'user');
        //     $business_owner = $db->map('username')->ArrayBuilder()->get('xun_user');
    
        //     foreach($merchant_list as $merchant_key => $merchant_value){
        //         $business_owner_mobile = $merchant_value['business_main_mobile'];
        //         $business_id = $merchant_value['business_id'];
    
        //         $business_owner_name = $business_owner[$business_owner_mobile]['nickname'];
        //         $merchant_list[$business_id]['business_owner_name'] = $business_owner_name ? $business_owner_name : '';
        //     }
            
        //     $db->where('status', 'success');
        //     $db->where('business_id', $business_id_arr, 'in');
        //     $crypto_history = $db->get('xun_crypto_history');
    
        //     $marketplace_currencies = $xunCurrency->get_cryptocurrency_list('a.id, a.currency_id, a.symbol');
        //     $crypto_rate = $xunCurrency->get_cryptocurrency_rate_with_stable_coin($marketplace_currencies);
    
        //     foreach($crypto_history as $key => $value){
        //         $business_owner_mobile = $value['main_mobile'];
        //         $amount_receive = $value['amount_receive'];
        //         $transaction_fee = $value['transaction_fee'];
        //         $wallet_type = strtolower($value['wallet_type']);
        //         $business_id = $value['business_id'];
    
        //         $total_transaction = $merchant_list[$business_id]['total_transaction'];
        //         $total_transaction_usd = $merchant_list[$business_id]['total_transaction_usd'];
        //         $total_commission_usd = $merchant_list[$business_id]['total_commission_usd'];
    
        //         $converted_total_transaction_usd =  bcmul($amount_receive, $crypto_rate[$wallet_type], 2);
        //         $converted_total_commission_usd =  bcmul($transaction_fee, $crypto_rate[$wallet_type], 2);
    
        //         $new_total_transaction_usd = bcadd($total_transaction_usd, $converted_total_transaction_usd, 2);
        //         $new_total_commission_usd = bcadd($total_commission_usd, $converted_total_commission_usd, 2);
    
        //         $merchant_list[$business_id]['total_transaction_usd'] = $new_total_transaction_usd;
        //         $merchant_list[$business_id]['total_commission_usd'] = $new_total_commission_usd;
        //         $merchant_list[$business_id]['total_transaction'] = $total_transaction + 1;
                          
        //     }
    
        //     $returnData['merchant_listing'] = $merchant_list;
        //     $returnData["totalRecord"] = (int) $totalRecord;
        //     $returnData["numRecord"] = (int) $page_size;
        //     $returnData["totalPage"] = ceil($totalRecord/$page_size);
        //     $returnData["pageNumber"] = (int) $page_number;
    
        //     return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00275') /*Admin Nuxpay Merchant List.*/, 'data' => $returnData);
    
        // }

        public function reseller_nuxpay_transaction_history_listing($params,$userID, $fromSite){
            global $xunCurrency;
            $db = $this->db;
            $setting = $this->setting;
    
            $reseller_page_limit   = $setting->getResellerPageLimit();
            $page_number           = $params["page"];
            $page_size             = $params["page_size"] ? $params["page_size"] : $reseller_page_limit;
            $to_datetime           = $params["date_to"];
            $from_datetime         = $params["date_from"];
            $business_id           = $params["business_id"];
            $business_name         = $params["business_name"];
            $tx_hash               = $params['tx_hash'];
            //$status                = $params['status'];
            $reseller              = $params['reseller'];
            $distributor           = $params['distributor'];
            $site              = $params["site"];
            $tx_hash               = $params['tx_hash'];
            //$status                = $params['status'];
            $phone_no              = $params['phone_no'];
            $address               = $params['address'];
            $dest_address           = $params['dest_address'];
            $coin_type           = $params['coin_type'];
            $sender_address          = $params['sender_address'];

            if($business_id == '' && $business_name == '' && $to_datetime == '' && $from_datetime == '' && $tx_hash == '' /*&& $status == '' */&& $reseller == '' && $distributor =='' && $site == '' && $phone_no == '' && $address == '' && $dest_address == '' && $sender_address == '' && $coin_type == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00504') /*Searching parameter is required.*/, 'developer_msg' => 'Searching parameter is required.');
            }
           
            if($fromSite!="Admin") {
                $db->where("id", $userID);
                $source = $db->getValue("reseller", "source");

                $arr_reseller_id = $this->get_reseller($userID);
                $db->where('b.reseller_id', $arr_reseller_id, "IN");

                $db->where("b.register_site", $source);
            }

            if($from_datetime){
                $from_datetime = date("Y-m-d H:i:s", $from_datetime);
                $db->where('a.created_at', $from_datetime, '>');
            }
    
            if($to_datetime){
                $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
                $db->where('a.created_at' , $to_datetime, '<=');
            }            
    
            if($tx_hash){
                $db->where('a.transaction_id', "%$tx_hash%", 'LIKE');
            }
    
            if($business_name){
                $db->where('b.nickname', "%$business_name%" , 'LIKE');
            }
        

            $db->orderBy('a.id', 'DESC');
            
            $db->join('xun_business_account c', 'a.business_id = c.user_id', 'INNER');
            $db->join('xun_user b', 'a.business_id = b.id', 'INNER');
            $db->join("reseller r", "r.id=b.reseller_id", "LEFT");

            $db->join("reseller r2", "r2.id=r.distributor_id", "LEFT");

            if($distributor) {
                $db->where("r2.username", $distributor);
            }

            if($reseller) {
                $db->where("r.username", $reseller);
            }

            if($site) {
                $db->where("b.register_site", $site);
            }

            if($business_id){
                $db->where('a.business_id', $business_id);
            }

            if($phone_no){
                $db->where('c.main_mobile', "%$phone_no%", 'LIKE');
            };
    
            if($address){
                $db->where('a.receiver_address', "%$address%", 'LIKE');
            }

            if($sender_address){
                $db->where('a.sender_address', "%$sender_address%", 'LIKE');
            }
    
            // if($dest_address){
            //     $db->where('a.recipient_internal', "%$dest_address%", 'LIKE')->where('a.recipient_external', "%$dest_address%", 'LIKE', 'OR');
            // }
            
            // if($sender_address){
            //     $db->where('a.sender_internal', "%$sender_address%", 'LIKE')->where('a.sender_external', "%$sender_address%", 'LIKE', 'OR');
            // }
    
            if($coin_type){
                $db->where('a.wallet_type', $coin_type);
            }
    
            if ($page_number < 1) {
                $page_number = 1;
            }
    
            $start_limit = ($page_number - 1) * $page_size;
            $limit       = array($start_limit, $page_size);
            
            // $db->where('a.status', array('received', 'pending', 'success') , 'IN');
           
            $copyDb = $db->copy();
            $copyDb2 = $db->copy();
            $crypto_history = $db->get('xun_payment_gateway_fund_in a', $limit, 'a.business_id, a.wallet_type, a.amount_receive as amount, a.exchange_rate as exchange_rate, a.miner_fee, a.sender_address, a.receiver_address, a.transaction_fee, a.miner_fee_wallet_type, a.transaction_id as transaction_hash, a.created_at, b.nickname as business_name, b.service_charge_rate, c.main_mobile as username, IF(r.username is null, "-", r.username) as reseller, IF(r2.username is null, "-", r2.username) as distributor, b.register_site as site ');
    
            $overall_total_transaction = 0;
            if(!$crypto_history){
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
            } 
    
            $crypto_history2 = $copyDb->get('xun_payment_gateway_fund_in a', null, 'a.business_id, a.wallet_type, a.miner_fee_wallet_type, a.amount_receive as amount, a.transaction_fee, a.transaction_id as transaction_hash, a.created_at, b.nickname as business_name, b.service_charge_rate');
            $totalRecord = $copyDb2->getValue('xun_payment_gateway_fund_in a', 'count(a.id)');
            $cryptocurrency_list = $xunCurrency->get_all_cryptocurrency_list();
    
            foreach($crypto_history as $key => $value){
                $service_charge_rate = $value['service_charge_rate'] ? $value['service_charge_rate'] : '0.2';
                $wallet_type = strtolower($value['wallet_type']);
                $amount = $value['amount'];

                // $urlRegex = '/^https?:\/\//';
                // if (!preg_match($urlRegex, $crypto_history[$key]['transaction_url'])){
                //     $crypto_history[$key]['transaction_url'] = "-";
                // }
        
                $crypto_history[$key]['amount_usd'] = bcmul(strval($crypto_history[$key]['amount']), strval($crypto_history[$key]['exchange_rate']), 2);
                $crypto_history[$key]['service_charge_rate'] = $service_charge_rate;
                $crypto_history[$key]['symbol'] = strtoupper($cryptocurrency_list[$wallet_type]['symbol']);
            }
    
            foreach($crypto_history2 as $key2 => $value2){
    
                $service_charge_rate = $value2['service_charge_rate'] ? $value2['service_charge_rate'] : '0.2';
                $wallet_type = strtolower($value2['wallet_type']);
                $tx_fee_wallet_type = strtolower($value2['miner_fee_wallet_type']);
                $amount = $value2['amount'];
                $transaction_fee = $value2['transaction_fee'];
                // $status = ucfirst($value2['status']);
    
                $tx_fee_symbol = strtoupper($cryptocurrency_list[$tx_fee_wallet_type]['symbol']);
                if($transaction_summary_list[$wallet_type]){
                    $total_transaction = $transaction_summary_list[$wallet_type]['total_transaction'];
                    $total_amount = $transaction_summary_list[$wallet_type]['total_amount'];
                    $total_service_fee = $transaction_summary_list[$wallet_type]['total_service_fee'];
                    $new_total_amount = bcadd($total_amount, $amount, 8);
                    $transaction_summary_list[$wallet_type]['total_amount'] = $new_total_amount;
                    $new_total_service_fee = bcadd($total_service_fee, $transaction_fee, 8);
                    $transaction_summary_list[$wallet_type]['total_service_fee'] = $new_total_service_fee;
                    $transaction_summary_list[$wallet_type]['total_transaction'] = $total_transaction + 1;
     
                }
                else{
                    $transaction_summary_list[$wallet_type]['total_amount'] = $amount;
                    $transaction_summary_list[$wallet_type]['total_service_fee'] = $transaction_fee;
                    $transaction_summary_list[$wallet_type]['total_transaction'] = 1;
                    $transaction_summary_list[$wallet_type]['tx_fee_symbol'] = $tx_fee_symbol;
                }
    
                // if(!$total_transaction_by_status[$status]){
                //     $total_transaction_by_status[$status] = 1;
                // }
                // else{
                //     $total_transaction_by_status[$status]++;
                // }
                $overall_total_transaction++;
    
            }
    
            foreach($transaction_summary_list as $tx_key => $tx_value){
                $wallet_type = $tx_key;
    
                $transaction_summary_list[$wallet_type]['coin_name'] = $cryptocurrency_list[$wallet_type]['name'];
            }
    
            $transaction_summary_list = array_values($transaction_summary_list);
    
            $returnData['transaction_summary'] = $transaction_summary_list;
            $returnData["overall_total_transaction"] = $overall_total_transaction;
            $returnData["total_transaction_by_status"] = $total_transaction_by_status;
            $returnData["transaction_history_list"] = $crypto_history;
            $returnData["totalRecord"] = $totalRecord;
            $returnData["numRecord"] = $page_size;
            $returnData["totalPage"] = ceil($totalRecord/$page_size);
            $returnData["pageNumber"] = $page_number;
                
            // return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00274') /*Admin Nuxpay Transaction History List.*/, 'data' => $returnData);
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00301') /*Reseller Transaction History List.*/, 'data' => $returnData);
        }

        public function reseller_change_password($params){
            $db = $this->db;
    
            $username = $params['username'];
            $current_password = $params['current_password'];
            $new_password = $params['new_password'];
            $confirm_password = $params['confirm_password'];
            // $otp_code = $params['otp_code'];
    
            if($username == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00404') /*Username cannot be empty.*/, 'developer_msg' => 'Username cannot be empty.');
            }

            // if($otp_code == ''){
            //     return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00404') /*Username cannot be empty.*/, 'developer_msg' => 'Username cannot be empty.');
            // }
    
            if($current_password == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00066') /*Current password cannot be empty*/, 'developer_msg' => 'Current password cannot be empty');
            }
    
            if($new_password == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00067') /*New password cannot be empty*/, 'developer_msg' => 'New password cannot be empty');
            }
    
            if($confirm_password == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00068') /*Confirm password cannot be empty*/, 'developer_msg' => 'Confirm password cannot be empty');
            }
    
            if($new_password != $confirm_password){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00280') /*Password does not match.*/, 'developer_msg' => 'Password does not match.');
            }
    
            $hash_password = password_hash($new_password, PASSWORD_BCRYPT);
      
            $db->where('username', $username);
            // $admin = $db->getOne('admin');
            $reseller = $db->getOne('reseller');
    
            if(!$reseller){
                return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/);
            }
    
            $db_password = $reseller['password'];
        
            if (!password_verify($current_password, $db_password)) {
                return array("code" => 1, "status" => "error", "statusMsg" =>  $this->get_translation_message('E00070') /*Your password is incorrect. Please try again.*/);
            } 
    
            $update_new_password = array(
                "password" => $hash_password,
                "updated_at" => date("Y-m-d H:i:s")
            );
      
            $db->where('username', $username);
            $updated = $db->update('reseller', $update_new_password);
    
            if(!$updated){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
            }
    
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00288') /*Reseller Password Successfully Changed.*/);
        }

        
public function reseller_get_fund_out_listing($params,$userID){
    $db = $this->db;
    $setting = $this->setting;

    $reseller_page_limit   = $setting->getResellerPageLimit();
    $page_number           = $params["page"];
    $page_size             = $params["page_size"] ? $params["page_size"] : $reseller_page_limit;
    $to_datetime           = $params["date_to"];
    $from_datetime         = $params["date_from"];
    $business_id           = $params['business_id'];   
    $status                = $params['status'];
    $business_name         = $params['business_name'];            
    $reseller              = $params["reseller"]; 
    $distributor           = $params["distributor"]; 
    $site                  = $params["site"]; 
    $tx_hash               = $params["tx_hash"]; 
    $recipient_address     = $params["recipient_address"]; 
    $currency              = $params["currency"];
    $status                = $params["status"];

    if ($page_number < 1) {
        $page_number = 1;
    }

    $start_limit = ($page_number - 1) * $page_size;
    $limit       = array($start_limit, $page_size);


    if($fromSite!="Admin") {
        $db->where("id", $userID);
        $source = $db->getValue("reseller", "source");

        $arr_reseller_id = $this->get_reseller($userID);
        $db->where('b.reseller_id', $arr_reseller_id, "IN");

        $db->where("b.register_site", $source);
    }


    if($from_datetime){
        $from_datetime = date("Y-m-d H:i:s", $from_datetime);
        $db->where('a.created_at', $from_datetime, '>');
    }

    if($to_datetime){
        $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
        $db->where('a.created_at' , $to_datetime, '<=');
    }

    if($status){
        $db->where('a.status', $status);
    }

    if($business_name){
        $db->where('b.nickname', "%$business_name%" , 'LIKE');
    }

    if($business_id){
        $db->where('a.business_id', $business_id);
    }
    
    if($reseller) {
        $db->where("r.username", "%$reseller%", 'LIKE');
    }

    $db->orderBy('a.created_at', 'DESC');
    
    // filter xun_user to corresponding reseller              
    $db->where('b.reseller_id', $arr_reseller_id, "IN");                        

    $db->join('xun_user b', 'a.business_id = b.id', 'INNER');
    $db->join("reseller r", "r.id=b.reseller_id", "LEFT");

    $db->join("reseller r2", "r2.id=r.distributor_id", "LEFT");
    
    if($distributor) {
        $db->where("r2.username", "%$distributor%", 'LIKE');
    }

    if($site) {
        $db->where("r.source", "%$site%", 'LIKE');
    }

    if($tx_hash) {
        $db->where("a.tx_hash", "%$tx_hash%", 'LIKE');
    }

    if($recipient_address) {
        $db->where("a.recipient_address", "%$recipient_address%", 'LIKE');
    }

    if($currency) {
        $db->where("a.wallet_type", "%$currency%", 'LIKE');
    }

    if($status) {
        $db->where("a.status", "%$status%", 'LIKE');
    }
    
    $copyDb = $db->copy();
    $copyDb2 = $db->copy();
    $copyDb3 = $db->copy();
    $fund_out_details = $db->get('xun_crypto_fund_out_details a', $limit, 'a.business_id, a.sender_address, a.recipient_address, a.amount, a.wallet_type, a.service_charge_amount, a.pool_amount, a.status, a.tx_hash, a.created_at, a.remark, b.nickname as business_name, IF(r.username is null, "-", r.username) as reseller_name, IF(r2.username is null, "-", r2.username) as distributor_name, b.register_site as site');

    if(!$fund_out_details){
        return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/, 'data' => '');
    }

    $totalRecord = $copyDb->getValue('xun_crypto_fund_out_details a', 'count(a.id)');
   
    $copyDb2->groupBy('a.wallet_type');
    $summary_list = $copyDb2->map('wallet_type')->ArrayBuilder()->get('xun_crypto_fund_out_details a', null, 'sum(a.amount) as total_amount, sum(a.service_charge_amount) as total_service_fee, sum(a.pool_amount) as total_miner_fee, count(a.id) as total_transaction, a.wallet_type');

    $copyDb3->groupBy('a.status');
   
    $total_transaction_by_status = $copyDb3->map('status')->ArrayBuilder()->get('xun_crypto_fund_out_details a', null, 'a.status, count(a.id)');

    $wallet_type_arr = [];
    foreach($summary_list as $key => $value){
        $wallet_type = strtolower($value['wallet_type']);
        array_push($wallet_type_arr, $wallet_type);
        
    }

    $db->where('currency_id', $wallet_type_arr, 'IN');
    $marketplace_currencies = $db->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies');

    foreach($summary_list as $key => $value){
        $wallet_type = strtolower($value['wallet_type']);

        $summary_list[$wallet_type]['coin_name']= $marketplace_currencies[$wallet_type]['name'];
        $summary_list[$wallet_type]['tx_fee_symbol']= strtoupper($marketplace_currencies[$wallet_type]['symbol']);
    }

    foreach($total_transaction_by_status as $key=> $value){
        $status = ucfirst($key);
        $total_transaction_by_status[$status] = $value;
        unset($total_transaction_by_status[$key]);
    }

    $data = array(
        "fund_out_listing" => $fund_out_details,
        "totalRecord" => $totalRecord,
        "numRecord" => $page_size,
        "totalPage" => ceil($totalRecord/$page_size),
        "pageNumber" => $page_number,
        "summary_listing" => $summary_list,
        "total_transaction_by_status" => $total_transaction_by_status,
        "overall_total_transaction" => $totalRecord,
    );
        
    // return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00293') /*Admin Fund Out Listing.*/, 'data' => $data);    
    return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00302') /*Reseller Fund Out Listing.*/, 'data' => $data);    
}

public function reseller_fund_out_details($params){
    global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;

        $admin_page_limit      = $setting->getResellerPageLimit();
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $tx_hash               = $params['tx_hash'];
        $default_service_charge_rate = $setting->systemSetting["theNuxCommissionFeePct"];

        $db->where("a.tx_hash", $tx_hash);
        $db->join("xun_user b", "b.id=a.business_id", "LEFT");
        $paidAmount = $db->get("xun_crypto_fund_out_details a", null, "a.business_id, a.amount, a.service_charge_amount, IF(b.service_charge_rate is null, $default_service_charge_rate, b.service_charge_rate) as service_charge_rate");
        
        $db->where("a.tx_hash", $tx_hash);
        $db->join("xun_service_charge_audit b", "a.service_charge_wallet_tx_id=b.wallet_transaction_id", "LEFT");
        $db->join("xun_wallet_transaction c", "b.id=c.reference_id", " LEFT");
        $db->join("xun_marketplace_currencies d", "c.wallet_type=d.currency_id", "LEFT");
        $fundOutDetails = $db->get("xun_crypto_fund_out_details a", null, "c.created_at, c.id, c.address_type, c.recipient_address, c.amount, c.transaction_hash, c.wallet_type, c.fee, c.miner_fee_exchange_rate, c.exchange_rate");
        
        $db->join("xun_wallet_transaction b", "a.symbol=b.fee_unit", "INNER");
        $marketplaceCurrencies =$db->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies a',null,  "a.currency_id");
        
        foreach($fundOutDetails as $key => $value){
            $created_at                     = $value['created_at'];
            $address_type                   = $value['address_type'];
            $recipient_address              = $value['recipient_address'];
            $amount                         = $value['amount'];
            $transaction_hash               = $value['transaction_hash'];
            $wallet_type                    = $value['wallet_type'];
            $fee                            = $value['fee'];
            $minerFeeExchangeRate           = $value['miner_fee_exchange_rate'];
            $exchangeRate                   = $value['exchange_rate'];
            $currencyID                     = $marketplaceCurrencies['currency_id'];
            $decimalPlace                  = $xunCurrency->get_currency_decimal_places($wallet_type);


            if($address_type == "company_acc"){
                $address_type = "Company Account";
            }else if($address_type == "company_pool"){
                $address_type = "Company Pool";
            }else if($address_type == "marketer"){
                $address_type = "Marketer";
            }else{
                $address_type = "-";
            }


            if($wallet_type != $currencyID){
                
                $usdAmount = bcmul($fee, $minerFeeExchangeRate, 18);
                $minerFee = bcmul($usdAmount, $exchangeRate, 18);
                
            }
            else if ($wallet_type == $currencyID){
                $minerFee = $fee;
            }
            
            $fundOut_arr = array(
                "date" => $created_at,
                "address_type" => $address_type,
                "recipient_address" => $recipient_address,
                "amount"            => $amount,
                "transaction_hash"  => $transaction_hash,
                "minerFee"          => $minerFee,
            );
            $data["fundOutDetails"] = $fundOut_arr;

        }

        foreach($paidAmount as $key => $value){
            $amount                     = $value['amount'];
            $service_charge_rate        = $value['service_charge_rate'];
            $service_charge_amount      = $value['service_charge_amount'];

            $paidAmount_arr = array(
                "amount" => $amount,
                "service_charge_rate" => $service_charge_rate,
                "service_charge_amount" => $service_charge_amount
            
            );
        $data["paidAmount"] = $paidAmount_arr;
        }

        
        
        // $returnData["fundOutDetails"] = $fundOutDetails;
        
        
        // $test = array("code" => 0, "status" => "ok", "statusMsg" => $translation['B00307'][$language] /*Admin Reseller Listing*/, 'data' => $data);
        //         echo json_encode($test);
        
        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('E00397') /*Success.*/, 'data' => $data);

}

        // public function reseller_nuxpay_merchant_details($params){
        //     global $xunCurrency;
        //     $db = $this->db;
        //     $setting = $this->setting;
    
        //     $business_id           = $params["business_id"];
    
        //     if($business_id == ''){
        //         return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/, 'developer_msg' => 'Business ID cannot be empty.');
        //     }
    
        //     $col = 'a.id, a.nickname, a.service_charge_rate,  a.created_at, b.main_mobile, c.email, c.website, c.phone_number, c.company_size, c.address1, c.address2, c.city, c.state, c.postal, c.country';
        //     $db->where('a.id', $business_id);
        //     $db->join('xun_business c', 'c.user_id = a.id', 'LEFT');
        //     $db->join('xun_business_account b', 'b.user_id = a.id', 'LEFT');
        //     $db->orderBy('a.id', 'DESC');
        //     $copyDb = $db->copy();
        //     $nuxpay_user = $db->getOne('xun_user a', $col);
     
        //     if(!$nuxpay_user){
        //         return array('status' => 'ok', 'code' => 0, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
        //     }
    
        //     $business_owner_mobile = $nuxpay_user['main_mobile'];
        //     $business_name = $nuxpay_user['nickname'];
        //     $address1 = $nuxpay_user['address1'];
        //     $address2 = $nuxpay_user['address2'];
        //     $city = $nuxpay_user['city'];
        //     $state = $nuxpay_user['state'];
        //     $postal = $nuxpay_user['postal'];
        //     $country = $nuxpay_user['country'];
        //     $business_website = $nuxpay_user['website'];
        //     $business_phone_number = $nuxpay_user['phone_number'];
        //     $business_company_size = $nuxpay_user['company_size'];
        //     $business_created_at = $nuxpay_user['created_at'];
        //     $service_charge_rate = $nuxpay_user['service_charge_rate'];
    
        //     if($address1){
        //         $address_arr[] = $address1;
        //     }
    
        //     if($address2){
        //         $address_arr[] = $address2;
        //     }
    
        //     if($postal){
        //         $address_arr[] = $postal;
        //     }
    
        //     if($city){
        //         $address_arr[] = $city;
        //     }
    
        //     if($state){
        //         $address_arr[] = $state;
        //     }
    
        //     if($country){
        //         $address_arr[] = $country;
        //     }
    
        //     if($address_arr){
        //         $business_address = implode(", ", $address_arr);
        //     }
            
        //     $db->where('username', $business_owner_mobile);
        //     $business_owner = $db->getOne('xun_user');
        //     $business_owner_name = $business_owner['nickname'];
    
        //     $db->where('user_id', $business_id);
        //     $db->where('name', 'ipCountry');
        //     $user_setting = $db->getOne('xun_user_setting');
        //     $ip_country = $user_setting['value'];
            
        //     $db->where('business_id', $business_id);
        //     $crypto_history = $db->get('xun_crypto_history');
    
        //     $marketplace_currencies = $xunCurrency->get_cryptocurrency_list('a.id, a.currency_id, a.symbol');
        //     $crypto_rate = $xunCurrency->get_cryptocurrency_rate_with_stable_coin($marketplace_currencies);
    
        //     $total_transaction = 0;
        //     $total_transaction_usd = '0.00';
        //     $total_commission_usd = '0.00';
        //     $transacted_wallet_type= [];
        //     $wallet_type_total_transaction = [];
        //     foreach($crypto_history as $key => $value){
        //         $wallet_type = strtolower($value['wallet_type']);
        //         $amount_receive = $value['amount_receive'];
        //         $transaction_fee = $value['transaction_fee'];
        //         $status = ucfirst($value['status']);
    
        //         if(!$total_transaction_by_status[$status]){
        //             $total_transaction_by_status[$status] = 1;
        //         }
        //         else{
        //             $total_transaction_by_status[$status]++;
        //         }
    
        //         $converted_total_transaction_usd =  bcmul($amount_receive, $crypto_rate[$wallet_type], 2);
        //         $converted_total_commission_usd =  bcmul($transaction_fee, $crypto_rate[$wallet_type], 2);
    
        //         $total_transaction_usd = bcadd($total_transaction_usd, $converted_total_transaction_usd, 2);
        //         $total_commission_usd = bcadd($total_commission_usd, $converted_total_commission_usd, 2);
    
        //         $total_transaction++;
    
        //         if(!$wallet_type_total_transaction[$wallet_type]){
        //             $wallet_type_total_transaction[$wallet_type] = array(
        //                 "total_amount" => $amount_receive,
        //                 "total_amount_usd" => $converted_total_transaction_usd,
        //             );
        //         }
        //         else{
        //             $total_amount = $wallet_type_total_transaction[$wallet_type]["total_amount"];
        //             $total_amount_usd = $wallet_type_total_transaction[$wallet_type]["total_amount_usd"];
    
        //             $new_total_amount = bcadd($total_amount, $amount_receive, 8);
        //             $new_total_amount_usd = bcadd($total_amount_usd, $converted_total_transaction_usd, 2);
        //             $wallet_type_total_transaction_arr = array(
        //                 "total_amount" => $new_total_amount,
        //                 "total_amount_usd" => $new_total_amount_usd
        //             );
    
        //             $wallet_type_total_transaction[$wallet_type] = $wallet_type_total_transaction_arr;
        //         }
    
        //         if(!$wallet_type_total_commission[$wallet_type]){
        //             $wallet_type_total_commission[$wallet_type] = array(
        //                 "total_commission_amount" => $transaction_fee,
        //                 "total_commission_amount_usd" => $converted_total_commission_usd,
        //             );
        //         }
        //         else{
        //             $total_commission_amount = $wallet_type_total_commission[$wallet_type]["total_commission_amount"];
        //             $total_commission_amount_usd = $wallet_type_total_commission[$wallet_type]["total_commission_amount_usd"];
    
        //             $new_total_commission_amount = bcadd($total_commission_amount, $transaction_fee, 8);
        //             $new_total_commission_amount_usd = bcadd($total_commission_amount_usd, $converted_total_commission_usd, 2);
        //             $wallet_type_total_commission_arr = array(
        //                 "total_commission_amount" => $new_total_commission_amount,
        //                 "total_commission_amount_usd" => $new_total_commission_amount_usd
        //             );
    
        //             $wallet_type_total_commission[$wallet_type] = $wallet_type_total_commission_arr;
        //         }
                
        //         if(!in_array($wallet_type, $transacted_wallet_type)){
        //             array_push($transacted_wallet_type, $wallet_type);
        //         }
    
        //     }
    
        //     foreach($transacted_wallet_type as $wallet_value){
        //         $symbol = strtoupper($marketplace_currencies[$wallet_value]['symbol']);
        //         $transacted_symbol_arr[] = $symbol;
    
        //         $wallet_type_total_transaction[$wallet_value]['symbol'] = $symbol;
        //         $wallet_type_total_commission[$wallet_value]['symbol'] = $symbol;
    
        //     }
        //     $wallet_type_total_transaction = array_values($wallet_type_total_transaction);
        //     $wallet_type_total_commission = array_values($wallet_type_total_commission);
        //     $transacted_symbol = implode(', ',$transacted_symbol_arr);
        //     $merchant_details = array(
        //         "owner_name" => $business_owner_name,
        //         "owner_mobile" => $business_owner_mobile,
        //         "business_id" => $business_id,
        //         "name" => $business_name,
        //         "address" => $business_address? $business_address : '',
        //         "website" => $business_website ? $business_website : '',
        //         "phone_number" => $business_phone_number ? $business_phone_number : '',
        //         "country" => $ip_country ? $ip_country : '', 
        //         "company_size" => $business_company_size ? $business_company_size : '',
        //         "created_at" => $business_created_at,
        //         "total_transaction" => $total_transaction,
        //         "total_transaction_by_status" => $total_transaction_by_status,
        //         "total_transaction_amount_per_coin" => $wallet_type_total_transaction,
        //         "total_transaction_usd" => $total_transaction_usd,
        //         "total_commission" => $total_commission_usd,
        //         "total_commission_per_coin" => $wallet_type_total_commission,
        //         "commission_rate" => $service_charge_rate ? $service_charge_rate : '0.2',
        //         "transacted_symbol" => $transacted_symbol,
    
        //     );
    
        //     return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00276') /*Admin Nuxpay Merchant Details.*/, 'data' => $merchant_details);
    
        // }

        public function reseller_nuxpay_merchant_details($params){
            global $xunCurrency;
            $db = $this->db;
            $setting = $this->setting;
    
            $business_id           = $params["business_id"];
    
            if($business_id == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/, 'developer_msg' => 'Business ID cannot be empty.');
            }
    
            $col = 'a.id, a.nickname, a.service_charge_rate,  a.created_at, a.username, a.email, c.website, c.phone_number, c.company_size, c.address1, c.address2, c.city, c.state, c.postal, c.country, IF(r.username is null, "-", r.username) as reseller_name';
            $db->where('a.id', $business_id);
            $db->join("reseller r", "r.id=a.reseller_id", "LEFT");
            $db->join('xun_business c', 'c.user_id = a.id', 'LEFT');
            $db->join('xun_business_account b', 'b.user_id = a.id', 'LEFT');
            $db->orderBy('a.id', 'DESC');
            $copyDb = $db->copy();
            $nuxpay_user = $db->getOne('xun_user a', $col);
     
            if(!$nuxpay_user){
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
            }
    
            $business_owner_mobile = $nuxpay_user['username'];
            $business_owner_email = $nuxpay_user['email'];
            $business_name = $nuxpay_user['nickname'];
            $reseller = $nuxpay_user['reseller_name'];
            $address1 = $nuxpay_user['address1'];
            $address2 = $nuxpay_user['address2'];
            $city = $nuxpay_user['city'];
            $state = $nuxpay_user['state'];
            $postal = $nuxpay_user['postal'];
            $country = $nuxpay_user['country'];
            $business_website = $nuxpay_user['website'];
            $business_phone_number = $nuxpay_user['phone_number'];
            $business_company_size = $nuxpay_user['company_size'];
            $business_created_at = $nuxpay_user['created_at'];
            $service_charge_rate = $nuxpay_user['service_charge_rate'];
    
            if($address1){
                $address_arr[] = $address1;
            }
    
            if($address2){
                $address_arr[] = $address2;
            }
    
            if($postal){
                $address_arr[] = $postal;
            }
    
            if($city){
                $address_arr[] = $city;
            }
    
            if($state){
                $address_arr[] = $state;
            }
    
            if($country){
                $address_arr[] = $country;
            }
    
            if($address_arr){
                $business_address = implode(", ", $address_arr);
            }
            
            $db->where('username', $business_owner_mobile);
            $business_owner = $db->getOne('xun_user');
            $business_owner_name = $business_owner['nickname'];
    
            $db->where('user_id', $business_id);
            $db->where('name', 'ipCountry');
            $user_setting = $db->getOne('xun_user_setting');
            $ip_country = $user_setting['value'];
            
            $db->where('business_id', $business_id);
            $crypto_history = $db->get('xun_crypto_history');
    
            $marketplace_currencies = $xunCurrency->get_cryptocurrency_list('a.id, a.currency_id, a.symbol');
            $crypto_rate = $xunCurrency->get_cryptocurrency_rate_with_stable_coin($marketplace_currencies);
    
            $total_transaction = 0;
            $total_transaction_usd = '0.00';
            $total_commission_usd = '0.00';
            $transacted_wallet_type= [];
            $wallet_type_total_transaction = [];
            foreach($crypto_history as $key => $value){
                $wallet_type = strtolower($value['wallet_type']);
                $amount_receive = $value['amount_receive'];
                $transaction_fee = $value['transaction_fee'];
                $status = ucfirst($value['status']);
    
                if(!$total_transaction_by_status[$status]){
                    $total_transaction_by_status[$status] = 1;
                }
                else{
                    $total_transaction_by_status[$status]++;
                }
    
                $converted_total_transaction_usd =  bcmul($amount_receive, $crypto_rate[$wallet_type], 2);
                $converted_total_commission_usd =  bcmul($transaction_fee, $crypto_rate[$wallet_type], 2);
    
                $total_transaction_usd = bcadd($total_transaction_usd, $converted_total_transaction_usd, 2);
                $total_commission_usd = bcadd($total_commission_usd, $converted_total_commission_usd, 2);
    
                $total_transaction++;
    
                if(!$wallet_type_total_transaction[$wallet_type]){
                    $wallet_type_total_transaction[$wallet_type] = array(
                        "total_amount" => $amount_receive,
                        "total_amount_usd" => $converted_total_transaction_usd,
                    );
                }
                else{
                    $total_amount = $wallet_type_total_transaction[$wallet_type]["total_amount"];
                    $total_amount_usd = $wallet_type_total_transaction[$wallet_type]["total_amount_usd"];
    
                    $new_total_amount = bcadd($total_amount, $amount_receive, 8);
                    $new_total_amount_usd = bcadd($total_amount_usd, $converted_total_transaction_usd, 2);
                    $wallet_type_total_transaction_arr = array(
                        "total_amount" => $new_total_amount,
                        "total_amount_usd" => $new_total_amount_usd
                    );
    
                    $wallet_type_total_transaction[$wallet_type] = $wallet_type_total_transaction_arr;
                }
    
                if(!$wallet_type_total_commission[$wallet_type]){
                    $wallet_type_total_commission[$wallet_type] = array(
                        "total_commission_amount" => $transaction_fee,
                        "total_commission_amount_usd" => $converted_total_commission_usd,
                    );
                }
                else{
                    $total_commission_amount = $wallet_type_total_commission[$wallet_type]["total_commission_amount"];
                    $total_commission_amount_usd = $wallet_type_total_commission[$wallet_type]["total_commission_amount_usd"];
    
                    $new_total_commission_amount = bcadd($total_commission_amount, $transaction_fee, 8);
                    $new_total_commission_amount_usd = bcadd($total_commission_amount_usd, $converted_total_commission_usd, 2);
                    $wallet_type_total_commission_arr = array(
                        "total_commission_amount" => $new_total_commission_amount,
                        "total_commission_amount_usd" => $new_total_commission_amount_usd
                    );
    
                    $wallet_type_total_commission[$wallet_type] = $wallet_type_total_commission_arr;
                }
                
                if(!in_array($wallet_type, $transacted_wallet_type)){
                    array_push($transacted_wallet_type, $wallet_type);
                }
    
            }
    
            foreach($transacted_wallet_type as $wallet_value){
                $symbol = strtoupper($marketplace_currencies[$wallet_value]['symbol']);
                $transacted_symbol_arr[] = $symbol;
    
                $wallet_type_total_transaction[$wallet_value]['symbol'] = $symbol;
                $wallet_type_total_commission[$wallet_value]['symbol'] = $symbol;
    
            }
            $wallet_type_total_transaction = array_values($wallet_type_total_transaction);
            $wallet_type_total_commission = array_values($wallet_type_total_commission);
            $transacted_symbol = implode(', ',$transacted_symbol_arr);
            $merchant_details = array(
                "owner_name" => $business_owner_name,
                "owner_mobile" => $business_owner_mobile ? $business_owner_mobile : '-',
                'owner_email' => $business_owner_email ? $business_owner_email : '-',
                "reseller" => $reseller,
                "business_id" => $business_id,
                "name" => $business_name,
                "address" => $business_address? $business_address : '',
                "website" => $business_website ? $business_website : '',
                "phone_number" => $business_phone_number ? $business_phone_number : '',
                "country" => $ip_country ? $ip_country : '', 
                "company_size" => $business_company_size ? $business_company_size : '',
                "created_at" => $business_created_at,
                "total_transaction" => $total_transaction,
                "total_transaction_by_status" => $total_transaction_by_status,
                "total_transaction_amount_per_coin" => $wallet_type_total_transaction,
                "total_transaction_usd" => $total_transaction_usd,
                "total_commission" => $total_commission_usd,
                "total_commission_per_coin" => $wallet_type_total_commission,
                "commission_rate" => $service_charge_rate ? $service_charge_rate : '0.2',
                "transacted_symbol" => $transacted_symbol,
    
            );
            
            // return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00276') /*Admin Nuxpay Merchant Details.*/, 'data' => $merchant_details);
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00303') /*Reseller Merchant Details.*/, 'data' => $merchant_details);
    
        }

        public function reseller_nuxpay_get_miner_fee_report($params,$userID){
            global $xunCurrency;
            $db = $this->db;
            $setting = $this->setting;
    
            $to_datetime           = $params["date_to"];
            $from_datetime         = $params["date_from"];
            $business_id           = $params["business_id"];
            $business_name         = $params["business_name"];
            $tx_hash               = $params['tx_hash'];
            $status                = $params['status'];
            $type                  = $params['type'];
    
            if($type == '' && $to_datetime == '' && $from_datetime == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00504') /*Searching parameter is required.*/, 'developer_msg' => 'Searching parameter is required.');
            }
           

            $arr_reseller_id = $this->get_reseller($userID);

            if($from_datetime){
                $from_datetime = date("Y-m-d H:i:s", $from_datetime);
                $db->where('a.created_at', $from_datetime, '>');
            }
    
            if($to_datetime){
                $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
                $db->where('a.created_at' , $to_datetime, '<=');
            }
    
            $miner_fee_arr = []; //miner fee in usd
            $miner_fee_fund_out_arr = []; //miner fee that actually company spent
            $miner_fee_collected = []; //miner fee that actually collected from transaction
            if($type == "marketer_fundout"){
    
                $db->where('a.address_type', 'marketer');
                $db->where('a.status', 'completed');
                
                // filter xun_user to corresponding reseller  
                $db->where('b.reseller_id', $arr_reseller_id, "IN");
                
                $db->join('xun_user b', 'a.user_id = b.id', 'INNER');
                $xun_wallet_transaction = $db->get('xun_wallet_transaction a');
    
                if(!$xun_wallet_transaction) {
                    return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
                }
    
                $feeUnitArr = [];
                foreach($xun_wallet_transaction as $key => $value){
                    $fee = $value['fee'];
                    $fee_unit = strtolower($value['fee_unit']);
    
                    if(!in_array($fee_unit, $feeUnitArr)){
                        array_push($feeUnitArr, $fee_unit);
                    }
                }
                
                $db->where('symbol', $feeUnitArr, 'in');
                $marketplace_currencies = $db->map('symbol')->ArrayBuilder()->get('xun_marketplace_currencies');
    
                foreach($xun_wallet_transaction as $key => $value){
                    $fee = $value['fee'];
                    $fee_unit = strtolower($value['fee_unit']);
                    $miner_fee_wallet_type = $marketplace_currencies[$fee_unit]['currency_id'];
                    $miner_fee_exchange_rate = $value['miner_fee_exchange_rate'];
    
                    $exchange_rate = $value['exchange_rate'];
                    $wallet_type = $value['wallet_type'];
                    $amount = $value['amount'];
                    $created_at = $value['created_at'];
    
                    if($miner_fee_wallet_type == 'ethereum'){
                        $decimal_places = 18;
                    }
                    else{
                        $decimal_places = $xunCurrency->get_currency_decimal_places($miner_fee_wallet_type);
                    }
                    
                    $miner_fee_value = bcmul($fee, $miner_fee_exchange_rate, 4);
    
                    if($miner_fee_arr[$miner_fee_wallet_type]){
                        $total_miner_fee = $miner_fee_arr[$miner_fee_wallet_type];
                        $new_miner_fee_amount = bcadd($total_miner_fee, $miner_fee_value, 4);
                        $miner_fee_arr[$miner_fee_wallet_type] = $new_miner_fee_amount;
                    }
                    else{
                        $miner_fee_arr[$miner_fee_wallet_type] = $miner_fee_value;
                    }
       
                    if($miner_fee_fund_out_arr[$miner_fee_wallet_type]){
                        $total_miner_fee_fund_out = $miner_fee_fund_out_arr[$miner_fee_wallet_type];
                        $new_miner_fee_amount = bcadd($total_miner_fee_fund_out, $fee, $decimal_places);
                        $miner_fee_fund_out_arr[$miner_fee_wallet_type] = $new_miner_fee_amount;
                    }
                    else{
                        $miner_fee_fund_out_arr[$miner_fee_wallet_type] = $fee;
                    }
                   
                    if($miner_fee_collected[$wallet_type]){
                        if($miner_fee_wallet_type == $wallet_type){
                            $total_miner_fee_collected = $miner_fee_collected[$wallet_type];
                            $new_miner_fee_collected_amount = bcadd($total_miner_fee_collected, $fee, $decimal_places);
                            $miner_fee_collected[$wallet_type] = $new_miner_fee_collected_amount;
                        }
                        else{
                            $miner_fee_native_value = bcdiv($miner_fee_value, $exchange_rate, $decimal_places); 
                            $total_miner_fee_collected = $miner_fee_collected[$wallet_type];
                            $new_miner_fee_collected_amount = bcadd($total_miner_fee_collected, $miner_fee_native_value, $decimal_places);
                        
                            $miner_fee_collected[$wallet_type] = $new_miner_fee_collected_amount;
                        }
                        
                    }
                    else{
              
                        if($miner_fee_wallet_type == $wallet_type){
                            $miner_fee_collected[$wallet_type] = $fee;
                        }
                        else{
                            $miner_fee_native_value = bcdiv($miner_fee_value, $exchange_rate, 18); 
                            $miner_fee_collected[$wallet_type] = $miner_fee_native_value;
                        }  
                    }
    
                    $miner_fee_array= array(
                        "amount" => $amount,
                        "wallet_type" => $wallet_type,
                        "miner_fee" => $fee,
                        "miner_fee_value" => $miner_fee_value,
                        "miner_fee_wallet_type" => $miner_fee_wallet_type,
                        "created_at" => $created_at,
                    );
                    $miner_fee_listing[] = $miner_fee_array;
    
                }
    
                $data = array(
                    "total_miner_fee_value" => $miner_fee_arr,
                    "total_miner_fee_fund_out" => $miner_fee_fund_out_arr,
                    "total_miner_fee_collected" => $miner_fee_collected,
                    "listing" => $miner_fee_listing,
                );
                
            } elseif($type == 'blockchain_fundout'){
    
                $db->where('a.status', 'confirmed');

                // filter xun_user to corresponding reseller  
                $db->where('b.reseller_id', $arr_reseller_id, "IN");                
                $db->join('xun_user b', 'a.business_id = b.id', 'INNER');

                $crypto_fund_out_details = $db->get('xun_crypto_fund_out_details a');
    
                $feeUnitArr = [];
                foreach($crypto_fund_out_details as $key => $value){
                    $miner_fee_amount = $value['pool_amount'];
                    $transaction_details = json_decode($value['transaction_details'], true);
                    $miner_fee_exchange_rate = $transaction_details['feeDetails']['exchangeRate']['USD'];
                    $exchange_rate = $transaction_details['feeChargeDetails']['exchangeRate']['USD'];
                    $feeUnit = $transaction_details['feeDetails']['unit'];
    
                    if(!in_array($feeUnit, $feeUnitArr)){
                        array_push($feeUnitArr, $feeUnit);
                    }
    
                }
    
                if($feeUnitArr){
                    $db->where('symbol', $feeUnitArr, 'in');
                    $marketplace_currencies = $db->map('symbol')->ArrayBuilder()->get('xun_marketplace_currencies');   
                }
          
                foreach($crypto_fund_out_details as $k => $v){
    
                    $miner_fee_amount = $v['pool_amount'];
                    $transaction_details = json_decode($value['transaction_details'], true);
                    $miner_fee_exchange_rate = $transaction_details['feeDetails']['exchangeRate']['USD'];
                    $exchange_rate = $transaction_details['feeChargeDetails']['exchangeRate']['USD'];
                    $feeUnit = strtolower($transaction_details['feeDetails']['unit']);
    
                    $miner_fee_wallet_type = $marketplace_currencies[$feeUnit]['currency_id'];
    
                    $wallet_type = $v['wallet_type'];
    
                    $miner_fee_value = bcmul($miner_fee_amount, $exchange_rate, 4);
    
                    if($miner_fee_arr[$miner_fee_wallet_type]){
                        $total_miner_fee = $miner_fee_arr[$miner_fee_wallet_type];
                        $new_miner_fee_amount = bcadd($total_miner_fee, $miner_fee_value, 4);
                        
                        $miner_fee_arr[$miner_fee_wallet_type] = $new_miner_fee_amount;
                    }
                    else{
                        $miner_fee_arr[$miner_fee_wallet_type] = $miner_fee_value;
                    }
       
                    $fund_out_miner_fee = bcdiv($miner_fee_value, $miner_fee_exchange_rate, 18);
                    if($miner_fee_fund_out_arr[$miner_fee_wallet_type]){
                        $total_miner_fee_fund_out = $miner_fee_fund_out_arr[$miner_fee_wallet_type];
                        $new_miner_fee_amount = bcadd($total_miner_fee_fund_out, $fund_out_miner_fee, 18);
                        $miner_fee_fund_out_arr[$miner_fee_wallet_type] = $new_miner_fee_amount;
                    }
                    else{
                        $miner_fee_fund_out_arr[$miner_fee_wallet_type] = $fund_out_miner_fee;
                    }
    
                    if($miner_fee_collected[$wallet_type]){
                        $total_miner_fee_collected = $miner_fee_collected[$wallet_type];
                        $new_miner_fee_collected_amount = bcadd($total_miner_fee_collected, $miner_fee_amount, 18);
                        $miner_fee_collected[$wallet_type] = $new_miner_fee_collected_amount;
                        
                    }
                    else{
                        $miner_fee_collected[$wallet_type] = $miner_fee_amount;
                    
                    }
    
                    $data = array(
                        "total_miner_fee_value" => $miner_fee_arr,
                        "total_miner_fee_fund_out" => $miner_fee_fund_out_arr,
                        "total_miner_fee_collected" => $miner_fee_collected,
                    );
                }
    
            }
            elseif ($type == 'pg_fundout'){
               
                $db->where('a.recipient_external', '', '!=');
                $db->where('a.status', 'success');

                // filter xun_user to corresponding reseller  
                $db->where('b.reseller_id', $arr_reseller_id, "IN");                
                $db->join('xun_user b', 'a.business_id = b.id', 'INNER');

                $crypto_history = $db->get('xun_crypto_history a');
    
                foreach($crypto_history as $key => $value){
                    $miner_fee = $value['miner_fee'];
                    $miner_fee_wallet_type = strtolower($value['miner_fee_wallet_type']);
                    $exchange_rate = $value['exchange_rate'];
                    $miner_fee_exchange_rate = $value['miner_fee_exchange_rate'];
                    $wallet_type = strtolower($value['wallet_type']);
                    $actual_miner_fee_amount = $value['actual_miner_fee_amount'] ? $value['actual_miner_fee_amount'] : $value['miner_fee'];
                    $actual_miner_fee_wallet_type = strtolower($value['actual_miner_fee_wallet_type']) ? strtolower($value['actual_miner_fee_wallet_type']) : $miner_fee_wallet_type;
                    $actual_miner_fee_exchange_rate = $value['miner_fee_exchange_rate'] ;
                    
                    $miner_fee_value = bcmul($miner_fee, $exchange_rate, 4);
    
                    if($miner_fee_arr[$miner_fee_wallet_type]){
                        $total_miner_fee = $miner_fee_arr[$miner_fee_wallet_type];
                        $new_miner_fee_amount = bcadd($total_miner_fee, $miner_fee_value, 4);
                        $miner_fee_arr[$miner_fee_wallet_type] = $new_miner_fee_amount;
                    }
                    else{
                        $miner_fee_arr[$miner_fee_wallet_type] = $miner_fee_value;
                    }
    
                    // $fund_out_miner_fee = bcdiv($miner_fee_value, $miner_fee_exchange_rate, 18);
    
                    if($actual_miner_fee_wallet_type){
                        if($miner_fee_fund_out_arr[$actual_miner_fee_wallet_type]){
                            $total_miner_fee_fund_out = $miner_fee_fund_out_arr[$actual_miner_fee_wallet_type];
                            $new_miner_fee_amount = bcadd($total_miner_fee_fund_out, $actual_miner_fee_amount, 18);
                            $miner_fee_fund_out_arr[$actual_miner_fee_wallet_type] = $new_miner_fee_amount;
                        }
                        else{
                            $miner_fee_fund_out_arr[$actual_miner_fee_wallet_type] = $actual_miner_fee_amount;
                        }
                    }
                    
    
                    if($miner_fee_collected[$wallet_type]){
                        $total_miner_fee_collected = $miner_fee_collected[$wallet_type];
                        $new_miner_fee_collected_amount = bcadd($total_miner_fee_collected, $miner_fee, 18);
                        $miner_fee_collected[$wallet_type] = $new_miner_fee_collected_amount;
                        
                    }
                    else{
                        $miner_fee_collected[$wallet_type] = $miner_fee;
                    
                    }
    
                    $data = array(
                        "total_miner_fee_value" => $miner_fee_arr,
                        "total_miner_fee_fund_out" => $miner_fee_fund_out_arr,
                        "total_miner_fee_collected" => $miner_fee_collected,
                    );
    
                }
            }  
                
            // return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00291') /*Admin Miner Fee Report.*/, 'data' => $data);
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00304') /*Reseller Miner Fee Report.*/, 'data' => $data);
    
        }
        
        public function nuxpay_get_miner_fee_details($params,$userID){
            global $xunCurrency;
            $db = $this->db;
            $setting = $this->setting;
    
            $reseller_page_limit   = $setting->getResellerPageLimit();
            $page_number           = $params["page"];
            $page_size             = $params["page_size"] ? $params["page_size"] : $reseller_page_limit;
            $to_datetime           = $params["date_to"];
            $from_datetime         = $params["date_from"];
            $business_id           = $params["business_id"];
            $type                  = $params['type'];
    
            if ($page_number < 1) {
                $page_number = 1;
            }
    
            $start_limit = ($page_number - 1) * $page_size;
            $limit       = array($start_limit, $page_size);
    

            $arr_reseller_id = $this->get_reseller($userID);


            if($from_datetime){
                $from_datetime = date("Y-m-d H:i:s", $from_datetime);
                $db->where('a.created_at', $from_datetime, '>');
            }
    
            if($to_datetime){
                $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
                $db->where('a.created_at' , $to_datetime, '<=');
            }
    
            if($type == "marketer_fundout"){
    
                $db->where('a.address_type', 'marketer');
                $db->where('a.status', 'completed');

                // filter xun_user to corresponding reseller  
                $db->where('b.reseller_id', $arr_reseller_id, "IN");
                $db->join('xun_user b', 'a.user_id = b.id', 'INNER');

                $copyDb= $db->copy();
                $db->orderBy('a.id' , "DESC");
                $xun_wallet_transaction = $db->get('xun_wallet_transaction a', $limit, 'a.*');
    
                $totalRecord = $copyDb->getValue('xun_wallet_transaction a', 'count(a.id)');
                $feeUnitArr = [];
                foreach($xun_wallet_transaction as $key => $value){
                    $fee = $value['fee'];
                    $fee_unit = strtolower($value['fee_unit']);
    
                    if(!in_array($fee_unit, $feeUnitArr)){
                        array_push($feeUnitArr, $fee_unit);
                    }
                }
                
                $db->where('symbol', $feeUnitArr, 'in');
                $marketplace_currencies = $db->map('symbol')->ArrayBuilder()->get('xun_marketplace_currencies');
    
                $miner_fee_arr = [];
                $miner_fee_fund_out_arr = [];
                $miner_fee_collected = [];
                foreach($xun_wallet_transaction as $key => $value){
                    $fee = $value['fee'];
                    $fee_unit = strtolower($value['fee_unit']);
                    $miner_fee_wallet_type = $marketplace_currencies[$fee_unit]['currency_id'];
                    $miner_fee_exchange_rate = $value['miner_fee_exchange_rate'];
    
                    $exchange_rate = $value['exchange_rate'];
                    $wallet_type = $value['wallet_type'];
                    $amount = $value['amount'];
                    $created_at = $value['created_at'];
    
    
                    if($miner_fee_wallet_type == 'ethereum'){
                        $decimal_places = 18;
                    }
                    else{
                        $decimal_places = $xunCurrency->get_currency_decimal_places($miner_fee_wallet_type);
                    }
    
                    $miner_fee_value = bcmul($fee, $miner_fee_exchange_rate, 4);
        
                    $miner_fee_array= array(
                        "amount" => $amount,
                        "wallet_type" => $wallet_type,
                        "miner_fee" => $fee,
                        "miner_fee_value" => $miner_fee_value,
                        "miner_fee_wallet_type" => $miner_fee_wallet_type,
                        "created_at" => $created_at,
                    );
                    $miner_fee_listing[] = $miner_fee_array;
    
                }
            
        }
        elseif($type == 'blockchain_fundout'){
            $db->where('a.status', 'confirmed');

            // filter xun_user to corresponding reseller  
            $db->where('b.reseller_id', $arr_reseller_id, "IN");                
            $db->join('xun_user b', 'a.business_id = b.id', 'INNER');
            $db->orderBy('a.created_at', 'desc');
            $copyDb = $db->copy();
            $db->orderBy('a.id', 'desc');
            $crypto_fund_out_details = $db->get('xun_crypto_fund_out_details a', $limit, 'a.*');            
            $totalRecord = $copyDb->getValue('xun_crypto_fund_out_details a', 'count(a.id)');
    
            $feeUnitArr = [];
            foreach($crypto_fund_out_details as $key => $value){
                $miner_fee_amount = $value['pool_amount'];
                $transaction_details = json_decode($value['transaction_details'], true);
                $miner_fee_exchange_rate = $transaction_details['feeDetails']['exchangeRate']['USD'];
                $exchange_rate = $transaction_details['feeChargeDetails']['exchangeRate']['USD'];
                $feeUnit = $transaction_details['feeDetails']['unit'];
    
                if(!in_array($feeUnit, $feeUnitArr)){
                    array_push($feeUnitArr, $feeUnit);
                }
    
            }
            $db->where('symbol', $feeUnitArr, 'in');
            $marketplace_currencies = $db->map('symbol')->ArrayBuilder()->get('xun_marketplace_currencies');    
    
            $miner_fee_arr = [];
            $miner_fee_fund_out_arr = [];
            $miner_fee_collected = [];
      
            foreach($crypto_fund_out_details as $k => $v){
    
                $miner_fee_amount = $v['pool_amount'];
                $transaction_details = json_decode($value['transaction_details'], true);
                $miner_fee_exchange_rate = $transaction_details['feeDetails']['exchangeRate']['USD'];
                $exchange_rate = $transaction_details['feeChargeDetails']['exchangeRate']['USD'];
                $feeUnit = strtolower($transaction_details['feeDetails']['unit']);
                $amount = $v['amount'];
                $created_at = $v['created_at'];
    
                $miner_fee_wallet_type = $marketplace_currencies[$feeUnit]['currency_id'];
    
                $wallet_type = $v['wallet_type'];
    
                $miner_fee_value = bcmul($miner_fee_amount, $exchange_rate, 2);
                $fee = bcdiv($miner_fee_value, $miner_fee_exchange_rate, 18);
    
                $miner_fee_array= array(
                    "amount" => $amount,
                    "wallet_type" => $wallet_type,
                    "miner_fee" => $fee,
                    "miner_fee_value" => $miner_fee_value,
                    "miner_fee_wallet_type" => $miner_fee_wallet_type,
                    "created_at" => $created_at,
    
                );
                $miner_fee_listing[] = $miner_fee_array;
    
            }  
    
        } elseif($type == 'pg_fundout'){
            $db->where('a.recipient_external', '', '!=');
            $db->where('a.status', 'success');

            // filter xun_user to corresponding reseller  
            $db->where('b.reseller_id', $arr_reseller_id, "IN");                
            $db->join('xun_user b', 'a.business_id = b.id', 'INNER');            
            $db->orderBy('a.created_at', 'desc');
            $copyDb= $db->copy();
            $db->orderBy('a.id', 'DESC');
            $crypto_history = $db->get('xun_crypto_history a', $limit, 'a.*');
    
            $totalRecord = $copyDb->getValue('xun_crypto_history a', 'count(a.id)');
    
            foreach($crypto_history as $key => $value){
                $amount = $value['amount_receive'];
                $wallet_type = $value['wallet_type'];
                $miner_fee = $value['actual_miner_fee_amount'] ? $value['actual_miner_fee_amount'] : $value['miner_fee'];
                $miner_fee_wallet_type = $value['actual_miner_fee_wallet_type'] ? strtolower($value['actual_miner_fee_wallet_type']) : strtolower($value['miner_fee_wallet_type']);
                $miner_fee_exchange_rate = $value['miner_fee_exchange_rate'];
    
                $miner_fee_value = bcmul($miner_fee, $miner_fee_exchange_rate, 4);
                $created_at = $value['created_at'];
         
                $miner_fee_array= array(
                    "amount" => $amount,
                    "wallet_type" => $wallet_type,
                    "miner_fee" => $miner_fee,
                    "miner_fee_value" => $miner_fee_value,
                    "miner_fee_wallet_type" => $miner_fee_wallet_type,
                    "created_at" => $created_at,
                );
    
                $miner_fee_listing[] = $miner_fee_array;
            }
            
        }
            $data = array(
                "listing" => $miner_fee_listing,
                "totalRecord" => $totalRecord,
                "numRecord" => $page_size,
                "totalPage" => ceil($totalRecord/$page_size),
                "pageNumber" => $page_number,
            );

            // return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00292') /*Admin Miner Fee Details.*/, 'data' => $data);
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00305') /*Reseller Miner Fee Details.*/, 'data' => $data);
            
        }

        public function distributor_listing($params, $userID) {

            $db = $this->db;
            $setting = $this->setting;
            
            $reseller_page_limit    = $setting->getResellerPageLimit();
            $distributor_Name       = $params["name"];
            $distributor_Username   = $params["username"];
            $email                  = $params["email"];
            $mobile_number          = $params["mobile"];
            $site                   = $params["site"];
            $distributor            = $params["distributor"];
            $page_number            = $params["page"];
            $page_size              = $params["page_size"] ? $params["page_size"] : $reseller_page_limit;

            if ($page_number < 1) {
                $page_number = 1;
            }

            $start_limit = ($page_number - 1) * $page_size;
            $limit       = array($start_limit, $page_size);

            // $db->where("username", $distributor, 'like');
            // $check_distributor = $db->getOne("reseller", "username");
            
            
                       

            $db->where("id", $userID);
            $db->where("type", "siteadmin");
            $db->where("deleted", 0);
            $siteAdminDetail = $db->getOne("reseller", "source");

            if($siteAdminDetail) {

                $source = $siteAdminDetail['source'];

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

                $db->where("r.source", $source);
                $db->where("r.type", "distributor");
                $db->where("r.deleted", 0);
                $db->join("xun_user u", "r.user_id = u.id", "INNER");
                $copyDb = $db->copy();
                $distributorListing = $db->get("reseller r", $limit, "r.id, r.username, r.distributor_id, r.name, r.email, r.source, r.created_at, r.username as distributor_username, IF(u.username = ' ', '-', u.username) as xun_user_username");
                $totalRecord = $copyDb->getValue("reseller r", "count(r.id)");
                
                if(!$distributorListing){
                return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/, 'data' => ''); 
                }
                //$totalRecord = count($distributorListing);

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

                $returnData["distributor_listing"] = $distributorList;
                $returnData["totalRecord"]      = $totalRecord;
                $returnData["numRecord"]        = $page_size;
                $returnData["totalPage"]        = ceil($totalRecord/$page_size);
                $returnData["pageNumber"]       = $page_number;
                //post_man
                // $test = array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00307') /*Reseller Details*/, 'data' => $returnData);
        
                // echo json_encode($test);
                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00306') /*Reseller Listing*/, 'data' => $returnData);

            } else {

                return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/, 'data' => '');

            }

        }

        public function reseller_listing($params, $userID) { 

            $db = $this->db;
            $setting = $this->setting;
            
            $reseller_page_limit= $setting->getResellerPageLimit();
            $reseller           = $params["reseller"];
            $reseller_name      = $params["reseller_name"];
            $reseller_email     = $params["reseller_email"];
            $reseller_number    = $params["reseller_number"];
            $reseller_site      = $params["reseller_site"];
            $distributor        = $params["distributor"];
            $site               = $params['site'];
            $page_number        = $params["page"];
            $page_size          = $params["page_size"] ? $params["page_size"] : $reseller_page_limit;

            if ($page_number < 1) {
                $page_number = 1;
            }

            $start_limit = ($page_number - 1) * $page_size;
            $limit       = array($start_limit, $page_size);
            
     
            $db->where("id", $userID);
            $resellerDetail = $db->getOne("reseller", "source, type");
            
            
            $rsource = $resellerDetail['source'];
            $rtype = $resellerDetail['type'];

            $distributor_id = $this->get_distributor($userID);

            if($reseller) {
                $db->where("r.username", "%$reseller%", "LIKE");
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
            $db->where("r.distributor_id", $distributor_id, "IN");
            $db->where("r.deleted", 0);
            $db->where("r.type", "reseller");
            $db->where("r.source", $rsource);
            $db->join("xun_user u", "r.user_id=u.id", "inner");
            

            if($rtype=="siteadmin") {
                $db->join("reseller r2", "r.distributor_id=r2.id", "left");
            } else {
                $db->join("reseller r2", "r.distributor_id=r2.id", "inner");
            }
            $copyDb= $db->copy();
            $resellerList = $db->get("reseller r", $limit,  "r.id, r.status ,r.username, r.name, r.email, r.disabled, r.created_at, r.source, IF(r2.username is null, '-', r2.username) as distributor_username, IF(u.username = ' ', '-', u.username) as xun_user_username");
            // if(!$resellerList){
            //      return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/);
            // }

            $totalRecord = $copyDb->getValue('reseller r', 'count("id")');


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
                $reseller_status = $resellerValue["status"];
                $reseller_username = $resellerValue["username"];
                $reseller_name = $resellerValue["name"];
                $reseller_email = $resellerValue["email"];
                $reseller_createdAt = $resellerValue["created_at"];
                $reseller_source = $resellerValue["source"];
                $reseller_distributorName = $resellerValue["distributor_username"];
                $reseller_xuUsername = $resellerValue["xun_user_username"];
                if($resellerValue['disabled']==0){
                    $reseller_status = "Active";
                }
                if($resellerValue['disabled']==1){
                    $reseller_status = "Suspended";
                }
                if($resellerValue['status']=="pending")
                {
                    $reseller_status = "Pending";
                }
            
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
                    "reseller_status" => $reseller_status
                );
                $newResellerList[$reseller_id] = $totalMerch_arr;
               

            }

            $returnData["reseller_listing"] = $newResellerList;
            $returnData["totalRecord"]      = $totalRecord;
            $returnData["numRecord"]        = $page_size;
            $returnData["totalPage"]        = ceil($totalRecord/$page_size);
            $returnData["pageNumber"]       = $page_number;
        

            // $test = array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00307') /*Reseller Listing*/, 'data' => $returnData);
            //     echo json_encode($test);
           
            // return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00281') /*Admin Listing.*/, 'data' => $returnData);
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00306') /*Reseller Listing*/, 'data' => $returnData, 'distributor_id'=>$distributor_id);

        }


        public function reseller_merchant_listing($params,$userID){
            $db = $this->db;
            $setting = $this->setting;

            $reseller_page_limit= $setting->getResellerPageLimit();
            $page_number        = $params["page"];
            $page_size          = $params["page_size"] ? $params["page_size"] : $reseller_page_limit;

            if ($page_number < 1) {
                $page_number = 1;
            }

            $start_limit = ($page_number - 1) * $page_size;
            $limit       = array($start_limit, $page_size);
            

            $arr_reseller_id = $this->get_reseller($userID);


            $db->where('u.reseller_id', $arr_reseller_id, "IN"); // Filter user to only corresponding reseller_id
            $db->where('u.type','business');
            $db->orderBy('u.id','DESC');       
            $db->join("reseller r", "r.id=u.reseller_id", "INNER");                
            $users = $db->get('xun_user u', $limit, 'u.id, u.username, u.email, u.role, u.disabled, u.created_at, u.nickname, r.username as reseller_name');

            $db->where('reseller_id', $arr_reseller_id, "IN"); // Filter user to only corresponding reseller_id
            $totalRecord = $db->getValue('xun_user','count(id)');
            $roles = $db->map('id')->ArrayBuilder()->get('roles');

            foreach($users as $key=>$value){
                $roles_id   = $value['role'];
                // $roles_name = $roles[$roles_id]['name'];
                $status     = '';
                $disabled   = $value['disabled'];

                if($disabled == 1){
                    $status = 'disabled';
                }

                $users[$key]['roles_name']  = $roles_name;
                $users[$key]['status']      = $status ? $status : 'active';
                unset($users[$key]['role']);
                unset($users[$key]['disabled']);                
            }

            $returnData["reseller_listing"]    = $users;
            $returnData["totalRecord"]      = $totalRecord;
            $returnData["numRecord"]        = $page_size;
            $returnData["totalPage"]        = ceil($totalRecord/$page_size);
            $returnData["pageNumber"]       = $page_number;
            
            // return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00281') /*Admin Listing.*/, 'data' => $returnData);
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00306') /*Reseller Listing*/, 'data' => $returnData);
        
        }

        public function get_distributor_details($params) {

            $db =$this->db;
        
            $id = $params['id'];
            
            $db->where("id", $id);
            $resellerDetail = $db->getOne("reseller", "id, username, name, email, created_at");

            if(!$resellerDetail){
                return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/);
            } else {
                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00307') /*Reseller Details*/, 'data' => $resellerDetail);
            }

        }

        public function get_reseller_details($params) {

            $db =$this->db;
        
            $id = $params['id'];
            
            $db->where("id", $id);
            $resellerDetail = $db->getOne("reseller", "id, username, name, email, created_at, disabled");

            //get reseller's distributor username & a list of all distributor with the same source usernames
            $db->where("id", $id);
            $distributor_id = $db->getValue("reseller", "distributor_id");
            $distributor_username = $db->where("id", $distributor_id)->getValue("reseller", "username");

            $reseller_source = $db->where("id", $id)->getValue("reseller", "source");
            $distributor_list = $db->where("type", "distributor")->where("source", $reseller_source)->get("reseller", null, "username");
            
            $resellerDetail['distributor_list'] = $distributor_list;
            $resellerDetail['distributor_username'] = $distributor_username;

            if(!$resellerDetail){
                return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/);
            } else {
                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00307') /*Reseller Details*/, 'data' => $resellerDetail);
            }

        }

        public function get_reseller_merchant_details($params){
            $db =$this->db;
        
            $id = $params['id'];
            
            $db->where('id', $id);
            $user = $db->getOne('xun_user');
    
            if(!$user){
                return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/);
            }
    
            $disabled = $user['disabled'];
            // $suspended = $admin['suspended'];
    
            if($disabled == 1){
                $status = 'disable';
            } else{
                $status = 'active';
            }
            $user['status'] = $status;

            // MERCHANT DETAILS
            $db->where("id", $user['reseller_id']);
            $resellerDetail = $db->getOne("reseller", "username, distributor_id");
            $user['reseller_username'] = $resellerDetail['username'];
            $user['distributor_id'] = $resellerDetail['distributor_id'];
            if(!$user['distributor_id']){
                $user['distributor'] = "No Distributor";
            }else{
                // NAME OF DISTRIBUTOR
                $db->where("id", $user['distributor_id']);
                $db->where("deleted", 0);
                $user['distributor'] = $db->getValue("reseller", "username");
            }

            //RESELLER LIST
            if($user['distributor_id'] > 0) {
                $db->where("source", $user['register_site']);
                $db->where("distributor_id", $user['distributor_id']);
                $db->where("type", "reseller");
                $db->where("deleted", 0);
                $user['reseller_list'] = $db->get("reseller", null, "username");
            } else{
                $db->where("distributor_id", 0);
                $db->where("source", $user['register_site']);
                $db->where("type", "reseller");
                $db->where("deleted", 0);
                $user['reseller_list'] = $db->get("reseller", null, "username");
            }

            //DISTRIBUTOR LIST  
            if($user['distributor_id'] >= 0) {
                $db->where("source", $user['register_site']);
                $db->where("type", "distributor");
                $db->where("deleted", 0);
                $user['distributor_list'] = $db->get("reseller", null, "username");
            } else {
                $user['distributor_list'] = array();
            }
            // print_r($user);
            // print_r($distributorDetail);
            // $test = array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00307') /*Reseller Details*/, 'data' => $user);
        
            // echo json_encode($test);
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00307') /*Reseller Details*/, 'data' => $user);
        }

        public function edit_distributor_details($params) {

            $db= $this->db;
    
            $id = $params['id'];
            $username = $params['username'];
            $nickname = $params['nickname'];
            $email = $params['email'];

            if($id == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00006') /*ID cannot be empty.*/, 'developer_msg' => 'ID cannot be empty.');
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

        public function edit_reseller_details($params) {

            $db= $this->db;
    
            $id = $params['id'];
            $username = $params['username'];
            $nickname = $params['nickname'];
            $email = $params['email'];
            $distributor_username = $params['distributor_username'];
            $disabled = $params['disabled'];
            $chkFlag = 0;

            if($id == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00006') /*ID cannot be empty.*/, 'developer_msg' => 'ID cannot be empty.');
            }
    
            if($username == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => 'Username cannot be empty' /*Username cannot be empty.*/, 'developer_msg' => 'Username cannot be empty.');
            }

            if($nickname == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => 'Nickname cannot be empty.' /*Nickname cannot be empty.*/, 'developer_msg' => 'Nickname cannot be empty.');
            }

            if($disabled == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => 'Status cannot be empty.' /*Status cannot be empty.*/, 'developer_msg' => 'Status cannot be empty.');
            }

            if($distributor_username == "null"){
                $distributor_id = 0;
            } else {
                $distributor_id = $db->where("username", $distributor_username)->getValue("reseller", "id");

                // Checking if values are legitimate
                $userSource = $db->where("id", $id)->getValue("reseller", "source");
                $distributorSource = $db->where("id", $distributor_id)->getValue("reseller", "source");
                $distributor_type = $db->where("id", $distributor_id)->getValue("reseller", "type");

                if ($userSource === $distributorSource && $distributor_type === "distributor"){
                    $chkFlag = 1; //legitimate
                }
            }

            $db->where("id", $id);
            $db->where("username", $username);
            if ($chkFlag === 1 || $distributor_id === 0){
                $db->update("reseller", array("distributor_id"=>$distributor_id, "name"=>$nickname, "email"=>$email, "disabled"=>$disabled, "updated_at"=>date("Y-m-d H:i:s")) );
            }
    
            return array("code" => 0, "status" => "ok", "statusMsg" => 'Edit User Details Successful.' /*Edit User Details Successful.*/);
        }

        public function edit_reseller_merchant_details($params, $userID){
            $db= $this->db;
    
            $id = $params['id'];
            $username = $params['username'];
            $nickname = $params['nickname'];
            // $role_id = $params['role'];
            $status = $params['status'];
            $reseller = $params['reseller'];
            //$email = $params['email'];
            $disabled = 0;
            // $suspended = 0;
            // $deleted = 0;
    
            if($id == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00006') /*ID cannot be empty.*/, 'developer_msg' => 'ID cannot be empty.');
            }
    
            if($username == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => 'Username cannot be empty' /*Username cannot be empty.*/, 'developer_msg' => 'Username cannot be empty.');
            }

            if($nickname == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => 'Nickname cannot be empty.' /*Nickname cannot be empty.*/, 'developer_msg' => 'Nickname cannot be empty.');
            }
    
            // if($role_id == ''){
            //     return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00507') /*Roles ID cannot be empty.*/, 'developer_msg' => 'Roles ID cannot be empty.');
            // }
    
            if($status == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00012') /*Status cannot be empty.*/, 'developer_msg' => 'Status cannot be empty.');
            }

            // if($email == ''){
            //     // TODO RESELLER : Language Parser Email cannot be empty
            //     return array('code' => 1, 'status' => "error", 'statusMsg' => 'Email cannot be empty' /*Email cannot be empty.*/, 'developer_msg' => 'Email cannot be empty.');
            // }

            //GET SOURCE
            $db->where("id", $id);
            $regSource = $db->getValue("xun_user", "register_site");

            $db->where("id", $userID);
            $db->where("type", "siteadmin");
            $db->where("deleted", 0);
            $db->where("source", $regSource);
            $saCheck = $db->getOne("reseller");

            if(!$saCheck) {

                if($reseller == ''){
                    return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00533') /*Reseller cannot be empty.*/, 'developer_msg' => 'Reseller cannot be empty.');
                }

            }

            
    
            if($status == "disable"){
                $disabled = 1;
            }
            // elseif($status == "suspend"){
            //     $suspended = 1;
            // }
            
            $db->where('id', $id);
            $user = $db->getOne('xun_user');
    
            if(!$user){
                return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/);
            }

            if($reseller){
                $db->where("username", $reseller);
                $updatedResellerID = $db->getValue("reseller", "id");
            } else {
                $updatedResellerID = 0;
            }

            $update_user_details = array(
                "username"      => $username,
                "nickname"      => $nickname,
                // "role"       => $role_id,
                "disabled"      => $disabled,
                "reseller_id"      => $updatedResellerID,
                //"email"         => $email,
                // "suspended"     => $suspended,
                // "deleted"       => $deleted,
                "updated_at"    => date("Y-m-d H:i:s"),
                
            );
    
            $db->where('id', $id);
            $update_user = $db->update('xun_user', $update_user_details);
    

            $update_business = array("name"=>$nickname);
            $db->where("user_id", $id);
            $db->update("xun_business", $update_business);

            // $update_business_account = array("email"=>$email);
            // $db->where("user_id", $id);
            // $db->update("xun_business_account", $update_business_account);

            if(!$update_user){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
            }
    
            // TODO RESELLER: Language Parser - Edit User Details Successful.
            return array("code" => 0, "status" => "ok", "statusMsg" => 'Edit User Details Successful.' /*Edit User Details Successful.*/);
        }

        public function load_reseller_options($params){
            $db = $this->db;

            $id = $params['id'];
            $distributor = $params['distributor'];

            if($id == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00006') /*ID cannot be empty.*/, 'developer_msg' => 'ID cannot be empty.');
            }
            $db->where('id', $id);
            $user = $db->getOne('xun_user');
    
            if(!$user){
                return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/);
            }

            if($distributor == "No Distributor"){
                $db->where('distributor_id', 0);

                // CHECK IF DATABASE HAS NO DISTRIBUTOR ENTRY
                $checkNoDistributor = $db->getOne('reseller');
                if(!$checkNoDistributor){
                    return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/);
                }

                // GET ALL RESELLER FROM THE SAME DISTRIBUTOR
                $db->where('source', $user['register_site']);
                $db->where('type', 'reseller');
                $db->where('distributor_id', 0);
                $resellerOptions = $db->get("reseller", null, "username");
            }else{
                $db->where('username', $distributor);
                $validDistributor = $db->getOne('reseller');

                if(!$validDistributor){
                    return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/);
                }
                // GET DISTRIBUTOR ID
                $db->where('source', $user['register_site']);
                $db->where('username', $distributor);
                $distributor_id = $db->getValue("reseller", "id");
                
                // GET ALL RESELLER FROM THE SAME DISTRIBUTOR
                $db->where('distributor_id', $distributor_id);
                $resellerOptions = $db->get("reseller", null, "username");
            }

            // $test = array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00307') /*Reseller Details*/, 'data' => $resellerOptions);
        
            // echo json_encode($test);
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00307') /*Reseller Details*/, 'data' => $resellerOptions);
        }

        public function create_distributor_user($params, $userID) {

            global $config;
            $db = $this->db;
            $general = $this->general;
            $setting = $this->setting;
            $post = $this->post;
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
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00404') /*Username cannot be empty.*/, 'developer_msg' => 'Username cannot be empty.');
            }

            if($password == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00014') /*Password cannot be empty.*/, 'developer_msg' => 'Password cannot be empty.');
            }

            if($confirm_password == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00068') /*Confirm password cannot be empty*/, 'developer_msg' => 'Confirm password cannot be empty');
            }
            
            if($nickname == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00506') /*Name cannot be empty.*/, 'developer_msg' => 'Name cannot be empty.');
            }
            
            // if($site == ''){
            //     return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00534') /*Site cannot be empty*/, 'developer_msg' => 'Site cannot be empty.');
            // }
    
            if($email == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00211') /*Email cannot be empty.*/, 'developer_msg' => 'Email cannot be empty.');
            }

            if($password != $confirm_password){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00280') /*Password does not match.*/, 'developer_msg' => 'Password does not match.');
            }
            
            if($email != ""){
                if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
                    return array('code' => 1, 'status' => "error", 'statusMsg' =>  $this->get_translation_message('E00212')  /*Please enter a valid email address.*/);
                }

                $db->where('type', array('distributor', 'reseller'), 'IN');
                $db->where("source", $site);
                $db->where('disabled', 0);
                $db->where('email', $email);
                $reseller_email_detail = $db->getOne('reseller');
    
                if($reseller_email_detail){
                    return array('code' => 1, 'message' => "error", 'statusMsg' => $this->get_translation_message('E00558') /*An account already exists with this email address.*/, 'developer_msg' => 'An account already exists with this email address.');
                }
            }
      
            $db->where("id", $userID);
            $userSource = $db->getValue("reseller", "source");

            if($site != $userSource){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00535') /*Site does not match login source.*/, 'developer_msg' => 'Site does not match login source.');
            }

            if ($mobileNo != ""){
                $mobileNumberInfo = $general->mobileNumberInfo($mobileNo, null);

                if ($mobileNumberInfo["isValid"] == 0){
                    return array('code' => 1, 'message' => "error", 'statusMsg' => $this->get_translation_message('E00046') /*Please enter a valid mobile number.*/, 'developer_msg' => 'Please enter a valid mobile number');
                }
                $mobileNo = "+".$mobileNumberInfo["mobileNumberWithoutFormat"];

                $db->where('type', array('distributor', 'reseller'), 'IN');
                $db->where("username", $mobileNo);
                $db->where("register_site", $site);
                $db->where('disabled', 0);
                $mobileNoExists = $db->getOne("xun_user");

                if ($mobileNoExists){
                    return array('code' => 1, 'message' => "error", 'statusMsg' => $this->get_translation_message('E00536') /*An account already exists with this mobile number. Please select another mobile number.*/, 'developer_msg' => 'An account already exists with this mobile number. Please select another mobile number.');
                }
            }

            $hash_password = password_hash($password, PASSWORD_BCRYPT);


            $db->where("username", $username);
            $resellerDetail = $db->getOne("reseller");
            
            if($resellerDetail) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('B00314') /* This username is not available.*/, "developer_msg" => '');
            }

            $db->where("id", $userID);
            $db->where("type", "siteadmin");
            $distributorDetail = $db->getOne("reseller");

            if($distributorDetail) {

                $register_site = $distributorDetail['source'];

                //INSERT XUN USER
                $userData['server_host'] = $config["server"];
                $userData['type'] = "distributor";
                $userData['register_site'] = $register_site;
                $userData['register_through'] = "SiteAdmin Register";
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

                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00308') /*Reseller Created Successfully.*/);

            } else {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00242')/*Something went wrong.*/, "developer_msg" => '');
            }

        }

        public function create_reseller_user($params, $userID) {

            global $config;
            $db = $this->db;
            $general = $this->general;
            $setting = $this->setting;
            $post = $this->post;
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
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00404') /*Username cannot be empty.*/, 'developer_msg' => 'Username cannot be empty.');
            }

            if($password == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00014') /*Password cannot be empty.*/, 'developer_msg' => 'Password cannot be empty.');
            }

            if($confirm_password == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00068') /*Confirm password cannot be empty*/, 'developer_msg' => 'Confirm password cannot be empty');
            }
            
            if($nickname == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00506') /*Name cannot be empty.*/, 'developer_msg' => 'Name cannot be empty.');
            }
            
            // if($site == ''){
            //     return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00534') /*Site cannot be empty*/, 'developer_msg' => 'Site cannot be empty.');
            // }

            if($email == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' =>$this->get_translation_message('E00211') /*Email cannot be empty.*/, 'developer_msg' => 'Email cannot be empty.');
            }

            if($email != ""){
                if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
                    return array('code' => 1, 'status' => "error", 'statusMsg' =>  $this->get_translation_message('E00212')  /*Please enter a valid email address.*/);
                }

                $db->where('type', array('distributor', 'reseller'), 'IN');
                $db->where("source", $site);
                $db->where('disabled', 0);
                $db->where('email', $email);
                $reseller_email_detail = $db->getOne('reseller');
    
                if($reseller_email_detail){
                    return array('code' => 1, 'message' => "error", 'statusMsg' => $this->get_translation_message('E00558') /*An account already exists with this email address.*/, 'developer_msg' => 'An account already exists with this email address.');
                }
            }
      

            $db->where("id", $userID);
            $userSource = $db->getValue("reseller", "source");

            if($site != $userSource){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00535') /*Site does not match login source.*/, 'developer_msg' => 'Site does not match login source.');
            }

            if ($mobileNo != ""){
                $mobileNumberInfo = $general->mobileNumberInfo($mobileNo, null);

                if ($mobileNumberInfo["isValid"] == 0){
                    return array('code' => 1, 'message' => "error", 'statusMsg' => $this->get_translation_message('E00046') /*Please enter a valid mobile number.*/, 'developer_msg' => 'Please enter a valid mobile number');
                }
                $mobileNo = "+".$mobileNumberInfo["mobileNumberWithoutFormat"];

                $db->where('type', array('distributor', 'reseller'), 'IN');
                $db->where("username", $mobileNo);
                $db->where("register_site", $site);
                $db->where('disabled', 0);
                $mobileNoExists = $db->getOne("xun_user");

                if ($mobileNoExists){
                    return array('code' => 1, 'message' => "error", 'statusMsg' => $this->get_translation_message('E00536') /*An account already exists with this mobile number. Please select another mobile number.*/, 'developer_msg' => 'An account already exists with this mobile number. Please select another mobile number.');
                }
            }

            if($password != $confirm_password){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00280') /*Password does not match.*/, 'developer_msg' => 'Password does not match.');
            }

            $hash_password = password_hash($password, PASSWORD_BCRYPT);


            $db->where("username", $username);
            $db->where('disabled', '0');
            $resellerDetail = $db->getOne("reseller");
            
            if($resellerDetail) {
                return array('code' => 1, 'message' => "error", 'statusMsg' => $this->get_translation_message('B00314') /* This username is not available.*/, "developer_msg" => '');
            }

            $db->where("id", $userID);
            $distributorDetail = $db->getOne("reseller");

            if($distributorDetail) {

                $register_site = $distributorDetail['source'];
                $rType = $distributorDetail['type'];

                if($rType=="distributor") {
                    $distributor_id = $userID;
                } else if($rType=="siteadmin") {

                    if($distributor != "") {

                        $db->where("deleted", 0);
                        $db->where("source", $register_site);
                        $db->where("username", $distributor);
                        $distributorDetail = $db->getOne("reseller");

                        if($distributorDetail) {
                            $distributor_id = $distributorDetail['id'];
                        } else {
                            return array('code' => 1, 'message' => "error", 'statusMsg' => 'Invalid distributor username', "developer_msg" => '');
                        }

                    } else {
                        $distributor_id = 0;
                    }

                } else {
                    return array('code' => 1, 'message' => "error", 'statusMsg' => $this->get_translation_message('E00242')/*Something went wrong.*/, "developer_msg" => '');
                }

                //INSERT MARKETER
                $marketerData['name'] = $username;
                $marketerData['created_at'] = date("Y-m-d H:i:s");
                $marketer_id = $db->insert("xun_marketer", $marketerData);


                //INSERT XUN USER
                $userData['server_host'] = $config["server"];
                $userData['type'] = "reseller";
                $userData['register_site'] = $register_site;
                $userData['register_through'] = "Distributor Register";
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

                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00308') /*Reseller Created Successfully.*/, 'lq'=>$lq);

            } else {
                return array('code' => 1, 'message' => "error", 'statusMsg' => $this->get_translation_message('E00242')/*Something went wrong.*/, "developer_msg" => '');
            }

        }

        public function create_reseller_merchant($params,$userID){
            $db = $this->db;
            $general = $this->general;
            $setting = $this->setting;
            
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
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/, 'developer_msg' => 'Mobile number cannot be empty.');
            }

            if($password == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00014') /*Password cannot be empty.*/, 'developer_msg' => 'Password cannot be empty.');
            }

            if($confirm_password == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00068') /*Confirm password cannot be empty*/, 'developer_msg' => 'Confirm password cannot be empty');
            }
            
            if($nickname == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00506') /*Name cannot be empty.*/, 'developer_msg' => 'Name cannot be empty.');
            }
            
            if($site == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00534') /*Site cannot be empty*/, 'developer_msg' => 'Site cannot be empty.');
            }

            $db->where('source', $site);
            $siteExists = $db->getOne('site');
            if(!$siteExists){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00537') /*Site does not exist.*/, 'developer_msg' => 'Site does not exist.');
            }

            $db->where('name', $country);
            $countryExists = $db->getOne('country');
            if(!$countryExists){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00538') /*Country does not exist.*/, 'developer_msg' => 'Country does not exist.');
            }

            $db->where("id", $userID);
            $userSource = $db->getValue("reseller", "source");

            if($site != $userSource){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00535') /*Site does not match login source.*/, 'developer_msg' => 'Site does not match login source.');
            }
                        
            if($status == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00012') /*Status cannot be empty.*/, 'developer_msg' => 'Status cannot be empty.');
            }
    
            if($password != $confirm_password){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00280') /*Password does not match.*/, 'developer_msg' => 'Password does not match.');
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

            $mobileNumberInfo = $general->mobileNumberInfo($username, null);
            if ($mobileNumberInfo["isValid"] == 0) {

                return array('code' => 1, 'message' => "error", 'statusMsg' => $this->get_translation_message('E00046') /*Please enter a valid mobile number.*/, 'developer_msg' => 'Please enter a valid mobile number');

            }
            
            $username = "+".$mobileNumberInfo["mobileNumberWithoutFormat"];


            $db->where("id", $userID);
            $resellerDetail = $db->getOne("reseller");
            
            if(!$resellerDetail) {
                return array('code' => 0, 'message' => "FAILED", 'statusMsg' => $this->get_translation_message('E00242')/*Something went wrong.*/, "developer_msg" => '');
            } 
            else {
                
                $rType = $resellerDetail['type'];
                $source = $resellerDetail['source'];

                if($rType=="reseller") {

                    $resellerId = $resellerDetail['id'];
                    $marketerId = $resellerDetail['marketer_id'];
                    
                } else if($rType=="distributor") {

                    if($reseller == ''){
                        return array('code' => 1, 'status' => "error", 'statusMsg' => 'Reseller cannot be empty.', 'developer_msg' => 'Reseller cannot be empty.');
                    }

                    $db->where("type", "reseller");
                    $db->where("source", $source);
                    $db->where("username", $reseller);
                    $db->where("distributor_id", $userID);
                    $resellerDetail2 = $db->getOne("reseller");

                    if($resellerDetail2) {
                        $resellerId = $resellerDetail2['id'];
                        $marketerId = $resellerDetail2['marketer_id'];
                    } else {
                        return array('code' => 1, 'message' => "error", 'statusMsg' => 'Reseller not found', "developer_msg" => '');
                    }

                } else if($rType=="siteadmin"){
                    
                    if($reseller=="") {
                        $resellerId = 0;
                        $marketerId = 0;
                    } else {
                        $db->where("type", "reseller");
                        $db->where("source", $source);
                        $db->where("username", $reseller);
                        $resellerDetail2 = $db->getOne("reseller");

                        if($resellerDetail2) {
                            $resellerId = $resellerDetail2['id'];
                            $marketerId = $resellerDetail2['marketer_id'];
                        } else {
                            return array('code' => 1, 'message' => "error", 'statusMsg' => 'Reseller not found', "developer_msg" => '');
                        }
                    }
                    
                } else {
                    return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => 'Something went wrong. Please try again.');
                }
            }

            
            $db->where("register_site", $source);
            $db->where('username', $username);
            $user = $db->getOne('xun_user', 'id, username');
    
            if($user){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00536') /*An account already exists with this mobile number. Please select another mobile number.*/, 'developer_msg' => 'An account already exists with this mobile number. Please select another mobile number.');
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
                "register_through" => "Backend Register",
                "reseller_id" => $resellerId,
                "type" => "business",
                "service_charge_rate" => $service_charge_rate
            );
    
            // create nuxpay user
            $user_id = $db->insert('xun_user', $insert_user);
    
            if(!$user_id){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => 'Something went wrong. Please try again.');
            }

            // xun_business_account            
            $fields = array("user_id", "email" ,"password", "main_mobile", "main_mobile_verified", "created_at", "updated_at");
            $values = array($user_id, $email, $hash_password, $username, 1, $date, $date);
            $arrayData = array_combine($fields, $values);
            $db->insert("xun_business_account", $arrayData);
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
                return array('code' => 0, 'message' => "FAILED", 'statusMsg' => $this->get_translation_message('E00242')/*Something went wrong.*/, "developer_msg" => $db->getLastError());
                
            if($marketerId > 0) {

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

            

            // $user_country_info_arr = $xunUser->get_user_country_info([$username]);
            // $user_country_info = $user_country_info_arr[$mobile];
            // $user_country = $user_country_info["name"];

            // $message = "Username: " .$nickname. "\n";
            // $message .= "Phone number: " .$username. "\n";
            // // $message .= "IP: " . $ip . "\n";
            // $message .= "Country: " . $user_country . "\n";
            // // $message .= "Device: " . $user_agent . "\n";
            // $message .= "Type of user: " .$insertUserData["type"] . "\n";
            // $message .= "Time: " . date("Y-m-d H:i:s");

            // $erlang_params["tag"] = "Backend Register";
            // $erlang_params["message"] = $message;
            // $erlang_params["mobile_list"] = array();
            
            // //PENDING0818 - ok
            // $xmpp_result = $general->send_thenux_notification($erlang_params, "thenux_pay");
            
            //print_r($curl_return);   
            // $message_d = $this->get_translation_message('B00115');
            // $message_d = str_replace("%%companyName%%", $type, $message_d);

            // return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00279') /*Admin Created Successfully.*/);
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00313') /*Merchant Created Successfully.*/);
        }

        public function get_translation_message($message_code)
        {
            // Language Translations.
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $message = $translations[$message_code][$language];
            return $message;
        }

        public function reseller_get_withdrawal_history($params, $userID){
            $db = $this->db;
            $setting = $this->setting;

            $reseller_page_limit    = $setting->getResellerPageLimit();
            $page_number            = $params["page"];
            $page_size              = $params["page_size"] ? $params["page_size"] : $reseller_page_limit;
            
            $to_datetime            = $params["date_to"]; 
            $from_datetime          = $params["date_from"]; 

            $business_id            = $params['business_id']; 
            $business_name          = $params['business_name'];

            $tx_hash                = $params["tx_hash"];

            $sender_address         = $params["sender_address"]; 
            $recipient_address      = $params["recipient_address"]; 

            $status                 = $params["status"];

            $reseller               = $params["reseller"]; 
            $distributor            = $params["distributor"]; 
            $site                   = $params["site"]; 

            $currency               = $params["currency"];

            if ($page_number < 1) {
                $page_number = 1;
            }

            $start_limit = ($page_number - 1) * $page_size;
            $limit       = array($start_limit, $page_size);

            $db->where("id", $userID);
            $resellerDetail = $db->getOne("reseller", "source, type");
            
            
            $rsource = $resellerDetail['source'];
            $rtype = $resellerDetail['type'];

            $arr_reseller_id = $this->get_reseller($userID);

            if($from_site=="Member") {
                $db->where('u.id', $userID);
            }

            if($from_datetime){
                $from_datetime = date("Y-m-d H:i:s", $from_datetime);
                $db->where('h.created_at', $from_datetime, '>');
            }
            if($to_datetime){
                $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
                $db->where('h.created_at' , $to_datetime, '<=');
            }


            if($business_name){
                $db->where('u.nickname', "%$business_name%" , 'LIKE');
            }
            if($business_id){
                $db->where('u.id', "%$business_id%", 'LIKE');
            }


            if($tx_hash) {
                $db->where("(h.transaction_hash LIKE '%$tx_hash%')");
            }


            if($sender_address) {
                $db->where("(h.sender_address LIKE '%$sender_address%)");
            }
            if($recipient_address) {
                $db->where("(h.recipient_address LIKE '%$recipient_address%')");
            }


            if($status){
                $db->where('h.status', $status);
            }


            if($reseller) {
                $db->where("r.username", "%$reseller%", 'LIKE');
            }
            if($distributor) {
                $db->where("r2.username", "%$distributor%", 'LIKE');
            }


            if($currency) {
                $db->where("h.wallet_type", "%$currency%", 'LIKE');
            }

            $db->where("u.type", "business");
            $db->where("u.register_site", $rsource);
            $db->where("u.reseller_id", $arr_reseller_id, "IN");
            $db->join("xun_user u", "u.id=h.business_id", "INNER");

            
            $db->join("reseller r", "r.id=u.reseller_id", "LEFT");
            $db->join("reseller r2", "r2.id=r.distributor_id", "LEFT");

            // $db->where("h.address_type", "withdrawal");

            $copyDb = $db->copy();
            $copyDb2 = $db->copy();
            $copyDb3 = $db->copy();


            $db->groupBy("h.status");
            $result = $db->get("xun_payment_gateway_withdrawal h", null, "h.status, COUNT(*) as total_transaction");

            $transaction_summary = array("total_transaction"=>0, "success"=>0, "failed"=>0, "pending"=>0);
            foreach($result as $key => $value){
                $status = strtolower($value['status']);
                if($status=="success") {
                    $transaction_summary['success'] = $value['total_transaction'];
                    $transaction_summary['total_transaction'] += $value['total_transaction'];
                } else if($status=="failed") {
                    $transaction_summary['failed'] = $value['total_transaction'];
                    $transaction_summary['total_transaction'] += $value['total_transaction'];
                } else {
                    $transaction_summary['pending'] = $value['total_transaction'];
                    $transaction_summary['total_transaction'] += $value['total_transaction'];
                }
                
            }

            

            $copyDb->groupBy("h.wallet_type");
            $result2 = $copyDb->get("xun_payment_gateway_withdrawal h", null, "h.wallet_type, SUM(h.amount_receive) as total_amount_receive, SUM(h.miner_fee) as total_miner_fee, SUM(h.transaction_fee) as total_processing_fee");

            $currency_summary = array();
            foreach($result2 as $key => $value) {

                $currency_summary[] = array("currency"=>$value['wallet_type'], "total_amount"=>$value['total_amount_receive'], "total_processing_fee"=>$value['total_processing_fee'], "total_miner_fee"=>$value['total_miner_fee'], "total_net_amount"=>($value['total_amount_receive'] - $value['total_miner_fee']));

            }

            
            $totalRecord = $copyDb2->getValue("xun_payment_gateway_withdrawal h", "COUNT(*)");


            $result3 = $copyDb3->get("xun_payment_gateway_withdrawal h", $limit, "h.created_at, u.register_site, u.reseller_id, u.id, u.nickname, h.wallet_type, h.amount, h.miner_fee, h.sender_address, h.recipient_address, h.transaction_hash, h.transaction_fee, h.status, IF(r.username is null, '-', r.username) as reseller_username, IF(r2.username is null, '-', r2.username) as distributor_username");
 
            if(!$result3){
                return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
            }

            $arr_transaction = array();

            foreach($result3 as $key => $value) {

                $history['date'] = $value['created_at'];
                $history['site'] = $value['register_site'];
                $history['distributor'] = $value['distributor_username'];
                $history['reseller'] = $value['reseller_username'];
                $history['merchant_id'] = $value['id'];
                $history['merchant_name'] = $value['nickname'];
                $history['currency'] = $value['wallet_type'];
                $history['actual_amount'] = $value['amount'];
                $history['processing_fee'] = $value['transaction_fee'];
                $history['miner_fee'] = $value['miner_fee'];
                $history['recipient_address'] = $value['recipient_address'];
                $history['tx_hash'] = $value['transaction_hash'];
                $history['status'] = $value['status'];
                $arr_transaction[] = $history;
                
            }


            $data = array(
                "totalRecord" => $totalRecord,
                "numRecord" => $page_size,
                "totalPage" => ceil($totalRecord/$page_size),
                "pageNumber" => $page_number,
                "transaction_summary"=>$transaction_summary, 
                "currency_summary"=>$currency_summary, 
                "arr_transaction"=>$arr_transaction
            );

            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00293') /*Admin Fund Out Listing.*/, 'data' => $data);


        }

        public function reseller_withdrawal_history_details($params){
            global $xunCurrency;
            $db = $this->db;
            $setting = $this->setting;
    
            $admin_page_limit      = $setting->getResellerPageLimit();
            $page_number           = $params["page"];
            $page_size             = $params["page_size"] ? $params["page_size"] : $reseller_page_limit;
            $tx_hash               = $params['tx_hash'];
            $default_service_charge_rate = $setting->systemSetting["theNuxCommissionFeePct"];
    
            $db->where("b.transaction_hash", $tx_hash);
            $db->join("xun_service_charge_audit b", "a.reference_id=b.id", "LEFT");
            $db->join("xun_user c", "a.user_id=c.id", "LEFT");
            $withdrawalHistoryDetails = $db->get("xun_wallet_transaction a", null, "a.created_at, a.address_type, a.amount as receivable_amount, a.wallet_type, a.fee, a.recipient_address, a.transaction_hash, a.miner_fee_exchange_rate, a.exchange_rate, b.ori_tx_amount as actual_amount, b.amount as processing_fee_amount, IF(c.service_charge_rate is null, $default_service_charge_rate, c.service_charge_rate) as service_charge_rate");
            
            $db->join("xun_wallet_transaction b", "a.symbol=b.fee_unit", "INNER");
            $marketplaceCurrencies = $db->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies a',null,  "a.currency_id");
    
            foreach($withdrawalHistoryDetails as $key => $value){
                //top table
                $processing_fee_amount          = $value['processing_fee_amount'];
                $actual_amount                  = $value['actual_amount'];
                $service_charge_rate            = $value['service_charge_rate'];
                //bottom table
                $created_at                     = $value['created_at'];
                $address_type                   = $value['address_type'];
                $recipient_address              = $value['recipient_address'];
                $receivable_amount              = $value['receivable_amount'];
                $fee                            = $value['fee'];
                $transaction_hash               = $value['transaction_hash'];
                $minerFeeExchangeRate           = $value['miner_fee_exchange_rate'];
                $exchangeRate                   = $value['exchange_rate'];
                $wallet_type                    = $value['wallet_type'];
                $currencyID                     = $marketplaceCurrencies['currency_id']; 
    
                if($address_type == "company_acc"){
                    $address_type = "Company Account";
                }else if($address_type == "company_pool"){
                    $address_type = "Company Pool";
                }else if($address_type == "marketer"){
                    $address_type = "Marketer";
                }
    
    
                if($wallet_type != $currencyID){
                    
                    $usdAmount = bcmul($fee, $minerFeeExchangeRate, 18);
                    $minerFee = bcmul($usdAmount, $exchangeRate, 18);
                    
                }
                else if ($wallet_type == $currencyID){
                    $minerFee = $fee;
                }
    
                $withdrawal_arr = array(
                    "actual_amount" =>  $actual_amount,
                    "service_charge_rate" =>  $service_charge_rate,
                    "processing_fee_amount" =>  $processing_fee_amount,
                    "date" => $created_at,
                    "address_type" =>  $address_type, 
                    "receivable_amount" =>  $receivable_amount,
                    "minerFee" =>  $minerFee,
                    "recipient_address" =>  $recipient_address,
                    "transaction_hash" =>  $transaction_hash,
                );
                
                $data["withdrawal_arr"] = $withdrawal_arr;
            }
    
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('E00397') /*Success.*/, 'data' => $data);
    
        }

        public function reseller_register($params, $site){
            global $config;
            $db = $this->db;
            $setting = $this->setting;
            $general = $this->general;
    
            //Language Translations.
            $language        = $this->general->getCurrentLanguage();
            $translations    = $this->general->getTranslations();
    
            // Get the stored password type.
            $passwordEncryption = $setting->getResellerPasswordEncryption();
    
            $username = trim($params['username']);            
            // $password = trim($params['password']);
            // $confirm_password = trim($params['confirm_password']);
            $nickname = $params['nickname'];
            $email = $params['email']; 
            $team_code = $params['team_code'];
            $phone_number = $params['phone_number'];
            $distributor_username = $params['distributor_username'];
    
            // Param validations
            // if($username == ''){
            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00404') /*Username cannot be empty.*/, 'developer_msg' => 'Username cannot be empty.');
            // }
            
            if($nickname == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00506') /*Name cannot be empty.*/, 'developer_msg' => 'Name cannot be empty.');
            }
            
            if($site == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00534') /*Site cannot be empty*/, 'developer_msg' => 'Site cannot be empty.');
            }

            if($email == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00211') /*Email cannot be empty*/, 'developer_msg' => 'Email cannot be empty.');
            }
    
            if($phone_number == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty*/, 'developer_msg' => 'Mobile number cannot be empty.');
            }

              // validate email
            if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00212') /*Please enter a valid email address.*/);
            }
           
            $mobileNumberInfo = $general->mobileNumberInfo($phone_number, null);
    
            if ($mobileNumberInfo["isValid"] == 0){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00046') /*Please enter a valid mobile number.*/, 'developer_msg' => 'Please enter a valid mobile number');
            }
            $mobileNo = "+".$mobileNumberInfo["mobileNumberWithoutFormat"];
    
            $db->where('type', array('distributor', 'reseller'), 'IN');
            $db->where("username", $phone_number);
            $db->where("register_site", $site);
            $mobileNoExists = $db->getOne("xun_user");
    
            if ($mobileNoExists){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00536') /*An account already exists with this mobile number. Please select another mobile number.*/, 'developer_msg' => 'An account already exists with this mobile number. Please select another mobile number.');
            }

            $db->where('type', array('distributor', 'reseller'), 'IN');
            $db->where("username", $username);
            $db->where("source", $site);
            $db->where('disabled', 0);
            $UserNameNoExists = $db->getOne("reseller");

            if ($UserNameNoExists){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => 'An account already exists with this User Name. Please select another User Name.' /*An account already exists with this User Name. Please select another User Name.*/, 'developer_msg' => 'An account already exists with this User Name. Please select another User Name.');
            }
           
            $db->where('type', array('distributor', 'reseller'), 'IN');
            $db->where("source", $site);
            $db->where('disabled', 0);
            $db->where('email', $email);
            $reseller_email_detail = $db->getOne('reseller');

            if($reseller_email_detail){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00558') /*An account already exists with this email address.*/, 'developer_msg' => 'An account already exists with this email address.');
            }
       
            // $db->where('email', $email);
            // $db->where("source", $site);
            // $result = $db->get('reseller');
    
            // if($result){
            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00548') /*Reseller username already exists.*/, 'developer_msg' => 'Reseller username already exists');
            // }

            if($team_code != "") {

                $db->where("deleted", 0);
                $db->where("source", $site);
                $db->where("referral_code", $team_code);
                $db->where("type", "distributor");
                $distributorDetail = $db->getOne("reseller");
    
                if($distributorDetail) {
                    $distributor_id = $distributorDetail['id'];
                } else {
                    return array('code' => 1, 'message' => "error", 'message_d' => 'Invalid distributor username', "developer_msg" => '');
                }
    
            } else {
                $distributor_id = 0;
            }

            if ($distributor_username != ""){
                $db->where("deleted", 0);
                $db->where("source", $site);
                $db->where("username", $distributor_username);
                $db->where("type", "distributor");
                $distributorDetail_D = $db->getOne("reseller");

                if($distributorDetail_D) {
                    $distributor_id = $distributorDetail_D['id'];
                } else {
                    $distributor_id = 0;
                }
            }
       
             //INSERT MARKETER
             $marketerData['name'] = $username ? $username : $nickname;
             $marketerData['created_at'] = date("Y-m-d H:i:s");
             $marketer_id = $db->insert("xun_marketer", $marketerData);
    
            if(!$marketer_id){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00242')/*Something went wrong.*/, "developer_msg" => $db->getLastError());
            }
    
             //INSERT XUN USER
             $userData['server_host'] = $config["server"];
             $userData['type'] = "reseller";
             $userData['register_site'] = $site;
             $userData['register_through'] = "Normal Register";
             $userData['nickname'] = $nickname;
             $userData['created_at'] = date("Y-m-d H:i:s");
             $userData['username'] = $mobileNo;
             $reseller_user_id = $db->insert("xun_user", $userData);
             if(!$reseller_user_id){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200')/*Something went wrong. Please try again later.*/, "developer_msg" => $db->getLastError());
             }
    
             //INSERT RESELLER
             $resellerData['user_id'] = $reseller_user_id;
             $resellerData['marketer_id'] = $marketer_id;
             $resellerData['distributor_id'] = $distributor_id;
             $resellerData['username'] = $username ? $username  : '';
             $resellerData['name'] = $nickname;
            //  $resellerData['password'] = $hash_password;
             $resellerData['status'] = 'pending';
             $resellerData['email'] = $email;
             $resellerData['source'] = $site;
             $resellerData['type'] = "reseller";
             $resellerData['role_id'] = "6";
             $resellerData['created_at'] = date("Y-m-d H:i:s");
             $reseller_id = $db->insert("reseller", $resellerData);
             if(!$reseller_id){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200')/*Something went wrong. Please try again later.*/, "developer_msg" => $db->getLastError());
             }
    
             //UPDATE XUN USER RESELLER ID
             $db->where("id", $reseller_user_id);
             $update_reseller_id = $db->update("xun_user", array("reseller_id" => $reseller_id) );
             if(!$update_reseller_id){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200')/*Something went wrong. Please try again later.*/, "developer_msg" => $db->getLastError());
             }
    
            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00324')/*We have received your application. Someone from our sales team will contact you shortly. We’ll use all the information you’ve provided to reach you.*/);
        }

        public function reseller_application_listing($params, $userID){
            $db= $this->db;
            $setting = $this->setting;

            $reseller_page_limit= $setting->getResellerPageLimit();
            $reseller           = $params["reseller"];
            $reseller_name      = $params["reseller_name"];
            $reseller_email     = $params["reseller_email"];
            $reseller_number    = $params["reseller_number"];
            $reseller_site      = $params["reseller_site"];
            $distributor        = $params["distributor"];
            $site               = $params['site'];
            $page_number        = $params["page"];
            $page_size          = $params["page_size"] ? $params["page_size"] : $reseller_page_limit;

            $db->where("id", $userID);
            $resellerDetail = $db->getOne("reseller", "source, type");
            
            $rsource = $resellerDetail['source'];
            $rtype = $resellerDetail['type'];

            if ($page_number < 1) {
                $page_number = 1;
            }

            if($reseller) {
                $db->where("r.username", "%$reseller%", "LIKE");
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
            
            // if($site) {
            //     $db->where("r.source", "%$site%", "LIKE");
            // }


            $start_limit = ($page_number - 1) * $page_size;
            $limit       = array($start_limit, $page_size);

            $db->where('r.distributor_id', $userID);
            $db->where('r.source', "%$rsource%", 'LIKE');
            $db->where('r.status', 'pending');
            $db->join("reseller r2", "r.distributor_id=r2.id", "left");
            $db->join('xun_user b', 'r.user_id= b.id' , 'LEFT');
            $reseller_listing = $db->get('reseller r', $limit, "r.source, r.username as reseller_username, r.name, b.username as phone_number, r.email, IF(r2.username is null, '-', r2.username) as distributor_username, r.created_at");
            
            if(!$reseller_listing){
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
            }
          
            $data['reseller_application_listing'] = $reseller_listing;
            
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00326')/*Reseller Application Listing*/, 'data' => $data);
            
        }

        public function get_reseller_clicks_info($params, $reseller_id){
            $db = $this->db;
            $display_decimals = 4;

            $db->where('id', $reseller_id);
            $reseller_info = $db->getOne('reseller');
            $content_str = 'type=signupPage&referral_code=';

            if (!$reseller_info){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Reseller ID does not exist.", "developer_msg" => "Reseller ID $userID does not exist.");
            }
            if ($reseller_info['type']=="reseller" || $reseller_info['type']=="siteadmin"){
                $content_str = 'username=';
                $referralCode = $reseller_info['referral_code'];
                $username = $reseller_info['username'];

                // Get ALL
                $db->where('content', $content_str . $username);
                $total_signup = $db->getValue('utm_tracking', 'count(id)');

                $db->where('reseller_id', $reseller_info['id']);
                $db->where('type', 'reseller', '<>');
                $total_registered = $db->getValue('xun_user', 'count(id)');

                // Get TODAY
                $date_query = 'DATE(created_at) = CURRENT_DATE';
                $db->where($date_query);
                $db->where('content', $content_str . $username);
                $today_signup = $db->getValue('utm_tracking', 'count(id)');

                $db->where($date_query);
                $db->where('reseller_id', $reseller_info['id']);
                $db->where('type', 'reseller', '<>');
                $today_registered = $db->getValue('xun_user', 'count(id)');            

                // Get YESTERDAY
                $date_query = 'DATE(created_at) = DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)';
                $db->where($date_query);
                $db->where('content', $content_str . $username);
                $yesterday_signup = $db->getValue('utm_tracking', 'count(id)');

                $db->where($date_query);
                $db->where('reseller_id', $reseller_info['id']);
                $db->where('type', 'reseller', '<>');
                $yesterday_registered = $db->getValue('xun_user', 'count(id)'); 

                // Get Last 7 days
                $date_query = 'DATE(created_at) BETWEEN DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND CURRENT_DATE';
                $db->where($date_query);
                $db->where('content', $content_str . $username);
                $pastweek_signup = $db->getValue('utm_tracking', 'count(id)');

                $db->where($date_query);
                $db->where('reseller_id', $reseller_info['id']);
                $db->where('type', 'reseller', '<>');
                $pastweek_registered = $db->getValue('xun_user', 'count(id)');    
                
                // Get Last Month days
                $date_query = 'DATE(created_at) BETWEEN DATE_SUB(CURRENT_DATE, INTERVAL 29 DAY) AND CURRENT_DATE';
                $db->where($date_query);
                $db->where('content', $content_str . $username);
                $pastmonth_signup = $db->getValue('utm_tracking', 'count(id)');

                $db->where($date_query);
                $db->where('reseller_id', $reseller_info['id']);
                $db->where('type', 'reseller', '<>');
                $pastmonth_registered = $db->getValue('xun_user', 'count(id)');            
            } 
            if ($reseller_info['type']=="distributor"){
                $referralCode = $reseller_info['referral_code']; 
                // Get ALL
                $db->where('content', $content_str . $referralCode);
                $total_signup = $db->getValue('utm_tracking', 'count(id)');

                $db->where('distributor_id', $reseller_info['id']);
                $total_registered = $db->getValue('reseller', 'count(id)');

                // Get TODAY
                $date_query = 'DATE(created_at) = CURRENT_DATE';
                $db->where($date_query);
                $db->where('content', $content_str . $referralCode);
                $today_signup = $db->getValue('utm_tracking', 'count(id)');

                $db->where($date_query);
                $db->where('distributor_id', $reseller_info['id']);
                $today_registered = $db->getValue('reseller', 'count(id)');            

                // Get YESTERDAY
                $date_query = 'DATE(created_at) = DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)';
                $db->where($date_query);
                $db->where('content', $content_str . $referralCode);
                $yesterday_signup = $db->getValue('utm_tracking', 'count(id)');

                $db->where($date_query);
                $db->where('distributor_id', $reseller_info['id']);
                $yesterday_registered = $db->getValue('reseller', 'count(id)'); 

                // Get Last 7 days
                $date_query = 'DATE(created_at) BETWEEN DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND CURRENT_DATE';
                $db->where($date_query);
                $db->where('content', $content_str . $referralCode);
                $pastweek_signup = $db->getValue('utm_tracking', 'count(id)');

                $db->where($date_query);
                $db->where('distributor_id', $reseller_info['id']);
                $pastweek_registered = $db->getValue('reseller', 'count(id)');    
                
                // Get Last Month days
                $date_query = 'DATE(created_at) BETWEEN DATE_SUB(CURRENT_DATE, INTERVAL 29 DAY) AND CURRENT_DATE';
                $db->where($date_query);
                $db->where('content', $content_str . $referralCode);
                $pastmonth_signup = $db->getValue('utm_tracking', 'count(id)');

                $db->where($date_query);
                $db->where('distributor_id', $reseller_info['id']);
                $pastmonth_registered = $db->getValue('reseller', 'count(id)');
            }
            $returnData['signup']['total'] = $total_signup;
            $returnData['registered']['total'] = $total_registered;
            $returnData['rate'] ['total']= ($total_signup == 0) ? 0 : round($total_registered / $total_signup * 100, $display_decimals);

            $returnData['signup']['today'] = $today_signup;
            $returnData['registered']['today'] = $today_registered;
            $returnData['rate'] ['today'] = ($today_signup == 0) ? 0 : round($today_registered / $today_signup * 100, $display_decimals);

            $returnData['signup']['yesterday'] = $yesterday_signup;
            $returnData['registered']['yesterday'] = $yesterday_registered;
            $returnData['rate'] ['yesterday'] = ($yesterday_signup == 0) ? 0 : round($yesterday_registered / $yesterday_signup * 100, $display_decimals);

            $returnData['signup']['pastweek'] = $pastweek_signup;
            $returnData['registered']['pastweek'] = $pastweek_registered;
            $returnData['rate'] ['pastweek'] = ($pastweek_signup == 0) ? 0 : round($pastweek_registered / $pastweek_signup * 100, $display_decimals);

            $returnData['signup']['pastmonth'] = $pastmonth_signup;
            $returnData['registered']['pastmonth'] = $pastmonth_registered;
            $returnData['rate'] ['pastmonth'] = ($pastmonth_signup == 0) ? 0 : round($pastmonth_registered / $pastmonth_signup * 100, $display_decimals);

            return array("code" => 0, "status" => "ok", "statusMsg" => "Reseller Clicks info", 'data' => $returnData);

        }

        public function reseller_request_reset_password_otp($params){
            global $xunUser;
            $db = $this->db;

            $email = $params['email'];
            $source = $params['source'];

            if($email == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00211') /*Email cannot be empty.*/, 'developer_msg' => 'Email cannot be empty.');
            }
            
            $db->where('email', $email);
            $db->where('source', $source);
            $db->where('deleted', 0);
            $db->where('status', 'approved');
            $reseller_details = $db->getOne('reseller');

            if(!$reseller_details){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00596') /*Email not found.*/, 'developer_msg' => 'Email not found.');
            }

            $reseller_email = $reseller_details['email'];
            $user_type = $reseller_details['type'];

            $requestParams = array(
                "req_type" => "email",
                "email" => $reseller_email,
                "company_name" => $source,
                "request_type" => "reseller_reset_password",
                "user_type" => $user_type
            );
            $otp_return = $xunUser->register_verifycode_get($requestParams);

            if($otp_return['code']== 0){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $otp_return['message_d'], 'timeout' => $otp_return['timeout'], 'errorCode' => $otp_return['errorCode']);
            }

            $success_message =  $this->get_translation_message('B00332'); /*An OTP has been sent to your email %%email%%*/

            $message_d = str_replace("%%email%%", $reseller_email, $success_message);
            return array("code" => 0, "status" => "ok", "statusMsg" => $message_d, 'timeout' => $otp_return['timeout'], 'show_help_message' => $otp_return['show_help_message'], 'help_message' => $otp_return['help_message'], 'email' => $reseller_email);
        }

        public function reseller_reset_password($params, $ip, $userAgent){
            global $xunUser;
            $db = $this->db;

            $email           = $params['email'];
            $user_otp           = trim($params['user_otp']);
            $new_password       = trim($params['new_password']);
            $confirm_password   = trim($params['confirm_password']);
            $source             = trim($params['source']);
            
            if($email == ""){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00211') /*Email cannot be empty.*/, 'developer_msg' => 'Email cannot be empty.');            }

            if($user_otp == ""){
                return array('code' => 1, 'status' => "error", 'statusMsg' =>$this->get_translation_message('E00559')/* "OTP code cannot be empty."*/, "developer_msg" => "OTP code cannot be empty.");
            }

            if($new_password == ""){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00067') /*New Password cannot be empty.*/, 'developer_msg' => 'New Password cannot be empty.');
            }

            if($confirm_password == ""){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00068') /*Confirm Password cannot be empty.*/, "developer_msg" => "Confirm password cannot be empty.");
            }
            
            if($confirm_password != $new_password){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00071') /*New Password confirmation does not match.*/, "developer_msg" => "New Password confirmation does not match.");
            }

            $db->where('email', $email);
            $db->where('source', $source);
            $db->where('deleted', 0);
            $db->where('status', 'approved');
            $reseller_details = $db->getOne('reseller');

            if(!$reseller_details){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00557') /*User not found.*/, 'developer_msg' => 'User not found.');
            }

            $reseller_email = $reseller_details['email'];
            $user_type = $reseller_details['type'];

            $requestParams = array(
                "req_type" => "email",
                "verify_code" => $user_otp,
                "companyName" => $source,
                "request_type" => "reseller_reset_password",
                "nuxpay_user_type" => $user_type,
                "mobile" => $reseller_email,
            );
            $otp_verify = $xunUser->register_verifycode_verify($requestParams, $ip, $userAgent);
          
            if($otp_verify["code"] == 1){

                if($confirm_password == $new_password){
                    
                    $hash_password = password_hash($new_password, PASSWORD_BCRYPT);
                    $update_new_password = array(
                        "password"      => $hash_password,
                        "updated_at"    => date("Y-m-d H:i:s")
                    );
                    
                    $db->where("email", $email);
                    $updated = $db->update("reseller", $update_new_password);

                    if($updated){
                        return array("code" => 0, "status" => "ok", "statusMsg" =>$this->get_translation_message('B00333') /* Password successfully updated!*/, "developer_msg" => "Password successfully updated!");
                    }else{
                        return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
                    }

                }

            }else{
                return array('code' => 1, 'status' => "error", 'statusMsg' => $otp_verify['message_d'], "developer_msg" => "Failed to verify OTP code.");
            }
        }

        public function reseller_request_username_otp($params){
            global $xunUser;
            $db = $this->db;

            $email = $params['email'];
            $source = $params['source'];

            if($email == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00211') /*Email cannot be empty.*/, 'developer_msg' => 'Email cannot be empty.');
            }

            $db->where('email', $email);
            $db->where('source', $source);
            $db->where('deleted', 0);
            $db->where('status', 'approved');
            $reseller_details = $db->getOne('reseller');

            if(!$reseller_details){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00557') /*Email not found.*/, 'developer_msg' => 'User not found.');
            }

            $reseller_email = $reseller_details['email'];
            $user_type = $reseller_details['type'];

            $requestParams = array(
                "req_type" => "email",
                "email" => $reseller_email,
                "company_name" => $source,
                "request_type" => "reseller_request_username",
                "user_type" => $user_type
            );
            $otp_return = $xunUser->register_verifycode_get($requestParams);

            if($otp_return['code'] == 0){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $otp_return['message_d'], 'timeout' => $otp_return['timeout'], 'errorCode' => $otp_return['errorCode']);
            }

            $success_message =  $this->get_translation_message('B00332'); /*An OTP has been sent to your email %%email%%*/
            $message_d = str_replace("%%email%%", $reseller_email, $success_message);
            
            return array("code" => 0, "status" => "ok", "statusMsg" => $message_d, 'timeout' => $otp_return['timeout'], 'show_help_message' => $otp_return['show_help_message'], 'help_message' => $otp_return['help_message'], 'email' => $reseller_email);
        }

        public function reseller_request_username($params){
            global $xunUser, $xunEmail;
            $general = $this->general;
            $db = $this->db;

            $email           = $params['email'];
            $user_otp           = trim($params['user_otp']);
            $source             = trim($params['source']);

            if($email == ""){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00211') /*Email cannot be empty.*/, 'developer_msg' => 'Email cannot be empty.');            }

            if($user_otp == ""){
                return array('code' => 1, 'status' => "error", 'statusMsg' =>$this->get_translation_message('E00559')/* "OTP code cannot be empty."*/, "developer_msg" => "OTP code cannot be empty.");
            }

            $db->where('email', $email);
            $db->where('source', $source);
            $db->where('deleted', 0);
            $db->where('status', 'approved');
            $reseller_details = $db->getOne('reseller');

            if(!$reseller_details){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00557') /*User not found.*/, 'developer_msg' => 'User not found.');
            }

            $reseller_email = $reseller_details['email'];
            $user_type = $reseller_details['type'];

            $requestParams = array(
                "req_type" => "email",
                "verify_code" => $user_otp,
                "companyName" => $source,
                "request_type" => "reseller_request_username",
                "nuxpay_user_type" => $user_type,
                "mobile" => $reseller_email,
            );
            $otp_verify = $xunUser->register_verifycode_verify($requestParams);

            if($otp_verify["code"] == 1){
                $username = $reseller_details['username'];

                $emailDetail = $xunEmail->getResellerUsernameEmail($source, $username);
                $emailParams["subject"] = $emailDetail['emailSubject'];
                $emailParams["body"] = $emailDetail['html'];
                $emailParams["recipients"] = array($email);
                $emailParams["emailFromName"] = $emailDetail['emailFromName'];
                $emailParams["emailAddress"] = $emailDetail['emailAddress'];
                $emailParams["emailPassword"] = $emailDetail['emailPassword'];
                $msg = $general->sendEmail($emailParams);

                $success_message =  $this->get_translation_message('B00339'); /*Your username has been sent to your email %%email%%*/

                $message_d = str_replace("%%email%%", $reseller_email, $success_message);

                return array("code" => 0, "status" => "ok", "statusMsg" => $message_d, "developer_msg" => "Username has been sent to your email " . $reseller_email);

            } else {
                return array('code' => 1, 'status' => "error", 'statusMsg' => $otp_verify['message_d'], "developer_msg" => "Failed to verify OTP code.");
            }

        }

        public function reseller_get_top_distributors($params, $userID){
            $db = $this->db;
            $dateFrom = date("Y-m-d", $params['dateFrom']);
            $dateTo = date("Y-m-d", $params['dateTo']);

            $db->where('id', $userID);
            $reseller_info = $db->getOne('reseller');

            if (!$reseller_info){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00557') /*User not found.*/, 'developer_msg' => 'User not found.');
            } else if ($reseller_info['type'] != 'siteadmin'){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00557') /*User not found.*/, 'developer_msg' => 'User not found.');
            }

            if ($dateFrom){
                $db->where('a.transaction_date', $dateFrom, '>=');
            }

            if ($dateTo){
                $db->where('a.transaction_date', $dateTo, '<=');
            }

            $db->where('r.distributor_id', '0', '<>');
            $db->where('r.source', $reseller_info['source']);
            $db->join('xun_user b', 'a.business_id = b.id', 'LEFT');
            $db->join('reseller r', 'b.reseller_id = r.id', 'LEFT');
            $db->join('reseller r2', 'r.distributor_id = r2.id', 'LEFT');
            $db->groupBy('r.distributor_id');
            $summaryDetails = $db->get('xun_crypto_history_summary a', null, 'r2.name as distributor_name, r.distributor_id, SUM(a.total_transaction) as total_transaction, SUM(a.total_amount_usd) as total_amount_usd, SUM(a.total_transaction_fee_usd) as total_transaction_fee_usd');

            $transaction_fee = array_column($summaryDetails, 'total_transaction_fee_usd');
            array_multisort($transaction_fee, SORT_DESC, $summaryDetails);
            
            if (!$summaryDetails){
                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00001') /*No Results Found.*/, "developer_msg" => "No Results Found.");
            } else {
                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00340') /*Top agents.*/, "developer_msg" => "Top agents.", 'data' => $summaryDetails);
            }
        }

        public function reseller_get_top_resellers($params, $userID){
            $db = $this->db;
            $dateFrom = date("Y-m-d", $params['dateFrom']);
            $dateTo = date("Y-m-d", $params['dateTo']);

            $db->where('id', $userID);
            $reseller_info = $db->getOne('reseller');

            if (!$reseller_info){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00557') /*User not found.*/, 'developer_msg' => 'User not found.');
            }

            if ($dateFrom){
                $db->where('a.transaction_date', $dateFrom, '>=');
            }

            if ($dateTo){
                $db->where('a.transaction_date', $dateTo, '<=');
            }
            
            $db->where('r.distributor_id', $reseller_info['id']);
            $db->join('xun_user b' ,'a.business_id = b.id', 'LEFT');
            $db->join('reseller r', 'b.reseller_id = r.id', 'LEFT');
            $db->groupBy('b.reseller_id');
            $summaryDetails = $db->get('xun_crypto_history_summary a', null, 'r.id as reseller_id, r.username, r.name, SUM(a.total_transaction) as total_transactions, SUM(a.total_amount_usd) as total_amount_usd, SUM(a.total_transaction_fee_usd) as total_transactions_fee_usd');

            if (!$summaryDetails){
                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00001') /*No Results Found.*/, "developer_msg" => "No Results Found.");
            } else {
                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00342') /*Top sales person.*/, "developer_msg" => "Top sales person.", 'data' => $summaryDetails);
            }
        }

        public function reseller_get_commission_balance($params, $userID){
            $db = $this->db;

            $db->where('a.reseller_id', $userID);
            $db->join('xun_marketplace_currencies c', 'a.wallet_type = c.currency_id', 'LEFT');
            $db->groupBy('a.wallet_type');
            $commissionDetails = $db->get('xun_marketer_commission_transaction a', null, 'a.wallet_type, SUM(a.credit) as total_credit, SUM(a.debit) as total_debit, SUM(a.credit) - SUM(a.debit) as balance, c.symbol');

            if (!$commissionDetails){
                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00001') /*No Results Found.*/, "developer_msg" => "No Results Found.");
            } else {
                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00343') /*Commission balance.*/, "developer_msg" => "Commission details.", 'data' => $commissionDetails);
            }
        }

        public function reseller_get_commission_transaction_history($params, $userID){
            $db = $this->db;
            $setting = $this->setting;
            $reseller_page_limit    = $setting->getResellerPageLimit();

            $wallet_type = $params['wallet_type'];
            $page_number            = $params["page"];
            $page_size              = $params["page_size"] ? $params["page_size"] : $reseller_page_limit;

            if ($page_number < 1) {
                $page_number = 1;
            }

            $start_limit = ($page_number - 1) * $page_size;
            $limit       = array($start_limit, $page_size);


            $db->where('id', $userID);
            $marketer_id = $db->getValue('reseller', 'marketer_id');

            if (!$marketer_id){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00557') /*User not found.*/, 'developer_msg' => 'User not found.');
            }
            
            if (!$wallet_type){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/, 'developer_msg' => 'Wallet Type cannot be empty');
            }

            $db->orderBy('a.created_at', 'DESC');
            $db->where('b.marketer_id', $marketer_id);
            $db->where('a.wallet_type', $wallet_type);
            $where = '(a.type="Transfer In" or a.type = "Fund Out" or a.type = "Miner Fee Fund Out" or a.type = "Daily Fund Out")';
            $db->where($where);
            $db->join('xun_business_marketer_commission_scheme b', 'a.business_marketer_commission_id=b.id', 'LEFT');
            $copyDb = $db->copy();
            $commissionTransactions = $db->get('xun_marketer_commission_transaction a', $limit, 'a.type as transaction_type, a.credit as in_amount, a.debit as out_amount, a.created_at');
            $totalRecord = $copyDb->getValue("xun_marketer_commission_transaction a", "count(a.id)");

            // Change transactions_type naming
            foreach($commissionTransactions as $key => $value){
                switch ($commissionTransactions[$key]['transaction_type']){
                    case 'Transfer In':
                        $newVal = 'Receive Commission';
                        break;

                    case 'Fund Out':
                        $newVal = 'Withdraw';
                        break;

                    case 'Miner Fee Fund Out':
                        $newVal = 'Miner Fee';
                        break;

                    case 'Daily Fund Out':
                        $newVal = 'Daily Withdraw';
                        break;
                }
                $commissionTransactions[$key]['transaction_type'] = $newVal;
            }
            $returnData["commissionTransactions"]      = $commissionTransactions;
            $returnData["totalRecord"]      = $totalRecord;
            $returnData["numRecord"]        = $page_size;
            $returnData["totalPage"]        = ceil($totalRecord/$page_size);
            $returnData["pageNumber"]       = $page_number;
            // if (!$commissionTransactions){
            //     return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00001') /*No Results Found.*/, "developer_msg" => "No Results Found.");
            // } else {
                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00344') /*Commission transaction history.*/, "developer_msg" => "Commission transaction history.", 'data' => $returnData);
            // }
        }

        public function reseller_request_commission_withdrawal_otp($params, $userID){
            global $xunUser;
            $db = $this->db;

            $db->where('id', $userID);
            $db->where('deleted', 0);
            $db->where('status', 'approved');
            $reseller_details = $db->getOne('reseller');

            if(!$reseller_details){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00557') /*User not found.*/, 'developer_msg' => 'User not found.');
            }

            $source = $reseller_details['source'];
            $reseller_email = $reseller_details['email'];
            $user_type = $reseller_details['type'];

            $requestParams = array(
                "req_type" => "email",
                "email" => $reseller_email,
                "company_name" => $source,
                "request_type" => "reseller_request_commission_withdrawal",
                "user_type" => $user_type
            );
            $otp_return = $xunUser->register_verifycode_get($requestParams);

            if($otp_return['code'] == 0){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $otp_return['message_d'], 'timeout' => $otp_return['timeout'], 'errorCode' => $otp_return['errorCode']);
            }

            $success_message =  $this->get_translation_message('B00332'); /*An OTP has been sent to your email %%email%%*/
            $message_d = str_replace("%%email%%", $reseller_email, $success_message);
            
            return array("code" => 0, "status" => "ok", "statusMsg" => $message_d, 'timeout' => $otp_return['timeout'], 'show_help_message' => $otp_return['show_help_message'], 'help_message' => $otp_return['help_message'], 'email' => $reseller_email);
        }

        public function reseller_get_commission_withdrawal_history($params, $userID){
            $db = $this->db;
            $setting = $this->setting;
            $reseller_page_limit    = $setting->getResellerPageLimit();

            $wallet_type = $params['wallet_type'];
            $page_number            = $params["page"];
            $page_size              = $params["page_size"] ? $params["page_size"] : $reseller_page_limit;

            if ($page_number < 1) {
                $page_number = 1;
            }

            $start_limit = ($page_number - 1) * $page_size;
            $limit       = array($start_limit, $page_size);



            $db->where('id', $userID);
            $marketer_id = $db->getValue('reseller', 'marketer_id');

            if (!$marketer_id){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00557') /*User not found.*/, 'developer_msg' => 'User not found.');
            }
            
            if (!$wallet_type){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/, 'developer_msg' => 'Wallet Type cannot be empty');
            }

            $db->orderBy('a.created_at', 'DESC');
            $db->where('b.marketer_id', $marketer_id);
            $db->where('a.wallet_type', $wallet_type);
            $db->where('a.type', 'Fund Out');
            $db->join('xun_business_marketer_commission_scheme b', 'a.business_marketer_commission_id=b.id', 'LEFT');
            $db->join('xun_wallet_transaction c', 'a.reference_id=c.id', 'LEFT');
            $copyDb = $db->copy();
            $commissionWithdrawals = $db->get('xun_marketer_commission_transaction a', $limit, 'a.created_at, a.wallet_type, c.amount, c.recipient_address, c.transaction_hash');
            $totalRecord = $copyDb->getValue("xun_marketer_commission_transaction a", "count(a.id)");

            $returnData["commissionWithdrawals"]      = $commissionWithdrawals;
            $returnData["totalRecord"]      = $totalRecord;
            $returnData["numRecord"]        = $page_size;
            $returnData["totalPage"]        = ceil($totalRecord/$page_size);
            $returnData["pageNumber"]       = $page_number;
            // if (!$commissionWithdrawals){
            //     return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00001') /*No Results Found.*/, "developer_msg" => "No Results Found.");
            // } else {
                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00345') /*Commission withdrawal history.*/, "developer_msg" => "Commission withdrawal history.", 'data' => $returnData);
            // }

        }

        public function reseller_withdraw($params, $userID){
            global $xunMarketer, $xunCrypto, $xunCurrency, $xunMinerFee, $xun_numbers, $config, $xunUser, $xunPayment;
            $db= $this->db;
            $setting= $this->setting;
            $post = $this->post;
            $general = $this->general;

            $xun_business_service = new XunBusinessService($db);
            $externalTransferCompanyPoolURL = $config['externalTransferCompanyPoolURL'];

            $prepaidWalletServerURL =  $config["giftCodeUrl"];
            $date = date("Y-m-d H:i:s");

            $destination_address = $params['destination_address'];
            $wallet_type = $params['wallet_type'];
            $amount = $params['amount'];
            $otp_code = $params['otp_code'];

            $company_pool_address = $setting->systemSetting['marketplaceCompanyPoolWalletAddress'];
            if($destination_address == ""){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00153') /*Destination Address cannot be empty.*/, 'developer_msg' => 'Destination Address cannot be empty.');
            }

            if($wallet_type == ""){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty.*/, 'developer_msg' => 'Wallet Type cannot be empty.');
            }

            if($amount == ""){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00417') /*Amount cannot be empty.*/, 'developer_msg' => 'Amount cannot be empty.');
            }

            if($otp_code == ""){
                return array('code' => 1, 'status' => "error", 'statusMsg' =>$this->get_translation_message('E00559')/* "OTP code cannot be empty."*/, "developer_msg" => "OTP code cannot be empty.");
            }       

            $db->where('id', $userID);
            $reseller = $db->getOne('reseller');

            if(!$reseller){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00571') /*Reseller not found.*/, 'developer_msg' => 'Reseller not found.');

            }
            $xun_user_id = $reseller['user_id'];
            $marketer_id = $reseller['marketer_id'];
            $source = $reseller['source'];
            $user_type = $reseller['type'];
            $reseller_email = $reseller['email'];
            $reseller_name = $reseller['name'];

            $requestParams = array(
                "req_type" => "email",
                "verify_code" => $otp_code,
                "companyName" => $source,
                "request_type" => "reseller_request_commission_withdrawal",
                "nuxpay_user_type" => $user_type,
                "mobile" => $reseller_email,
            );
            $otp_verify = $xunUser->register_verifycode_verify($requestParams);

            if($otp_verify["code"] == 0){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $otp_verify['message_d'], "developer_msg" => "Failed to verify OTP code.");
            }

            $db->where('marketer_id', $marketer_id);
            $db->where('wallet_type', $wallet_type);
            $db->where('disabled', 0);
            $business_marketer_commission_scheme = $db->getOne('xun_business_marketer_commission_scheme');

            $business_marketer_commission_id = $business_marketer_commission_scheme['id'];

            $marketer_wallet_balance = $xunMarketer->get_marketer_commission_balance($business_marketer_commission_id, $wallet_type);

            if($marketer_wallet_balance < $amount){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00338') /*Insufficient Balance.*/, 'developer_msg' => 'Insufficient Balance.');
            }

            $db->where('user_id', $xun_user_id);
            $db->where('address_type', 'nuxpay_wallet');
            $crypto_user_address = $db->getOne('xun_crypto_user_address', 'id, address');
            
            $internal_address = $crypto_user_address['address'];

            $wallet_info = $xunCrypto->get_wallet_info($internal_address, $wallet_type);

            $lc_wallet_type = strtolower($wallet_type);
            $unitConversion = $wallet_info[$lc_wallet_type]['unitConversion'];
            $minerFeeWalletType = strtolower($wallet_info[$lc_wallet_type]['feeType']);

            $db->where('currency_id', $minerFeeWalletType);
            $marketplace_currencies = $db->getOne('xun_marketplace_currencies', 'currency_id, unit_conversion');
            
            $minerFeeUnitConversion = $marketplace_currencies['unit_conversion'];

            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($lc_wallet_type, true);
            $decimal_places = $decimal_place_setting["decimal_places"];

            $miner_decimal_place_setting = $xunCurrency->get_currency_decimal_places($minerFeeWalletType, true);
            $miner_fee_decimal_places = $miner_decimal_place_setting['decimal_places'];

            if($minerFeeWalletType != $wallet_type){
      
                $minerFeeBalance = $xunMinerFee->getMinerFeeBalance($company_pool_address, $minerFeeWalletType);
                $converted_miner_fee_balance = $minerFeeBalance;

            }
            
            $ret_val= $xunCrypto->crypto_validate_address($destination_address, $wallet_type, 'external');
            ///need to add send message when the destination  address is not valid
            if($ret_val['code'] == 1){
                $ret_val1 = $xunCrypto->crypto_validate_address($destination_address, $wallet_type, 'internal');
    
                if($ret_val1['code'] ==0){
                    $destination_address = $ret_val1['data']['address'];
                    $transaction_type = $ret_val1['data']['addressType'];
                }
                else{
                    return array('code' => 1, 'status' => "error", 'statusMsg' => $ret_val1['statusMsg']);
                }
            }
            else{
                $destination_address = $ret_val['data']['address'];
                $transaction_type = $ret_val['data']['addressType'];
            }

            if($transaction_type == 'external'){
                $return = $xunCrypto->calculate_miner_fee($internal_address, $destination_address, $amount, $wallet_type, 1);

                $miner_fee_satoshi = $return['data']['txFee'];
    
            }
            else{
                $miner_fee_satoshi = '0';
            }
          
            $miner_fee = bcdiv($miner_fee_satoshi, $minerFeeUnitConversion, $miner_fee_decimal_places);
           
            //if miner is not charge in the same wallet type as the transaction
            if($wallet_type != $minerFeeWalletType){
                $lowercase_miner_wallet_type = strtolower($minerFeeWalletType);
            
                $converted_miner_fee=  $xunCurrency->get_conversion_amount($lc_wallet_type, $minerFeeWalletType, $miner_fee, true);
            
                $convertedSatoshiMinerFee = bcmul($converted_miner_fee, $unitConversion);

            }else{
                $convertedSatoshiMinerFee = $miner_fee_satoshi;
                $converted_miner_fee = $xunCurrency->round_miner_fee($minerFeeWalletType, $miner_fee);
            }

            $withdrawSatoshiAmount = bcmul($amount, $unitConversion);
            //After Deducting Miner Fee
            $withdrawSatoshiAmount = bcsub($withdrawSatoshiAmount, $convertedSatoshiMinerFee);

            $convertedWithdrawalAmount = bcdiv($withdrawSatoshiAmount, $unitConversion, $decimal_places);

            if($minerFeeWalletType != $wallet_type){
                $miner_fee_balance_usd = $xunCurrency->calculate_cryptocurrency_rate_by_wallet_type($minerFeeWalletType, $converted_miner_fee_balance);

                if($miner_fee_balance_usd <= 10){
                    $tag = "Low Miner Fee Balance";
                    $message = "Type: Company Pool Address\n";
                    $message .= "Address: ".$company_pool_address."\n";
                    // $message .= "Business Name:".$business_name."\n";
                    $message .= "Miner Fee:".$converted_miner_fee."\n";
                    $message .= "Miner Fee Wallet Balance: ".$converted_miner_fee_balance."\n";
                    $message .= "Wallet Type:".$minerFeeWalletType."\n";
                    $message .= "Time: ".date("Y-m-d H:i:s")."\n";

                    $thenux_params["tag"]         = $tag;
                    $thenux_params["message"]     = $message;
                    $thenux_params["mobile_list"] = $xun_numbers;
                    $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_issues");
                }
            }

            if($convertedWithdrawalAmount <= 0){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00572') /*You have insufficient amount ot pay the miner fee.*/, 'developer_msg' => 'You have insufficient amount to pay the miner fee.');
            }

            //calculate the remaining balance in the wallet
            $marketer_wallet_balance = bcsub($marketer_wallet_balance, $amount, $decimal_places);

            $fund_out_marketer_transaction_id = $xunCrypto->insertMarketerCommissionTransaction(0, $convertedWithdrawalAmount, $withdrawSatoshiAmount, $wallet_type, 0, $convertedWithdrawalAmount, 0, $destination_address, 'Fund Out',0,  $userID);
            if($miner_fee > 0){ 
                if($minerFeeWalletType != $wallet_type){
                   
                        $miner_fee_transaction_id = $xunCrypto->insertMarketerCommissionTransaction(0, $converted_miner_fee, $convertedSatoshiMinerFee, $wallet_type, 0, $converted_miner_fee, 0, "Original Miner Fee Amount: ".$miner_fee, 'Miner Fee Fund Out', $fund_out_marketer_transaction_id, $userID);

                        $xunWallet = new XunWallet($db);
                        $transactionObj->status = 'pending';
                        $transactionObj->transactionHash = '';
                        $transactionObj->transactionToken = '';
                        $transactionObj->senderAddress = $company_pool_address;
                        $transactionObj->recipientAddress = $internal_address;
                        $transactionObj->userID = $xun_user_id;
                        $transactionObj->senderUserID = 'company_pool';
                        $transactionObj->recipientUserID = $xun_user_id;
                        $transactionObj->walletType = $minerFeeWalletType;
                        $transactionObj->amount = $miner_fee;
                        $transactionObj->addressType = 'nuxpay_wallet';
                        $transactionObj->transactionType = 'send';
                        $transactionObj->escrow = 0;
                        $transactionObj->referenceID = $miner_fee_transaction_id;
                        $transactionObj->escrowContractAddress = '';
                        $transactionObj->createdAt = $date;
                        $transactionObj->updatedAt = $date;
                        $transactionObj->expiresAt = '';
                        $transactionObj->fee = '';
                        $transactionObj->feeUnit = '';
    
                        $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);  

                        $txHistoryObj->status = "pending";
                        $txHistoryObj->transactionID = "";
                        $txHistoryObj->transactionToken = '';
                        $txHistoryObj->senderAddress = $company_pool_address;
                        $txHistoryObj->recipientAddress = $internal_address;
                        $txHistoryObj->senderUserID = 'company_pool';
                        $txHistoryObj->recipientUserID = $xun_user_id;
                        $txHistoryObj->walletType = $minerFeeWalletType;
                        $txHistoryObj->amount =  $miner_fee;
                        $txHistoryObj->transactionType = 'nuxpay_wallet';
                        $txHistoryObj->referenceID = $miner_fee_transaction_id;
                        $txHistoryObj->createdAt = date("Y-m-d H:i:s");
                        $txHistoryObj->updatedAt = $date;
                        // $txHistoryObj->fee = $final_miner_fee;
                        // $txHistoryObj->feeWalletType = $miner_fee_wallet_type;
                        // $txHistoryObj->exchangeRate = $exchangeRate;
                        // $txHistoryObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
                        $txHistoryObj->type = 'out';
                        $txHistoryObj->gatewayType = "BC";

                        $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);

                        $transaction_history_id = $transaction_history_result['transaction_history_id'];
                        $transaction_history_table = $transaction_history_result['table_name'];

                        $updateWalletTx = array(
                            "transaction_history_table" => $transaction_history_table,
                            "transaction_history_id" => $transaction_history_id,
                        );

                        $xunWallet->updateWalletTransaction($transaction_id, $updateWalletTx);

                        //  insert to miner fee table
                        $miner_fee_tx_data = array(
                            "address" => $company_pool_address,
                            "reference_id" => $transaction_id,
                            "reference_table" => "xun_wallet_transaction",
                            "type" => 'fund_in',
                            "wallet_type" => $minerFeeWalletType,
                            "credit" => $miner_fee,
                        );
                        $xunMinerFee->insertMinerFeeTransaction($miner_fee_tx_data);

                        $company_pool_params = array(
                            "receiverAddress" => $internal_address,
                            "amount" => $miner_fee,
                            "walletType" => $minerFeeWalletType,
                            "walletTransactionID" => $transaction_id,
                            // "transactionToken" => $transaction_token,
                            "senderAddress" => $company_pool_address,

                        );
                        // $company_pool_result = $post->curl_post($internalTransferCompanyPoolURL, $company_pool_params, 0, 0, array(), 1, 1);
                        $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);
                        $company_pool_result = $xunCompanyWallet->fundOut('company_pool', $company_pool_params);

                        if($company_pool_result['code'] ==0){
                            //  full marketer commission

                            $new_marketer_wallet_balance = $xunMarketer->get_marketer_commission_balance($business_marketer_commission_id, $wallet_type);
                            $total_new_marketer_wallet_balance = bcadd($new_marketer_wallet_balance, $convertedWithdrawalAmount, $decimal_places);
                            $fund_out_failed_id = $xunCrypto->insertMarketerCommissionTransaction(0, $convertedWithdrawalAmount, $withdrawSatoshiAmount, $wallet_type, $convertedWithdrawalAmount, 0, $total_new_marketer_wallet_balance, '', 'Fund Out Failed', 0,  $userID);
                            
                            $update_wallet_transaction_arr = array(
                                "status" => 'failed',
                                "updated_at" => date("Y-m-d H:i:s"),
                            );
                            $db->where('id', $transaction_id);
                            $db->update('xun_wallet_transaction', $update_wallet_transaction_arr);

                            $db->where('id', $transaction_history_id);
                            $db->update($transaction_history_table, $update_wallet_transaction_arr);
                            
                            $tag = "Failed Marketer Fund Out";
                            $additional_message = "Error Message: " . $company_pool_result["message_d"] . "\n";
                            $additional_message .= "Input: " . json_encode($company_pool_params) . "\n"; 

                            return array('code' => 1, 'status' => "error", 'statusMsg' => $company_pool_result['message_d']);
                        }
                        else{     
                            return array('code' => 0, 'status' => "ok", 'statusMsg' => $this->get_translation_message('B00342') /* Your Withdrawal is processing */);

                        }
                      
                }
                else{
    
                    $miner_fee_transaction_id = $xunCrypto->insertMarketerCommissionTransaction(0, $converted_miner_fee, $convertedSatoshiMinerFee, $wallet_type, 0, $converted_miner_fee, 0, '', 'Miner Fee Fund Out', $fund_out_marketer_transaction_id, $userID);
                }
            }
    
            $tx_obj = new stdClass();
            $tx_obj->userID = $xun_user_id;
            $tx_obj->address = $internal_address;       
            $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);

            $xunWallet = new XunWallet($db);
            $transactionObj->status = 'pending';
            $transactionObj->transactionHash = '';
            $transactionObj->transactionToken = $transaction_token;
            $transactionObj->senderAddress = $internal_address;
            $transactionObj->recipientAddress = $destination_address;
            $transactionObj->userID = $xun_user_id;
            $transactionObj->senderUserID = $xun_user_id;
            $transactionObj->recipientUserID = '';
            $transactionObj->walletType = $wallet_type;
            $transactionObj->amount = $convertedWithdrawalAmount;
            $transactionObj->addressType = 'nuxpay_wallet';
            $transactionObj->transactionType = 'send';
            $transactionObj->escrow = 0;
            $transactionObj->referenceID = $fund_out_marketer_transaction_id;
            $transactionObj->escrowContractAddress = '';
            $transactionObj->createdAt = $date;
            $transactionObj->updatedAt = $date;
            $transactionObj->expiresAt = '';
            $transactionObj->fee = '';
            $transactionObj->feeUnit = '';

            $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);  

            $txHistoryObj->status = "pending";
            $txHistoryObj->transactionID = "";
            $txHistoryObj->transactionToken = $transaction_token;
            $txHistoryObj->senderAddress = $internal_address;
            $txHistoryObj->recipientAddress = $destination_address;
            $txHistoryObj->senderUserID = $xun_user_id;
            $txHistoryObj->recipientUserID = '';
            $txHistoryObj->walletType = $minerFeeWalletType;
            $txHistoryObj->amount =  $miner_fee;
            $txHistoryObj->transactionType = 'nuxpay_wallet';
            $txHistoryObj->referenceID = $miner_fee_transaction_id;
            $txHistoryObj->createdAt = date("Y-m-d H:i:s");
            $txHistoryObj->updatedAt = $date;
            // $txHistoryObj->fee = $final_miner_fee;
            // $txHistoryObj->feeWalletType = $miner_fee_wallet_type;
            // $txHistoryObj->exchangeRate = $exchangeRate;
            // $txHistoryObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
            $txHistoryObj->type = 'out';
            $txHistoryObj->gatewayType = "BC";

            $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);

            $transaction_history_id = $transaction_history_result['transaction_history_id'];
            $transaction_history_table = $transaction_history_result['table_name'];

            $updateWalletTx = array(
                "transaction_history_table" => $transaction_history_table,
                "transaction_history_id" => $transaction_history_id,
            );

            $xunWallet->updateWalletTransaction($transaction_id, $updateWalletTx);

            if($transaction_type == 'external'){
        

                $curlParams = array(
                    "command" => "fundOutExternal",
                    "params" => array(
                        "senderAddress" => $internal_address,
                        "receiverAddress" => $destination_address,
                        "amount" => $convertedWithdrawalAmount,
                        "walletType" => strtolower($wallet_type),
                        "transactionToken" => $transaction_token,
                        "walletTransactionID" => $transaction_id
                    )
                );
                
            }
            else if($transaction_type == 'internal'){
            
                $curlParams = array(
                    "command" => "fundOutCompanyWallet",
                    "params" => array(
                        "senderAddress" => $internal_address,
                        "receiverAddress" => $destination_address,
                        "amount" => $convertedWithdrawalAmount,
                        "satoshiAmount" => $withdrawSatoshiAmount,
                        "walletType" => strtolower($wallet_type),
                        "id" => $transaction_id,
                        "transactionToken" => $transaction_token,
                        "addressType" => "nuxpay_wallet",
                    ),
                );
            
            }
        
            $curlResponse = $post->curl_post($prepaidWalletServerURL, $curlParams, 0);

            if ($curlResponse['code'] == 1) {
                $update_wallet_transaction_id = array(
                    "reference_id" => $transaction_id,
                    "updated_at" => date("Y-m-d H:i:s"),
                );
                $db->where('id', $marketer_commission_transaction_id);
                $db->update('xun_marketer_commission_transaction', $update_wallet_transaction_id);
                $tag = "Reseller Withdrawal";

            } else {
                //  full marketer commission
            
                $new_marketer_wallet_balance = $xunMarketer->get_marketer_commission_balance($business_marketer_commission_id, $wallet_type);
                $total_new_marketer_wallet_balance = bcadd($new_marketer_wallet_balance, $convertedWithdrawalAmount, $decimal_places);
                $fund_out_failed_id = $xunCrypto->insertMarketerCommissionTransaction(0, $convertedWithdrawalAmount, $satoshi_amount, $wallet_type, $convertedWithdrawalAmount, 0, $total_new_marketer_wallet_balance, '', 'Fund Out Failed', 0,  $userID);
                
                $update_wallet_transaction_arr = array(
                    "status" => 'failed',
                    "updated_at" => date("Y-m-d H:i:s"),
                );
                $db->where('id', $transaction_id);
                $db->update('xun_wallet_transaction', $update_wallet_transaction_arr);

                $db->where('id', $transaction_history_id);
                $db->update($transaction_history_table, $update_wallet_transaction_arr);
                
                $tag = "Failed Reseller Withdrawal";
                $additional_message = "Error Message: " . $company_pool_result["message_d"] . "\n";
                $additional_message .= "Input: " . json_encode($company_pool_params) . "\n";
            }

            $message .= "Reseller Name:".$reseller_name."\n";
            $message .= "Amount:" .$convertedWithdrawalAmount."\n";
            $message .= "Wallet Type:".$wallet_type."\n";

            if($additional_message){
                $message .= $additional_message;
            }
            $message .= "Time: ".date("Y-m-d H:i:s")."\n";

            $thenux_params["tag"]         = $tag;
            $thenux_params["message"]     = $message;
            $thenux_params["mobile_list"] = $xun_numbers;
            $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
            
            return array('code' => 0, 'status' => "ok", 'statusMsg' => $this->get_translation_message('B00342') /* Your Withdrawal is processing */);
        }

        public function get_reseller_sales_listing($params){

            $db= $this->db;
            $general = $this->general;
            $setting = $this->setting;
            
            $reseller_page_limit = $setting->getResellerPageLimit();
            $date_from         = $params["date_from"];
            $date_to           = $params["date_to"];
            $user_id           = $params["user_id"];
            $page_number        = $params["page"];
            $page_size          = $params["page_size"] ? $params["page_size"] : $reseller_page_limit;
            $current_date       = date("Y-m-d");

            if ($page_number < 1) {
                $page_number = 1;
            }

            $start_limit = ($page_number - 1) * $page_size;
            $limit       = array($start_limit, $page_size);
            
            if ($user_id == ""){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00006') /*ID cannot be empty.*/, 'developer_msg' => 'ID cannot be empty.');
            }

            $db->where("id", $user_id);
            $userDetail = $db->getOne("reseller", "user_id, type, created_at, source");
            $userType = $userDetail['type'];
            $source = $userDetail['source'];
            
            if($userType == "distributor"){
                
                 if($date_from){
                    $db->where('transaction_date', $date_from, '>=');
                }
        
                if($date_to){
                    $db->where('transaction_date', $date_to, '<');
                }

                $db->where("a.distributor_id", $user_id);
                $db->where("a.type", "reseller");
                $db->where("b.type", "business");
                $db->join("xun_user b", "b.reseller_id=a.id", "INNER");
                $db->join("xun_crypto_history_summary c", "c.business_id=b.id", "INNER");
                $db->groupBy("c.transaction_date");
                $dSummaryDetails = $db->map('transaction_date')->ArrayBuilder()->get("reseller a", null, "c.transaction_date, count(c.business_id) as total_transaction,  SUM(c.total_amount_usd) as total_transacted_amount_usd, SUM(c.total_transaction_fee_usd) as total_sales_usd");

                $db->where("a.distributor_id", $user_id);
                $db->where("a.type", "reseller");
                $db->where("b.type", "business");
                $db->join("xun_user b", "b.reseller_id=a.id", "INNER");
                $db->join("xun_crypto_history c", "c.business_id=b.id", "INNER");
                $current_date_transaction2 = $db->get("reseller a", null, "c.transaction_date, c.business_id, c.amount, c.transaction_fee, c.exchange_rate");

            }else if($userType == "reseller"){
                
                if($date_from){
                    $db->where('transaction_date', $date_from, '>=');
                }
        
                if($date_to){
                    $db->where('transaction_date', $date_to, '<');
                }

                $db->where("a.reseller_id", $user_id);
                $db->where("a.type", "business");
                $db->join("xun_crypto_history_summary b", "b.business_id=a.id", "INNER");
                $db->groupBy("b.transaction_date");
                $rSummaryDetails = $db->map('transaction_date')->ArrayBuilder()->get("xun_user a", null, "b.transaction_date, count(b.business_id) as total_transaction, SUM(b.total_amount_usd) as total_transacted_amount_usd, SUM(b.total_transaction_fee_usd) as total_sales_usd");
                
                $db->where("a.reseller_id", $user_id);
                $db->where("a.type", "business");
                $db->join("xun_crypto_history b", "b.business_id=a.id", "INNER");
                $current_date_transaction3 = $db->get("xun_user a", null, "b.transaction_date, b.business_id, b.amount, b.transaction_fee, b.exchange_rate");

            }else if($userType == "siteadmin"){
                // if($date_from){
                //     $db->where('transaction_date', $date_from, '>=');
                // }
        
                // if($date_to){
                //     $db->where('transaction_date', $date_to, '<');
                // }

                // $db->where("a.type", "business");
                // $db->where("a.register_site", $source);
                // $db->join("xun_crypto_history_summary b", "b.business_id=a.id", "INNER");
                // $db->groupBy("b.transaction_date");
                // $saSummaryDetails = $db->map('transaction_date')->ArrayBuilder()->get("xun_user a", null, "b.transaction_date, count(b.business_id) as total_transaction, SUM(b.total_amount_usd) as total_transacted_amount_usd, SUM(b.total_transaction_fee_usd) as total_sales_usd");
                
                $strSql = "select transaction_date, total_transaction, total_transacted_amount_usd, total_sales_usd, IFNULL(netComAccProfit,0) as total_net_profit, IFNULL(mc,0) as Total_Marketer_commission from (
                    SELECT  b.transaction_date, count(b.business_id) as total_transaction, SUM(b.total_amount_usd) as total_transacted_amount_usd, SUM(b.total_transaction_fee_usd) as total_sales_usd FROM xun_user a INNER JOIN xun_crypto_history_summary b on b.business_id=a.id WHERE  a.type = 'business'  AND a.register_site = '$source'  GROUP BY b.transaction_date
                    ) a left join (SELECT sum(amount) as netComAccProfit, CAST(created_at AS DATE) as date2  FROM `xun_wallet_transaction` where address_type = 'company_acc' and status = 'completed' group by transaction_history_table) b on a.transaction_date = b.date2 
                    Left join (SELECT sum(amount) as mc, CAST(created_at AS DATE) as date3  FROM xun_wallet_transaction WHERE address_type = 'marketer' and status = 'completed' group by transaction_history_table) c on a.transaction_date = c.date3 
                    WHERE transaction_date is not null";

                if($date_from){
                    $strSql .= " and transaction_date >= '" .$date_from."'";
                } 
                if($date_to){
                    $strSql .= " and transaction_date < '" .$date_to."'";
                }

                $strSql .= " order by TRANSACTION_date desc;";
                $saSummaryDetails = $db->query($strSql);

                $db->where("a.type", "business");
                $db->where("a.register_site", $source);
                $db->join("xun_crypto_history b", "b.business_id=a.id", "INNER");
                $current_date_transaction = $db->get("xun_user a", null, "b.transaction_date, b.business_id, b.amount, b.transaction_fee, b.exchange_rate");
            }

            //Distributor
            $total_transaction = 0;
            foreach($current_date_transaction2 as $key => $value){
                $transaction_date   = $value['transaction_date'];
                $amount             = $value["amount"];
                $transaction_fee    = $value['transaction_fee'];
                $exchange_rate      = $value['exchange_rate'];
                
                $transacted_amount_usd = bcmul($amount, $exchange_rate, 8);
                $sales_usd = bcmul($transaction_fee, $exchange_rate, 8);
                $transaction_date_only = date('Y-m-d', strtotime($transaction_date));

                if($transaction_date_only == $current_date){
                    $total_transaction++;
                    $total_transacted_amount_usd = bcadd($total_transacted_amount_usd, $transacted_amount_usd, 8);
                    $total_sales_usd = bcadd($total_sales_usd, $sales_usd, 8);
                    
                    $total_transacted_amount_usd = number_format($total_transacted_amount_usd, 2);
                    $total_sales_usd = number_format($total_sales_usd, 2);
                    
                    $current_date_transaction_arr2 = array(
                        "transaction_date_only" => $transaction_date_only,
                        "total_transaction"     => $total_transaction,
                        "total_transacted_amount_usd" => $total_transacted_amount_usd,
                        "total_sales_usd" => $total_sales_usd,                    
                    );
                    $new_current_date_transaction_arr2[$transaction_date_only] = $current_date_transaction_arr2;
                    krsort($new_current_date_transaction_arr2);
                }
                
            }
            //Reseller
            $total_transaction = 0;
            foreach($current_date_transaction3 as $key => $value){
                $transaction_date   = $value['transaction_date'];
                $amount             = $value["amount"];
                $transaction_fee    = $value['transaction_fee'];
                $exchange_rate      = $value['exchange_rate'];
                
                $transacted_amount_usd = bcmul($amount, $exchange_rate, 8);
                $sales_usd = bcmul($transaction_fee, $exchange_rate, 8);
                $transaction_date_only = date('Y-m-d', strtotime($transaction_date));

                if($transaction_date_only == $current_date){
                    $total_transaction++;
                    $total_transacted_amount_usd = bcadd($total_transacted_amount_usd, $transacted_amount_usd, 8);
                    $total_sales_usd = bcadd($total_sales_usd, $sales_usd, 8);
                    
                    $total_transacted_amount_usd = number_format($total_transacted_amount_usd, 2);
                    $total_sales_usd = number_format($total_sales_usd, 2);

                    $current_date_transaction_arr3 = array(
                        "transaction_date_only" => $transaction_date_only,
                        "total_transaction"     => $total_transaction,
                        "total_transacted_amount_usd" => $total_transacted_amount_usd,
                        "total_sales_usd" => $total_sales_usd
                    
                    );
                    $new_current_date_transaction_arr3[$transaction_date_only] = $current_date_transaction_arr3;
                    krsort($new_current_date_transaction_arr3);
                }
                
            }
            
            //SiteAdmin
            $total_transaction = 0;
            foreach($current_date_transaction as $key => $value){
                $transaction_date   = $value['transaction_date'];
                $amount             = $value["amount"];
                $transaction_fee    = $value['transaction_fee'];
                $exchange_rate      = $value['exchange_rate'];
                
                
                $transacted_amount_usd = bcmul($amount, $exchange_rate, 8);
                $sales_usd = bcmul($transaction_fee, $exchange_rate, 8);
                $transaction_date_only = date('Y-m-d', strtotime($transaction_date));

                if($transaction_date_only == $current_date){
                    $total_transaction++;
                    $total_transacted_amount_usd = bcadd($total_transacted_amount_usd, $transacted_amount_usd, 8);
                    $total_sales_usd = bcadd($total_sales_usd, $sales_usd, 8);
                    
                    $total_transacted_amount_usd = number_format($total_transacted_amount_usd, 2);
                    $total_sales_usd = number_format($total_sales_usd, 2);

                    $current_date_transaction_arr = array(
                        "transaction_date_only" => $transaction_date_only,
                        "total_transaction"     => $total_transaction,
                        "total_transacted_amount_usd" => $total_transacted_amount_usd,
                        "total_sales_usd" => $total_sales_usd
                    );
                    $new_current_date_transaction_arr[$transaction_date_only] = $current_date_transaction_arr;
                    krsort($new_current_date_transaction_arr);
                }
                
                
            }
            
            
            if ($date_from == '' && $date_to == ''){
                $listingStartDate = $userDetail["created_at"];
                $listingStartDate = strtotime($listingStartDate);
                $listingStartDate = strtotime(date('Y-m-d', strtotime("-1 days", $listingStartDate)));
                $today = strtotime(date("Y-m-d 23:59:59"));
                $today = strtotime(date('Y-m-d', strtotime("-1 days", $today)));

            }else {
                $date_from = strtotime(date($date_from));
                $date_to = strtotime(date($date_to));
                $listingStartDate = $date_from;
                $listingStartDate = strtotime(date('Y-m-d', strtotime("-1 days", $listingStartDate)));
                $today = $date_to;
                $today = strtotime(date('Y-m-d', strtotime("-1 days", $today)));

            }  
    
            if($userType == "distributor"){
                
                while($listingStartDate <= $today){
                    $listingStartDate = strtotime(date('Y-m-d', strtotime("+1 days", $listingStartDate)));
                    $listingStartDateConverted =  date('Y-m-d', $listingStartDate);
                    $transaction_date = $dSummaryDetails[$listingStartDateConverted]["transaction_date"];
                    $data_today2 = $new_current_date_transaction_arr2[$current_date];

                    if(!$transaction_date){

                        $dSummaryDetails[$listingStartDateConverted]["transaction_date"] = $listingStartDateConverted;
                        $dSummaryDetails[$listingStartDateConverted]["total_transaction"] = 0;
                        $dSummaryDetails[$listingStartDateConverted]["total_transacted_amount_usd"] = 0;
                        $dSummaryDetails[$listingStartDateConverted]["total_sales_usd"] = 0;
                    }
                    
                    if($listingStartDateConverted == $current_date){
                        if(!$data_today2){
                            $dSummaryDetails[$current_date]["transaction_date"] = $current_date;
                            $dSummaryDetails[$current_date]["total_transaction"] = 0;
                            $dSummaryDetails[$current_date]["total_transacted_amount_usd"] = 0;
                            $dSummaryDetails[$current_date]["total_sales_usd"] = 0;
                        }else{
                            $dSummaryDetails[$current_date]["transaction_date"] = $data_today2["transaction_date_only"];
                            $dSummaryDetails[$current_date]["total_transaction"] = $data_today2["total_transaction"];
                            $dSummaryDetails[$current_date]["total_transacted_amount_usd"] = $data_today2["total_transacted_amount_usd"];
                            $dSummaryDetails[$current_date]["total_sales_usd"] = $data_today2["total_sales_usd"];
                        }
                                            
                    }
                    krsort($dSummaryDetails);

                }
                    
            }else if($userType == "reseller"){
                while($listingStartDate <= $today){
                    
                    $listingStartDate = strtotime(date('Y-m-d', strtotime("+1 days", $listingStartDate)));
                    $listingStartDateConverted =  date('Y-m-d', $listingStartDate);
                    $transaction_date = $rSummaryDetails[$listingStartDateConverted]["transaction_date"];
                    $data_today3 = $new_current_date_transaction_arr3[$current_date];
                    if($transaction_date == ""){

                        $rSummaryDetails[$listingStartDateConverted]["transaction_date"] = $listingStartDateConverted;
                        $rSummaryDetails[$listingStartDateConverted]["total_transaction"] = 0;
                        $rSummaryDetails[$listingStartDateConverted]["total_transacted_amount_usd"] = 0;
                        $rSummaryDetails[$listingStartDateConverted]["total_sales_usd"] = 0;
                    }
                    if($listingStartDateConverted == $current_date){
                        if(!$data_today3){
                            $rSummaryDetails[$current_date]["transaction_date"] = $current_date;
                            $rSummaryDetails[$current_date]["total_transaction"] = 0;
                            $rSummaryDetails[$current_date]["total_transacted_amount_usd"] = 0;
                            $rSummaryDetails[$current_date]["total_sales_usd"] = 0;
                        }else{
                            $rSummaryDetails[$current_date]["transaction_date"] = $data_today3["transaction_date_only"];
                            $rSummaryDetails[$current_date]["total_transaction"] = $data_today3["total_transaction"];
                            $rSummaryDetails[$current_date]["total_transacted_amount_usd"] = $data_today3["total_transacted_amount_usd"];
                            $rSummaryDetails[$current_date]["total_sales_usd"] = $data_today3["total_sales_usd"];
                        }
                                            
                    }

                    krsort($rSummaryDetails);

                    
                }
                
            }else if($userType == "siteadmin"){
                
                // while($listingStartDate <= $today){
                    
  
                //     $listingStartDate = strtotime(date('Y-m-d', strtotime("+1 days", $listingStartDate)));
                //     $listingStartDateConverted =  date('Y-m-d', $listingStartDate);
                //     $data_today = $new_current_date_transaction_arr[$current_date];
                //     $transaction_date = $saSummaryDetails[$listingStartDateConverted]["transaction_date"];

                    
                //     if(!$transaction_date){

                //         $saSummaryDetails[$listingStartDateConverted]["transaction_date"] = $listingStartDateConverted;
                //         $saSummaryDetails[$listingStartDateConverted]["total_transaction"] = 0;
                //         $saSummaryDetails[$listingStartDateConverted]["total_transacted_amount_usd"] = 0;
                //         $saSummaryDetails[$listingStartDateConverted]["total_sales_usd"] = 0;
                //         $saSummaryDetails[$listingStartDateConverted]["total_net_profit"] = 0;
                //         $saSummaryDetails[$listingStartDateConverted]["Total_Marketer_commission"] = 0;
                //     }
                    
                //     if($listingStartDateConverted == $current_date){
                //         if(!$data_today){
                //             $saSummaryDetails[$current_date]["transaction_date"] = $current_date;
                //             $saSummaryDetails[$current_date]["total_transaction"] = 0;
                //             $saSummaryDetails[$current_date]["total_transacted_amount_usd"] = 0;
                //             $saSummaryDetails[$current_date]["total_sales_usd"] = 0;
                //             $saSummaryDetails[$current_date]["total_net_profit"] = 0;
                //             $saSummaryDetails[$current_date]["Total_Marketer_commission"] = 0;
                //         }else{
                //             $saSummaryDetails[$current_date]["transaction_date"] = $data_today["transaction_date_only"];
                //             $saSummaryDetails[$current_date]["total_transaction"] = $data_today["total_transaction"];
                //             $saSummaryDetails[$current_date]["total_transacted_amount_usd"] = $data_today["total_transacted_amount_usd"];
                //             $saSummaryDetails[$current_date]["total_sales_usd"] = $data_today["total_sales_usd"];
                //         }
                                            
                //     }
                //     krsort($saSummaryDetails);
                    
                // }
            }
            
            if($userType == "distributor"){
                // $totalRecord = count($dSummaryDetails);
                // $page_size = 25;
                $data["data"]       = $dSummaryDetails;                
                $data["pageNumber"]       = $page_number;
                $data["totalRecord"]      = $totalRecord;
                $data["numRecord"]        = $page_size;
                $data["totalPage"]        = ceil($totalRecord/$page_size);

                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('E00397') /*Success.*/, 'data' => $data);

            }else if($userType == "reseller"){
                // $totalRecord = count($rSummaryDetails);
                $data["data"]       = $rSummaryDetails;                
                $data["pageNumber"]       = $page_number;
                $data["totalRecord"]      = $totalRecord;
                $data["numRecord"]        = $page_size;
                $data["totalPage"]        = ceil($totalRecord/$page_size);

                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('E00397') /*Success.*/, 'data' => $data);
            }else if($userType == "siteadmin"){
                // $totalRecord = count($rSummaryDetails);
                $data["data"]       = $saSummaryDetails;                
                $data["pageNumber"]       = $page_number;
                $data["totalRecord"]      = $totalRecord;
                $data["numRecord"]        = $page_size;
                $data["totalPage"]        = ceil($totalRecord/$page_size);

                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('E00397') /*Success.*/, 'data' => $data);
            }
    
        }

        public function get_reseller_sales_Detail($params, $userID){

            $db= $this->db;
            $general = $this->general;
            $setting = $this->setting;
            
            $transactionDate    = $params["transactionDate"];
            $user_id            = $params["user_id"];
            $current_date       = date("Y-m-d");

            if ($user_id == ""){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00006') /*ID cannot be empty.*/, 'developer_msg' => 'ID cannot be empty.');
            }

            $db->where("id", $user_id);
            $userDetail = $db->getOne("reseller", "user_id, type, created_at, source");
            $userType = $userDetail['type'];
            $source = $userDetail['source'];
            
            if($userType == "siteadmin"){
                $strSql = "select a.transaction_date, case when c.nickname <> '' then c.nickname else a.business_id end as name, 
                    a.total_transacted_amount_usd, total_sales_usd, IFNULL(b.total_marketer,0) as total_marketer from (
                    SELECT  b.transaction_date, b.business_id, SUM(b.total_amount_usd) as total_transacted_amount_usd, 
                    SUM(b.total_transaction_fee_usd) as total_sales_usd FROM xun_user a 
                    INNER JOIN xun_crypto_history_summary b on b.business_id=a.id WHERE  a.type = 'business'  
                    AND a.register_site = 'Nuxpay' and b.transaction_date = '$transactionDate'  GROUP BY b.business_id) a 
                    left join (SELECT b.business_id, sum(amount) as total_marketer FROM xun_marketer_commission_transaction a 
                    left join xun_business_marketer_commission_scheme  b on a.business_marketer_commission_id = b.id 
                    where CAST(a.created_at AS DATE) = '$transactionDate' and a.type = 'Transfer In' group by business_id) b 
                    on a.business_id = b.business_id left join xun_user c on a.business_id = c.id;";

                $saSummaryDetails = $db->query($strSql);
            }

            if($userType == "siteadmin"){
                $data["data"]       = $saSummaryDetails;                
                
                return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('E00397') /*Success.*/, 'data' => $data);
            }
        
        }

        public function reseller_add_username($params, $userID){
            $db= $this->db;
            $general = $this->general;
            $setting = $this->setting;
            
            $username = $params['username'];
            
            if($username == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00404') /*Username cannot be empty.*/, 'developer_msg' => 'Username cannot be empty.');
            }

            $db->where('id', $userID);
            $reseller = $db->getOne('reseller');

            if(!$reseller){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00571') /*Reseller not found.*/, 'developer_msg' => 'Reseller not found.');
            }

            $db->where('username', $username);
            $reseller_data = $db->getOne('reseller');

            if($reseller_data){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00548') /*Reseller username already exists.*/, 'developer_msg' => 'Reseller username already exists', 'lq' => $db->getLastQuery());
            }

            $updateReseller = array(
                "username" => $username,
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $db->where('id', $userID);
            $updated = $db->update('reseller', $updateReseller);

            if(!$updated){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
            }
            $data['username'] = $username;

            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00359') /*Username added successfully.*/, 'data' => $data);
        }
    }
 ?>
