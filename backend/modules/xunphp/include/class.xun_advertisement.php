<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * Date 05/01/2021.
    **/


    class XunAdvestiment {
        
        function __construct($db, $general, $setting) {
            $this->db = $db;
            $this->general = $general;
            $this->setting = $setting;
        }
        
        public function get_translation_message($message_code) {
            // Language Translations.
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $message = $translations[$message_code][$language];
            return $message;
        }
        
        function get_advertisement_detail($param) {

            $db = $this->db;

            $ads_id = $param['ads_id'];

            if($ads_id=="") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00626')/* Advertisement ID cannot be empty. */);
            }

            $db->where('bs.id', $ads_id);
            // $db->where('c.status', 1);
            $db->join('xun_business b', 'b.user_id=bs.business_id', 'INNER');
            // $db->join('xun_marketplace_currencies c', 'c.currency_id=bs.wallet_type', 'INNER');
            $result = $db->getOne('xun_buynsell bs', 'bs.id, bs.business_id, b.name, bs.wallet_type, bs.content, bs.amount, bs.type, bs.disabled, bs.expire_at, bs.updated_at, bs.created_at');

            if($result) {
                
                if($result['disabled']==1) {
                    $result['status'] = 'Deleted';
                } else if($result['disabled']==0 && $result['expire_at']>date('Y-m-d H:i:s')) {
                    $result['status'] = 'Active';
                } else {
                    $result['status'] = 'Expired';
                }

                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('E00631')/* Advertisement Details */, 'data' => $result);

            } else {

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00628')/* You're not allowed to performed this operation. */);

            }

        }

        function get_advertisement_listing($param, $user_id) {

            $db = $this->db;
            $setting = $this->setting;

            $type = $param['type'];
        
            $page_limit = $setting->systemSetting["memberBlogPageLimit"];
            $page_number = $param["page"] ? $param["page"] : 1;
            $page_size = $param["page_size"] ? $param["page_size"] : $page_limit;


            $start_limit = ($page_number - 1) * $page_size;
            $limit = array($start_limit, $page_size);


            if($type=="") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00238')/*'Type cannot be empty'*/);
            }

            if($type!="buy" && $type!="sell" && $type!="myads" && $type!="all") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00334')/*'Invalid type'*/);
            }

            if($type=="buy" || $type=="sell") {
                $db->where('bs.disabled', 0);
                $db->where('bs.expire_at', date('Y-m-d H:i:s'), '>=');
                $db->where('bs.type', $type);
            }

            if($type=="myads") {
                $db->where('bs.business_id', $user_id);
            }

            if($type=="all"){
                $db->where('bs.disabled', 0);
            }
            
            
            // $db->where('c.status', 1);
            $db->join('xun_business b', 'b.user_id=bs.business_id', 'INNER');
            // $db->join('xun_marketplace_currencies c', 'c.currency_id=bs.wallet_type', 'INNER');

            $dbCopy = $db->copy();
            $totalRecord = $dbCopy->getValue('xun_buynsell bs', 'COUNT(*)');

            $db->orderBy('bs.id', 'DESC');
            $result = $db->get('xun_buynsell bs', $limit, 'bs.id, bs.business_id, b.name, bs.wallet_type, bs.content, bs.amount, bs.type, bs.disabled, bs.expire_at, bs.updated_at, bs.created_at');
            
            $arr_listing = array();

            foreach($result as $detail) {

                if($detail['disabled']==1) {
                    $detail['status'] = 'Deleted';
                } else if($detail['disabled']==0 && $detail['expire_at']>date('Y-m-d H:i:s')) {
                    $detail['status'] = 'Active';
                } else {
                    $detail['status'] = 'Expired';

                }

                $arr_listing[] = $detail;
            }

            $total_page = ceil($totalRecord/$page_size);

            $data['listing'] = $arr_listing;
            $data['totalPage']    = $total_page;
            $data['pageNumber']   = $page_number;
            $data['totalRecord']  = $totalRecord;
            $data['numRecord']    = $page_size;

            return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('E00630') /* Advertisement listing.*/, "data" => $data);
        }

        function update_advestiment($param, $user_id) {

            $db = $this->db;

            $ads_id = $param['ads_id'];
            $amount = $param['amount'];
            $content = $param['content'];

            if($ads_id=="") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00626')/* Advertisement ID cannot be empty. */);
            }

            //Amount 
            if($amount=="") {
                $amount=0;
                // return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00305')/*Amount cannot be empty.*/);
            } 
            if($amount < 0 || !is_numeric($amount)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00320')/*Invalid Amount.*/);
            } 
            //Content
            if($content=="") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00620')/*Content Cannot be empty*/);
            } 

            if(strlen($content) > 200 ) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00621')/*Content length cannot more than 200 Words*/);
            } 

            $db->where('id', $ads_id);
            $db->where('business_id', $user_id);
            $db->where('disabled', 0);
            $result = $db->getOne('xun_buynsell');

            if($result) {
                
                $db->where('id', $ads_id);
                $db->update('xun_buynsell', array('content'=>$content, 'amount'=>$amount, 'updated_at'=>date('Y-m-d H:i:s')));

                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('E00629')/* Advertisement details updated. */);

            } else {

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00628')/* You're not allowed to performed this operation. */);

            }

        }

        function delete_advestiment($param, $user_id) {

            $db = $this->db;

            $ads_id = $param['ads_id'];

            if($ads_id=="") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00626')/* Advertisement ID cannot be empty. */);
            }

            $db->where('id', $ads_id);
            $db->where('business_id', $user_id);
            $db->where('disabled', 0);
            $result = $db->getOne('xun_buynsell');

            if($result) {
                $db->where('id', $ads_id);
                $db->update('xun_buynsell', array('disabled'=>1, 'updated_at'=>date('Y-m-d H:i:s')));

                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('E00627')/* Advertisement deleted successfully. */);

            } else {

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00628')/* You're not allowed to performed this operation. */);

            }

        }

        function create_advestiment($param, $user_id) {
            
            $db = $this->db;

            $type = $param['type'];
            // $wallet_type = $param['wallet_type'];
            // $amount = $param['amount'];
            $content = $param['content'];
            $setting = $this->setting;
            $StartDateTime = date('Y-m-d')." 00:00:00";
            $EndDateTime = date('Y-m-d')." 23:59:59";
            $currentDateTime = date('Y-m-d H:i:s');

            
            $daily_post_limit = $setting->systemSetting["buySellAdsMaxDailyLimit"];
            $duration =$setting->systemSetting["buySellAdsDuration"]; 

            //Type buy/sell
            if($type=="") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00238')/*'Type cannot be empty'*/, 'b');
            }
            if($type!="buy" && $type!="sell") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00334')/*'Invalid type'*/, 'c');
            }

            //Currency if status !=1 will prompt error
            // if($wallet_type=="" ){
            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00625')/*Wallet Type Can Not Empty*/);
            // } else {
            //     $db->where("currency_id", $wallet_type);
            //     $db->where("is_payment_gateway",1);
            //     $result = $db->get("xun_coins");

            //     if(!$result) {
            //         return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00624')/*Invalid Wallet Type*/);
            //     }
            // }

            //Amount 
            // if($amount=="") {
            //     $amount = 0;
            //     // return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00305')/*Amount cannot be empty.*/);
            // } 
            // if($amount < 0 || !is_numeric($amount)) {
            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00320')/*Invalid Amount.*/);
            // } 
            //Content
            if($content=="") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00620')/*Content Cannot be empty*/);
            } 

            if(strlen($content) > 200 ) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00621')/*Content length cannot more than 200 Words*/);
            } 

            
            //Daily Limit
            $db->where("business_id", $user_id);
            $db->where("created_at", $StartDateTime, '>=');
            $db->where("created_at", $EndDateTime, '<=');
            $dailyLimit = $db->getValue("xun_buynsell", "COUNT(*)");

            if($dailyLimit>= $daily_post_limit){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00622')/*You have reached the maximum daily limit for creating the advertisement.*/, 'a');
            }

            //Advestiment Duration
            $expire_at = date("Y-m-d H:i:s", strtotime($currentDateTime." + ".$duration." minutes"));

            $insertData = array (
               'business_id' => $user_id,
            //    'wallet_type' => $wallet_type,
               'content' => $content,
            //    'amount' => $amount,
               'type' => $type,
               'disabled' => 0,
               'expire_at' => $expire_at,
               'created_at' => $currentDateTime,
               'updated_at' => $currentDateTime
                );
               $announcementID = $db->insert('xun_buynsell', $insertData);


               return array('status' => "SUCCESS", 'code' => 1, 'message_d'=> $this->get_translation_message('E00623')/*Advertisement created successfully.*/, 'data' => "");


        }


        //buy sell
        function get_buy_sell_wallet_type(){
            
            global $config, $db;
            
           // $wallet_list = $config["cryptoWalletType"];
           $db->where('a.is_buy_sell', 1);
           $db->join('xun_marketplace_currencies b', 'a.currency_id = b.currency_id', 'INNER');
           $db->orderBy("a.sequence", "ASC");
           $xun_coins = $db->get('xun_coins a', null, 'b.name, b.currency_id, b.image, b.symbol, b.display_symbol');

           foreach($xun_coins as $key => $value){
               $wallet_type = $value['currency_id'];
               $name = $value['name'];
               $image = $value['image'];
               $symbol = $value['symbol'];
               $display_symbol = $value['display_symbol'];

                if($wallet_type == 'tetherusd'){
                    $wallet_list[] = $wallet_type;
                    $coin_array = array(
                        "name" => $name,
                        "wallet_type" => $wallet_type,
                        "image" => $image,
                        "symbol" => strtoupper($symbol),
                        "display_symbol" => strtoupper($display_symbol)
                    );
                    $coin_list[] = $coin_array;
                }
               
           }
           foreach($xun_coins as $key => $value){
                $wallet_type = $value['currency_id'];
                $name = $value['name'];
                $image = $value['image'];
                $symbol = $value['symbol'];
                $display_symbol = $value['display_symbol'];

                 if($wallet_type != 'tetherusd'){
                     $wallet_list[] = $wallet_type;
                     $coin_array = array(
                         "name" => $name,
                         "wallet_type" => $wallet_type,
                         "image" => $image,
                         "symbol" => strtoupper($symbol),
                         "display_symbol" => strtoupper($display_symbol)
                     );
                     $coin_list[] = $coin_array;
                 }
            }
            $returnData["wallet_types"] = $wallet_list;
            $returnData["coin_data"] = $coin_list;
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00090') /*Wallet Types.*/, "code" => 1, "result" => $returnData);
        
        }

        function create_buysell_order($param, $user_id) {
            
            $db = $this->db;

            $type = $param['type'];
            $content = $param['content'];
            $currency = $param['currency'];
            $contactInfo = $param['contactInfo'];

            $setting = $this->setting;
            $StartDateTime = date('Y-m-d')." 00:00:00";
            $EndDateTime = date('Y-m-d')." 23:59:59";
            $currentDateTime = date('Y-m-d H:i:s');

            
            $daily_post_limit = $setting->systemSetting["buySellAdsMaxDailyLimit"];
            $duration =$setting->systemSetting["buySellAdsDuration"]; 

            //Type buy/sell
            if($type=="") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00238')/*'Type cannot be empty'*/);
            }
            if($type!="buy" && $type!="sell") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00334')/*'Invalid type'*/);
            }

            //Content
            if($content=="") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00620')/*Content Cannot be empty*/);
            } 

            if(strlen($content) > 200 ) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00621')/*Content length cannot more than 200 Words*/);
            } 

            if(count($currency)==0 || !is_array($currency)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00180')/* Currency is required. */);
            }
            
            if(count($contactInfo)==0 || !is_array($contactInfo)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('M01964')/* Contact info is required. */);
            }

            //Daily Limit
            $db->where("business_id", $user_id);
            $db->where("created_at", $StartDateTime, '>=');
            $db->where("created_at", $EndDateTime, '<=');
            $dailyLimit = $db->getValue("xun_buysell_order", "COUNT(*)");

            if($dailyLimit>= $daily_post_limit){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00622')/*You have reached the maximum daily limit for creating the advertisement.*/, 'a');
            }

            //Advestiment Duration
            $expire_at = date("Y-m-d H:i:s", strtotime($currentDateTime." + ".$duration." minutes"));

            $insertData = array (
               'business_id' => $user_id,
               'content' => $content,
               'type' => $type,
               'disabled' => 0,
               'expire_at' => $expire_at,
               'created_at' => $currentDateTime,
               'updated_at' => $currentDateTime);
            $orderId = $db->insert('xun_buysell_order', $insertData);

            foreach($currency as $coin) {

                $insertData = array (
                   'order_id' => $orderId,
                   'wallet_type' => $coin,
                   'created_at' => $currentDateTime);

                $db->insert('xun_buysell_wallettype', $insertData);   
            }

            foreach($contactInfo as $info) {

                $insertData = array (
                   'order_id' => $orderId,
                   'type' => $info['type'],
                   'detail' => $info['detail'],
                   'created_at' => $currentDateTime);

                $db->insert('xun_buysell_contactinfo', $insertData);  
            }

            return array('status' => "SUCCESS", 'code' => 1, 'message_d'=> $this->get_translation_message('E00623')/*Advertisement created successfully.*/, 'data' => "");

        }

        function delete_buysell($param, $user_id) {

            $db = $this->db;

            $order_id = $param['order_id'];

            if($order_id=="") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00626')/* Order ID cannot be empty. */);
            }

            $db->where('id', $order_id);
            $db->where('business_id', $user_id);
            $db->where('disabled', 0);
            $result = $db->getOne('xun_buysell_order');

            if($result) {
                $db->where('id', $order_id);
                $db->update('xun_buysell_order', array('disabled'=>1, 'updated_at'=>date('Y-m-d H:i:s')));

                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('E00627')/* Order deleted successfully. */);

            } else {

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00628')/* You're not allowed to performed this operation. */);

            }

        }

        function update_buysell($param, $user_id) {

            $db = $this->db;

            $order_id = $param['order_id'];
            $content = $param['content'];
            $currency = $param['currency'];
            $contactInfo = $param['contactInfo'];

            if($order_id=="") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00626')/* Order ID cannot be empty. */);
            }

            //Content
            if($content=="") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00620')/*Content Cannot be empty*/);
            } 

            if(strlen($content) > 200 ) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00621')/*Content length cannot more than 200 Words*/);
            } 

            if(count($currency)==0 || !is_array($currency)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00180')/* Currency is required. */);
            }
            
            if(count($contactInfo)==0 || !is_array($contactInfo)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('M01964')/* Contact info is required. */);
            }


            $db->where('id', $order_id);
            $db->where('business_id', $user_id);
            $db->where('disabled', 0);
            $result = $db->getOne('xun_buysell_order');

            if($result) {
                
                $db->where('id', $order_id);
                $db->update('xun_buysell_order', array('content'=>$content, 'updated_at'=>date('Y-m-d H:i:s')));


                $db->where('order_id', $order_id);
                $db->delete('xun_buysell_wallettype');

                foreach($currency as $coin) {

                    $insertData = array (
                       'order_id' => $order_id,
                       'wallet_type' => $coin,
                       'created_at' => date('Y-m-d H:i:s'));

                    $db->insert('xun_buysell_wallettype', $insertData); 
                }


                $db->where('order_id', $order_id);
                $db->delete('xun_buysell_contactinfo');

                foreach($contactInfo as $info) {

                    $insertData = array (
                       'order_id' => $order_id,
                       'type' => $info['type'],
                       'detail' => $info['detail'],
                       'created_at' => date('Y-m-d H:i:s'));

                    $db->insert('xun_buysell_contactinfo', $insertData);  
                }

                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('E00629')/* Order details updated. */);

            } else {

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00628')/* You're not allowed to performed this operation. */);

            }

        }

        function get_buysell_listing($param, $user_id) {

            $db = $this->db;
            $setting = $this->setting;

            $type = $param['type'];
            $currency = $param['currency'];
        
            $page_limit = $setting->systemSetting["memberBlogPageLimit"];
            $page_number = $param["page"] ? $param["page"] : 1;
            $page_size = $param["page_size"] ? $param["page_size"] : $page_limit;


            $start_limit = ($page_number - 1) * $page_size;
            $limit = array($start_limit, $page_size);

            if($type=="") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00238')/*'Type cannot be empty'*/);
            }

            if($type!="buy" && $type!="sell" && $type!="all" && $type!="myorder") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00334')/*'Invalid type'*/);
            }

            if($type=="buy" || $type=="sell") {
                $db->where('bs.disabled', 0);
                $db->where('bs.expire_at', date('Y-m-d H:i:s'), '>=');
                $db->where('bs.type', $type);

                if(count($currency)>0 || is_array($currency)) {
                    if(!in_array("all", $currency)) {
                        $db->where('t.wallet_type',$currency, 'IN');
                    }
                } else {
                    $db->where('t.wallet_type','none');
                }

            }

            if($type=="all") {
                $db->where('bs.disabled', 0);
                $db->where('bs.expire_at', date('Y-m-d H:i:s'), '>=');
            }

            if($type=="myorder") {
                $db->where('bs.business_id', $user_id);
            }
            
            $db->join('xun_buysell_wallettype t2', 't2.order_id=bs.id', 'INNER');
            $db->join('xun_buysell_wallettype t', 't.order_id=bs.id', 'INNER');
            $db->join('xun_marketplace_currencies c', 'c.currency_id=t2.wallet_type', 'INNER');
            $db->join('xun_business b', 'b.user_id=bs.business_id', 'INNER');

            $dbCopy = $db->copy();
            $totalRecord = $dbCopy->getValue('xun_buysell_order bs', 'COUNT(DISTINCT bs.id)');


            $db->groupBy('bs.id');
            $db->orderBy('bs.id', 'DESC');

            $result = $db->get('xun_buysell_order bs', $limit, 'DISTINCT bs.id, bs.business_id, b.name, bs.content, bs.type, bs.disabled, bs.expire_at, bs.updated_at, bs.created_at, GROUP_CONCAT(DISTINCT UPPER(c.symbol)) as wallet_type, GROUP_CONCAT(DISTINCT UPPER(c.display_symbol)) as display_wallet_type, b.created_at as join_at');

            $arr_listing = array();

            foreach($result as $detail) {

                if($detail['disabled']==1) {
                    $detail['status'] = 'Deleted';
                } else if($detail['disabled']==0 && $detail['expire_at']>date('Y-m-d H:i:s')) {
                    $detail['status'] = 'Active';
                } else {
                    $detail['status'] = 'Expired';
                }

                $db->where('order_id', $detail['id']);
                $contactDetail = $db->get('xun_buysell_contactinfo', null, 'type, detail');

                $detail['contact'] = $contactDetail;

                $detail['wallet_type'] = explode(",", $detail['wallet_type']);
                $detail['display_wallet_type'] = explode(",", $detail['display_wallet_type']);

                $detail['time_ago'] = $this->getTimeAgo($detail['created_at']);

                $arr_listing[] = $detail;
            }

            $total_page = ceil($totalRecord/$page_size);

            $wallet_type_listing = $this->get_buy_sell_wallet_type();
            $data['coin_data'] = $wallet_type_listing['result']['coin_data'];

            $data['listing'] = $arr_listing;
            $data['totalPage']    = $total_page;
            $data['pageNumber']   = $page_number;
            $data['totalRecord']  = $totalRecord;
            $data['numRecord']    = $page_size;
            $data['userid'] = $user_id;
            
            return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('E00630') /* Advertisement listing.*/, "data" => $data);
        }

        function get_buysell_detail($param) {

            $db = $this->db;

            $order_id = $param['order_id'];

            if($order_id=="") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00626')/* Order ID cannot be empty. */);
            }


            $db->where('bs.id', $order_id);
            $db->join('xun_buysell_wallettype t', 't.order_id=bs.id', 'INNER');
            // $db->join('xun_marketplace_currencies c', 'c.currency_id=t.wallet_type', 'INNER');
            $db->join('xun_business b', 'b.user_id=bs.business_id', 'INNER');

            $result = $db->getOne('xun_buysell_order bs', 'bs.id, bs.business_id, b.name, bs.content, bs.type, bs.disabled, bs.expire_at, bs.updated_at, bs.created_at, GROUP_CONCAT(t.wallet_type) as wallet_type, b.created_at as join_at');

            if($result) {

                if($result['disabled']==1) {
                    $result['status'] = 'Deleted';
                } else if($result['disabled']==0 && $result['expire_at']>date('Y-m-d H:i:s')) {
                    $result['status'] = 'Active';
                } else {
                    $result['status'] = 'Expired';
                }

                $db->where('order_id', $result['id']);
                $contactDetail = $db->get('xun_buysell_contactinfo', null, 'type, detail');

                $result['contact'] = $contactDetail;

                $result['wallet_type'] = explode(",", $result['wallet_type']);


                $wallet_type_listing = $this->get_buy_sell_wallet_type();
                $result['coin_data'] = $wallet_type_listing['result']['coin_data'];

                $result['time_ago'] = $this->getTimeAgo($result['created_at']);

                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('E00631')/* Order Details */, 'data' => $result);

            } else {

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00628')/* You're not allowed to performed this operation. */);

            }

        }

        function getTimeAgo($since_ts) {

            $start_date = new DateTime(date("Y-m-d H:i:s"));
            $since_start = $start_date->diff(new DateTime($since_ts));

            if($since_start->y > 0) {
                return $since_start->y." ".($since_start->y == 1 ? "year": "years");
            } else if($since_start->m > 0) {
                return $since_start->m." ".($since_start->m == 1 ? "month": "months");
            } else if($since_start->d > 0) {
                return $since_start->d." ".($since_start->d == 1 ? "day": "days");
            } else if($since_start->h > 0) {
                return $since_start->h." ".($since_start->h == 1 ? "hour": "hours");
            } else if($since_start->i > 0) {
                return $since_start->i." ".($since_start->i == 1 ? "min": "mins");
            } else {
                return $since_start->s." ".($since_start->s == 1 ? "sec": "secs");
            }

        }

    }
?>