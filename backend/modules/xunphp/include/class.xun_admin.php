<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the Database functionality for XUN.
 * Date  16/03/2018.
 **/

class XunAdmin
{

    public function __construct($db, $setting, $general, $post)
    {
        $this->db      = $db;
        $this->setting = $setting;
        $this->general = $general;
        $this->post    = $post;
    }

    public function adminGetUserListing($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $general = $this->general;
        $post    = $this->post;

        $admin_page_limit      = $setting->getAdminPageLimit();
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order                 = $params["order"];
        //$business_email        = $params["business_email"];
        $business_company_name = $params["business_company_name"];
        $country               = $params["country"];
        $business_id           = $params["business_id"];
        $business_mobile       = $params["business_mobile"];
        $domain                = $params["domain"];

        // if ($business_email) {
        //     $db->where("a.email", "%$business_email%", "LIKE");
        // }

        if ($business_company_name) {
            $db->where("a.name", "%$business_company_name%", "LIKE");
        }

        if ($country) {
            $db->where("a.country", $country);
        }

        if ($business_id) {
            $db->where("a.user_id", $business_id);
        }

        if ($business_mobile) {
            $db->where("a.phone_number", "%$business_mobile%", "LIKE");
        }
        
        if($domain == 'nuxpay'){
            $db->where('b.register_site', 'nuxpay');
        }

        if($domain == 'thenux'){
            $db->where('b.register_site', '');
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $db->orderBy("a.id", "DESC");
        $db->join('xun_user b', 'a.user_id = b.id', 'LEFT');
        $copyDb = $db->copy();
        $result = $db->get("xun_business a", $limit, 'a.*, b.register_site');

        $bid_arr = [];
        foreach($result as $value){
            $business_id = $value['user_id'];

            if(!in_array($business_id, $bid_arr)){
                array_push($bid_arr, $business_id);
            }
        }

        $db->where('user_id', $bid_arr, 'IN');
        $db->where('name', 'ipCountry');
        $user_setting = $db->map('user_id')->ArrayBuilder()->get('xun_user_setting');

        foreach ($result as $business_data) {
            $business_id = $business_data['user_id'];
            $business["business_id"]    = $business_data["user_id"];
            //$business["business_email"] = $business_data["email"];
            $business["domain"]         = $business_data['register_site'] ? $business_data['register_site'] : 'thenux';
            $business['created_at']     = $business_data['created_at'];
            $db->where("user_id", $business_data["user_id"]);
            // $db->where("email", $business["business_email"]);
            $business_account = $db->getOne("xun_business_account");

            $business["business_phone_number"] = $business_account["main_mobile"];
            $business["last_login"]            = $business_account["last_login"];
            $business["business_country"]      = $user_setting[$business_id]["value"] ? $user_setting[$business_id]["value"] : '';
            $business["business_name"]         = $business_data["name"];

            $return[] = $business;
        }

        
        if(!$return) return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
        //totalPage, pageNumber, totalRecord, numRecord
        $totalRecord = $copyDb->getValue("xun_business a", "count(a.id)");
        
        $returnData["data"] = $return;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        

        return array('status' => 'ok', 'code' => "0", 'statusMsg' => "", 'data' => $returnData);
    }

    public function adminGetUserDetails($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;

        $business_id = $params["business_id"];

        $db->where("a.user_id", $business_id);
        $db->join("xun_user b", "a.user_id=b.id", "LEFT");
        $business       = $db->getOne("xun_business a", "a.*, b.register_site, b.register_through");
        $business_email = $business["email"];

        $db->where("email", $business_email);
        $business_account = $db->getOne("xun_business_account");

        $db->where("business_id", $business_id);
        $db->orderBy("created_at", "DESC");
        $business_package_subscription = $db->getOne("xun_business_package_subscription");

        $returnData["referral_id"]   = $business_account["referral_code"] == 'undefined' ? "" : $business_account["referral_code"];
        $returnData["mobile"]        = $business_account["main_mobile"];
        $returnData["last_purchase"] = $business_package_subscription["created_at"];
        $returnData["last_login"]    = $business_account["last_login"];
        $returnData["freeze"]        = $business_account["status"] == '1' ? "true" : "false";
        $returnData["description"]   = $business_account["description"] == 'undefined' ? "" : $business_account["description"];
        $returnData["created_date"]  = $business["created_at"];
        $returnData["business_name"] = $business["name"];
        $returnData["business_id"]   = $business["user_id"];
        //$returnData["business_credit"] = get balance function
        $returnData["business_email"]   = $business["email"];
        $returnData["business_country"] = $business["country"];
        $returnData["domain"] = $business["register_site"] ? $business["register_site"] : "TheNux";
        $returnData["register_through"] = $business["register_through"] ? $business["register_through"] : "-";

        $check_utm_record = $db->rawQuery("SELECT * FROM `utm_record` WHERE business_id = '$business_id' ");

        if (!empty($check_utm_record)) {

            $result_utm = $db->rawQueryOne("SELECT `device_id`,`utm_campaign`,`utm_source`,`utm_term`,`utm_medium` FROM `utm_record` WHERE business_id = '$business_id' AND register_status = 1 ");

            $returnData["device_id"]    = $result_utm["device_id"];
            $returnData["utm_campaign"] = $result_utm["utm_campaign"];
            $returnData["utm_source"]   = $result_utm["utm_source"];
            $returnData["utm_term"]     = $result_utm["utm_term"];
            $returnData["utm_medium"]   = $result_utm["utm_medium"];

        }

        return array('status' => 'ok', 'code' => "0", 'statusMsg' => "", 'data' => $returnData);
    }

    public function adminEditUser($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;

        $business_id = $params["business_id"];

        $db->where("user_id", $business_id);
        $business_result = $db->getOne("xun_business");

        if (!$business_result) {
            return array("status" => "error", "code" => "0", "statusMsg" => "Invalid business id");
        }

        $business_email = $business_result["email"];

        $mobile                  = $params["mobile"];
        $business_country        = $params["business_country"];
        $referral_id             = $params["referral_id"];
        $description             = $params["description"];
        $freeze                  = $params["freeze"];
        $business_contact_us_url = $params["business_contact_us_url"];
        $business_name           = $params["business_name"];

        $update_business_data["phone_number"] = $mobile;
        $update_business_data["country"]      = $business_country;

        $update_business_account_data["referral_code"] = $referral_id;
        $update_business_account_data["description"]   = $description;

        if ($freeze) {
            $update_business_account_data["status"] = $freeze == 'true' ? "1" : "0";
        }

        $update_business_data["contact_us_url"] = $business_contact_us_url;
        $update_business_data["name"]           = $business_name;

        if ($update_business_data) {
            $db->where("user_id", $business_id);
            $db->update("xun_business", $update_business_data);

            $this->send_business_update_profile_message($business_id, "details");
        }

        if ($update_business_account_data) {
            $db->where("email", $business_email);
            $db->update("xun_business_account", $update_business_account_data);
        }

        return array('status' => 'ok', 'code' => "0", 'statusMsg' => "Business details updated.", 'data' => $returnData);
    }

    public function adminGetUserTopupHistory($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;

        $admin_page_limit = $setting->getAdminPageLimit();
        $business_id      = $params["business_id"];
        $from_datetime    = $params["from_datetime"];
        $to_datetime      = $params["to_datetime"];
        $page_number      = $params["page"];
        $page_size        = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order            = $params["order"];

        if ($business_id) {
            $db->where("business_id", $business_id);
        }

        if ($from_datetime) {
            $from_datetime  = date("Y-m-d H:i:s", $from_datetime);
            $query[] = "a.created_at >= '$from_datetime'";
        }
        
        if ($to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $query[] = "a.created_at <= '$to_datetime'";
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        $copyDb = $db->copy();
        $db->orderBy("id", $order);
        $result = $db->get("xun_payment_transaction", $limit);
        
        if($result){
            $package_result = $db->get("xun_business_package");
            
            foreach($package_result as $package_data){
                $packages[$package_data["code"]] = $package_data;    
            }
            
        }

        foreach ($result as $payment_data) {
            
            $package_data = $packages[$payment_data["package_code"]];
            
            $payment["billing_date"]          = $payment_data["created_at"];
            $payment["billing_invoice"]       = $payment_data["invoice_no"];
            $payment["package_description"]   = $package_data["description"];
            $payment["package_unit_price"]    = $package_data["price"];
            $payment["billing_status"]        = $payment_data["payment_status"] == "1"? "Paid":"Failed";
            $payment["package_type"]          = $package_data["type"];
            $payment["billing_currency"]      = $payment_data["payment_currency"];
            $payment["billing_total"]         = $payment_data["payment_amount"];
            $payment["quantity"]              = $payment_data["quantity"] ? $payment_data["quantity"]:"1";
            
            $return[] = $payment;
        }

        
        if(!$return) return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
        
        $totalRecord = $copyDb->getValue("xun_payment_transaction", "count(id)");
        
        $returnData["data"] = $return;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;


        return array('status' => 'ok', 'code' => "0", 'statusMsg' => "", 'data' => $returnData);
    }

    public function adminGetUserUsageHistory($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;

        $admin_page_limit = $setting->getAdminPageLimit();
        $business_id      = $params["business_id"];
        $from_datetime    = $params["from_datetime"];
        $to_datetime      = $params["to_datetime"];
        $page_number      = $params["page"];
        $page_size        = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order            = $params["order"];

        if ($business_id) {
            $db->where("business_id", $business_id);
        }

        if ($from_datetime) {
            $from_datetime  = date("Y-m-d H:i:s", $from_datetime);
            $db->where("sent_datetime", $from_datetime, '>=');
        }
        
        if ($to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $db->where("sent_datetime", $to_datetime, '<=');
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        $copyDb = $db->copy();
        $db->orderBy("id", $order);
        $result = $db->get("xun_publish_message_log", $limit);

        foreach ($result as $usage_data) {
            $usage["datetime"]    = $usage_data["sent_datetime"];
            $usage["id"]          = $usage_data["id"];
            $usage["total_usage"] = $usage_data["sent_mobile_length"];

            $return[] = $usage;

            $total_usage += $usage_data["sent_mobile_length"];
        }

        
        if(!$return) return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
        
        $totalRecord = $copyDb->getValue("xun_publish_message_log", "count(id)");
        
        $returnData["data"] = $return;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["totalUsage"] = $total_usage;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;


        return array('status' => 'ok', 'code' => "0", 'statusMsg' => "", 'data' => $returnData);
    }

    public function adminGetUserFollow($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;

        $admin_page_limit = $setting->getAdminPageLimit();
        $page_number      = $params["page"];
        $page_size        = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order            = $params["order"];
        $business_id      = $params["business_id"];

        if ($business_id) {
            $db->where("user_id", $business_id);
            $business_data = $db->getOne("xun_business");

            $db->where("business_id", $business_id);
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        $copyDb = $db->copy();
        $db->orderBy("id", $order);
        $result = $db->get("xun_business_follow", $limit);

        foreach ($result as $business_follow_data) {
            $business_follow["business_phone_number"] = $business_data["phone_number"];
            $business_follow["user_username"]         = $business_follow_data["username"];

            $return[] = $business_follow;
        }

        
        if(!$return) return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
        
        $totalRecord = $copyDb->getValue("xun_business_follow", "count(id)");
        
        $returnData["data"] = $return;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        

        return array('status' => 'ok', 'code' => $code, 'statusMsg' => $message, 'data' => $returnData);
    }

    public function adminGetTopupHistory($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;

        $admin_page_limit      = $setting->getAdminPageLimit();

        $business_name         = $params["business_name"];
        $business_email        = $params["business_email"];
        $business_mobile       = $params["mobile"];
        $from_datetime         = $params["from_datetime"];
        $to_datetime           = $params["to_datetime"];
        $payment_method        = $params["payment_method"];
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order                 = $params["order"];

        if ($business_name) {
            $query[] = "b.name LIKE '%$business_name%'";
        }

        if ($business_email) {
            $query[] = "b.email LIKE '%$business_email%'";
        }

        if ($business_mobile) {
            $query[] = "b.phone_number LIKE '%$business_mobile%'";
        }
        
        if ($from_datetime) {
            $from_datetime  = date("Y-m-d H:i:s", $from_datetime);
            $query[] = "a.created_at >= '$from_datetime'";
        }
        
        if ($to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $query[] = "a.created_at <= '$to_datetime'";
        }
        
//        if($payment_method) {
//            $query[] = "b.phone_number LIKE '%$business_mobile%'";
//        }

        $business_query = "";

        if ($query) {
            $business_query = implode(" AND ", $query);
        }

        if ($page_number < 1) {
            $page_number = 1;
        }
        
        $start_limit = ($page_number - 1) * $page_size;

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        $count_result = $db->rawQuery("SELECT COUNT(a.id) AS total FROM xun_payment_transaction a, xun_business b WHERE a.business_id = b.user_id " . ($business_query == "" ? $business_query : "AND $business_query") . "");

        $result = $db->rawQuery("SELECT a.*, b.email, b.name, b.phone_number FROM xun_payment_transaction a, xun_business b WHERE a.business_id = b.user_id " . ($business_query == "" ? $business_query : "AND $business_query") . " ORDER BY a.id $order LIMIT $start_limit, $page_size");
        if($result){
            $package_result = $db->get("xun_business_package");
            
            foreach($package_result as $package_data){
                $packages[$package_data["code"]] = $package_data;    
            }
            
        }

        foreach ($result as $payment_data) {
            
            $package_data = $packages[$payment_data["package_code"]];
            
            $payment["id"]                    = $payment_data["id"];
            $payment["business_id"]           = $payment_data["business_id"];
            $payment["billing_date"]          = $payment_data["created_at"];
            $payment["business_name"]         = $payment_data["name"];
            $payment["business_email"]        = $payment_data["email"];
            $payment["mobile"]                = $payment_data["phone_number"];
            $payment["billing_invoice"]       = $payment_data["invoice_no"];
            //$payment["package_description"]   = $package_data["description"];
            $payment["package_unit_price"]    = $package_data["price"];
            $payment["billing_status"]        = $payment_data["payment_status"] == "1"? "Paid":"Failed";
            $payment["package_type"]          = $package_data["type"];
            $payment["billing_currency"]      = $payment_data["payment_currency"];
            $payment["billing_total"]         = $payment_data["payment_amount"];
            $payment["quantity"]              = $payment_data["quantity"] ? $payment_data["quantity"]:"1";
            $payment["payment_method"]        = "Stripe";
            
            $return[] = $payment;
        }
        
        if(!$return) return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
        
        $totalRecord = $count_result[0]["total"];
        
        $returnData["data"] = $return;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["totalUsage"] = $total_usage;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        
        return array('status' => 'ok', 'code' => "0", 'statusMsg' => "", 'data' => $returnData);
    }

    public function adminGetUsageHistory($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;

        $admin_page_limit      = $setting->getAdminPageLimit();

        $business_id           = $params["business_id"];
        $business_name         = $params["business_name"];
        $business_email        = $params["business_email"];
        $business_mobile       = $params["mobile"];
        $from_datetime         = $params["from_datetime"];
        $to_datetime           = $params["to_datetime"];
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order                 = $params["order"];

        if ($business_name) {
            $query[] = "b.name LIKE '%$business_name%'";
        }

        if ($business_email) {
            $query[] = "b.email LIKE '%$business_email%'";
        }

        if ($business_id) {
            $query[] = "a.business_id = $business_id";
        }

        if ($business_mobile) {
            $query[] = "b.phone_number LIKE '%$business_mobile%'";
        }
        
        if ($from_datetime) {
            $from_datetime  = date("Y-m-d H:i:s", $from_datetime);
            $query[] = "a.sent_datetime >= '$from_datetime'";
        }
        
        if ($to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $query[] = "a.sent_datetime <= '$to_datetime'";
        }

        $business_query = "";

        if ($query) {
            $business_query = implode(" AND ", $query);
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        $count_result = $db->rawQuery("SELECT COUNT(a.id) AS total FROM xun_publish_message_log a, xun_business b WHERE a.business_id = b.id " . ($business_query == "" ? $business_query : "AND $business_query") . "");

        $result = $db->rawQuery("SELECT a.*, b.email, b.name, b.phone_number FROM xun_publish_message_log a, xun_business b WHERE a.business_id = b.user_id " . ($business_query == "" ? $business_query : "AND $business_query") . " ORDER BY a.id $order LIMIT $start_limit, $page_size");
        foreach ($result as $usage_data) {

            $usage["id"]                    = $usage_data["id"];
            $usage["business_id"]           = $usage_data["business_id"];
            $usage["business_email"]        = $usage_data["email"];
            $usage["business_name"]         = $usage_data["name"];
            $usage["usage"]                 = $usage_data["sent_mobile_length"];
            $usage["mobile"]                = $usage_data["phone_number"];
            
            $total_usage += $usage_data["sent_mobile_length"];

            $return[] = $usage;
        }
        
        if(!$return) return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
        
        $totalRecord = $count_result[0]["total"];
        
        $returnData["data"] = $return;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["totalUsage"] = $total_usage;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        
        return array('status' => 'ok', 'code' => "0", 'statusMsg' => "", 'data' => $returnData);
    }

    public function adminGetUserFreezedListing($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;

        $admin_page_limit      = $setting->getAdminPageLimit();
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order                 = $params["order"];
        $business_company_name = $params["business_company_name"];
        $country               = $params["country"];
        $business_id           = $params["business_id"];
        $business_mobile       = $params["business_mobile"];
        $business_email        = $params["business_email"];

        if ($business_company_name) {
            $query[] = "b.name LIKE '%$business_company_name%'";
        }

        if ($country) {
            $query[] = "b.country LIKE '%$country%'";
        }

        if ($business_id) {
            $query[] = "b.id = $business_id";
        }

        if ($business_mobile) {
            $query[] = "b.phone_number LIKE '%$business_mobile%'";
        }

        if ($business_email) {
            $query[] = "b.email LIKE '%$business_email%'";
        }

        $business_query = "";

        if ($query) {
            $business_query = implode(" AND ", $query);
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        $count_result = $db->rawQuery("SELECT COUNT(a.id) as total FROM xun_business_account a, xun_business b WHERE a.email = b.email AND a.status = '0' " . ($business_query == "" ? $business_query : "AND $business_query") . "");

        $result = $db->rawQuery("SELECT a.email, a.status, b.* FROM xun_business_account a, xun_business b WHERE a.email = b.email AND a.status = '0' " . ($business_query == "" ? $business_query : "AND $business_query") . " ORDER BY b.user_id $order LIMIT $start_limit, $page_size");
        foreach ($result as $business_data) {
            $business["business_id"] = $business_data["user_id"];
            $business["business_email"] = $business_data["email"];
            $business["business_phone_number"] = $business_data["phone_number"];
            $business["business_country"] = $business_data["country"];
            $business["business_name"] = $business_data["name"];

            $return[] = $business;
        }

        if(!$return) return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
        
        $totalRecord = $count_result[0]["total"];
        
        $returnData["data"] = $return;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        

        return array('status' => 'ok', 'code' => "0", 'statusMsg' => "", 'data' => $returnData);
    }

    public function adminGetInvoiceDetails($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;

        $billing_invoice    = $params["billing_invoice"];
        
        $db->where("invoice_no", $billing_invoice);
        $payment_result = $db->getOne("xun_payment_transaction");
        
        $db->Where("id", $payment_result["reference_id"]);
        $db->orwhere("old_id", $payment_result["reference_id"]);
        $reference_result = $db->getOne($payment_result["type"]);
        
        $db->Where("id", $payment_result["reference_id"]);
        $db->orWhere("old_id", $reference_result["billing_id"]);
        $billing_result = $db->getOne("xun_business_billing_info");        
        
        $db->where("code", $payment_result["package_code"]);
        $package_result = $db->getOne("xun_business_package");

        if($payment_result["type"] == "xun_business_package_top_up"){
            $db->where("old_id", $payment_result["reference_id"]);
            $top_up_result = $db->getOne("xun_business_package_top_up");

            if($top_up_result){
                $quantity = $top_up_result["quantity"];
                $total_amount = $quantity * $payment_result["payment_amount"];
            }
        }
        else{
            $total_amount = $payment_result["payment_amount"];
        }

        $payment_amount = sprintf('%0.2f', $payment_result["payment_amount"]);
        $total_amount = $total_amount ? sprintf('%0.2f', $total_amount) : $payment_amount;

        $returnData["state"]                = $billing_result["state"];
        $returnData["postal"]               = $billing_result["postal"];
        $returnData["last_name"]            = $billing_result["last_name"];
        $returnData["first_name"]           = $billing_result["first_name"];
        $returnData["country"]              = $billing_result["country"];
        $returnData["city"]                 = $billing_result["city"];
        $returnData["billing_subscription"] = $package_result["description"];
        $returnData["billing_invoice"]      = $payment_result["invoice_no"];
        $returnData["billing_date"]         = $billing_result["created_at"];
        $returnData["billing_type"]         = $payment_result["type"] == 'xun_business_package_subscription' ? "Subscription" : "Top Up";
        $returnData["billing_currency"]     = $payment_result["payment_currency"];
        $returnData["billing_amount"]       = $payment_amount;
        $returnData["address"]              = $billing_result["address"];
        $returnData["total_amount"]         = $total_amount;
        $returnData["quantity"]             = $quantity ? $quantity :"1";

        return array('status' => 'ok', 'code' => $code, 'statusMsg' => $message, 'data' => $returnData);
    }

    public function adminGetReferralList($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;
//            $language       = $this->general->getCurrentLanguage();
        //            $translations   = $this->general->getTranslations();

        //https://<mount_point>/xun/admin/business/referral/list
        $command = "/xun/admin/business/referral/list";

        $result = $post->curl_get($command, $params);

        $data = json_decode($result, true);

        $code    = (string) $data["code"];
        $message = (string) $data["message_d"];

        if ($code == 0) {
            return array('status' => "error", "code" => $code, "statusMsg" => $message);
        }

        $returnData = $data["result"];

        return array('status' => 'ok', 'code' => $code, 'statusMsg' => $message, 'data' => $returnData);
    }

    public function getXunCountryList($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;
        
        global $message;
//            $language       = $this->general->getCurrentLanguage();
        //            $translations   = $this->general->getTranslations();

        $command = "/xun/country/phone_code/list";

        $result = $post->curl_post($command, $params);

        $data = json_decode($result, true);

        $code    = (string) $data["code"];
        $message = (string) $data["message_d"];

        if ($code == 0) {
            return array('status' => "error", "code" => $code, "statusMsg" => $message);
        }

        $returnData = $data["result"];

        return array('status' => 'ok', 'code' => $code, 'statusMsg' => $message, 'data' => $returnData);
    }

    public function contactUs($msgpackData, $source)
    {
        global $ticket, $message, $xunPaymentGateway;

        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;

        $params        = $msgpackData['params'];
        $name          = $params["name"];
        $email_address = $params["email_address"];
        $country       = $params["country"];
        $mobile_number = $params["mobile_number"];
        $company_name  = $params["company_name"];
        $subject       = $params["subject"];
        $content       = $params["message"];
        $website       = $params["website"];
        $symbol        = $params["symbol"];
        $keyword       = $params["keyword"];
        $ip            = $msgpackData["ip"];
        $user_agent    = $msgpackData["user_agent"];
        //$source        = $params["source"] == 'nuxpay' ?  $params['source'] : '';

        $newContent = "Message: " . $content . "\n\nWebsite: " . $website . "\n\nSymbol: " . $symbol;
        
        $mobile_number = $mobile_number ? $mobile_number : '';
        $subject = $subject ? $subject : '';

        $xunIP = new XunIP($db);
        $ip_country = $xunIP->get_ip_country($ip);

        $ticketData = array (
            'clientID' => $clientID,
            'clientName' => $name,
            'clientEmail' => $email_address,
            'clientPhone' => $mobile_number,
            'status' => "open",
            'priority' => 1,
            'type' => "",
            'subject' => $subject,
            'department' => "",
            'reminderDate' => "",
            'assigneeID' => "",
            'assigneeName' => "",
            'creatorID' => $clientID,
            'internal' => 0,
            'content' => nl2br($newContent),
            'autoEmail' => 1,
            'creatorType' => 'Member',
            'source' => $source,
            'keyword' => $keyword,
        );

        $return_data = $ticket->addTicket($ticketData);
        if($return_data['status'] == 'error'){
            if($source == 'nuxpay'){
                $return_status =  'FAILED';
                $return_message = $return_data['statusMsg'];
                $this->send_nuxpay_contact_us_notification($ip, $ip_country, $keyword, $name, $email_address, $country, $mobile_number, $content, $return_status, $return_message);
            }  

            return $return_data;

        }
        
        $find = array (
            "%%name%%",
            "%%email%%",
            "%%mobileNumber%%",
            "%%subject%%",
            "%%content%%", 
            "%%website%%", 
            "%%symbol%%", 
            "%%date%%", 
        );
        $replace = array (
            $name,
            $email_address,
            $mobile_number,
            $subject,
            $content,
            $website,
            $symbol,
            date("Y-m-d H:i:s"),
        );

        $messageCode = "10035";
        $message->createMessageOut($messageCode, "", "", $find, $replace);


//        include_once "class.smtp.php";
//        include_once "class.phpmailer.php";
//
//        $mail = new PHPMailer();
//
//        $mail->IsSMTP(); // set mailer to use SMTP
//        //            $mail->SMTPDebug = 3;
//        $mail->Host     = "smtp.gmail.com"; // specify main and backup server
//        $mail->SMTPAuth = true; // turn on SMTP authentication
//        $mail->Username = "support@thenux.com"; // SMTP username
//        $mail->Password = ">=heq5PV"; // SMTP password
//
////        $mail->From     = $email_address;
////        $mail->FromName = $name;
//        $mail->setFrom($email_address, $name);
//        $mail->AddAddress("support@thenux.com", "TheNux Support");
//        $mail->AddReplyTo($email_address, $name);
//
//        $mail->WordWrap = 50; // set word wrap to 50 characters
//        // $mail->AddAttachment("/var/tmp/file.tar.gz");         // add attachments
//        // $mail->AddAttachment("/tmp/image.jpg", "new.jpg");    // optional name
//        $mail->IsHTML(true); // set email format to HTML
//
//        $mail->Subject = $subject;
//
//        //$lang["M01424"][$language]."<br><br>".$lang["M01425"][$language].": ".$password."<br><br>".$lang["M01426"][$language]."<br><a href='".$sys['memberSite']."/login.php?resetPassword=1'>".$sys['memberSite']."</a>";
//
//        $mail->Body = "name : $name <br><br>
//                       email : $email_address <br><br>
//                       country : $country <br><br>
//                       mobile number : $mobile_number <br><br>
//                       company_name : $company_name <br><br>
//                       subject : $subject <br><br>
//                       message : $message";
//
//        if (!$mail->Send()) {
//            return array('status' => 'error', 'code' => "0", 'statusMsg' => "failed to send email", 'data' => "");
//        }

        if($source == 'nuxpay'){

            $status = "SUCCESS";
            $this->send_nuxpay_contact_us_notification($ip, $ip_country, $keyword, $name, $email_address, $country, $mobile_number, $content, $status, '');
          
        }

        return array('status' => 'ok', 'code' => "1", 'statusMsg' => "Thank you for contacting us, our support will reply to you within 1 working day", 'data' => "");
    }
    
    public function enquiry($params, $msgpackData) {
        $db = $this->db;
        $general = $this->general;
        $message = $this->message;

        // Language Translations.
        $language     = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $clientName = $params['clientName'];
        $clientEmail = $params['clientEmail'];
        $clientPhone = $params['clientPhone'];
        $subject = $params['subject'];
        $content = $params['content'];

        $hostName     = $msgpackData['hostName'];
        $platform      = $msgpackData['type'];
        $ip            = $msgpackData['ip'];
        $sourceVersion = $msgpackData['sourceVersion'];
        $sourceVersion = $db->escape($sourceVersion);
        $userAgent     = $msgpackData['userAgent'];
        $countryCode = strtoupper($general->ipLookup($ip)); // get country code

        $db->where('iso_code2', $countryCode);
        $result = $db->get("country", 1, "name, currency_code");
        foreach ($result as $value) {
            $country = $value["name"];
        }

        if(empty($clientName) || empty($clientEmail) || empty($clientPhone) || empty($subject) || empty($content))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00288'][$language]/* Required fields cannot be left empty. */, 'data' => "");


        $db->where('email', $clientEmail);
        $db->where('deleted', 0);
        $getAccountManagerID = "(SELECT reference FROM client_setting WHERE client_id=client.id AND client_setting.name='accountManager') AS account_manager_name";
        $result = $db->getOne('client', 'id,'.$getAccountManagerID);

        $clientID = 0;
        $accountManager = "-";
        if(!empty($result)) {
            $clientID = $result['id'];
            $accountManager = $result['account_manager_name'];
        }

        // New Ticket
        $ticket = array (
            'clientID' => $clientID,
            'clientName' => $clientName,
            'clientEmail' => $clientEmail,
            'clientPhone' => $clientPhone,
            'status' => "open",
            'priority' => 1,
            'type' => "",
            'subject' => $subject,
            'department' => "",
            'reminderDate' => "",
            'assigneeID' => "",
            'assigneeName' => "",
            'creatorID' => $clientID,
            'internal' => 0,
            'content' => nl2br($content),
            'autoEmail' => 1,
        );
        $this->addTicket($ticket);

        $find = array (
            "%%name%%",
            "%%email%%",
            "%%mobileNumber%%",
            "%%ip%%",
            "%%country%%",
            "%%domain%%",
            "%%subject%%",
            "%%content%%", 
            "%%date%%", 
        );
        $replace = array (
            $clientName,
            $clientEmail,
            $clientPhone,
            $ip,
            $country,
            $hostName,
            $subject,
            $content,
            date("Y-m-d H:i:s"),
        );

        $messageCode = "10035";
        $message->createMessageOut($messageCode, "", "", $find, $replace);

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
    }

    public function utm_record($data)
    {

        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;

        $business_id     = $data["business_id"] ? $data["business_id"] : 0;
        $business_name   = $data["business_name"] ? $data["business_name"] : 0;
        $utm_source      = $data["utm_source"] ? $data["utm_source"] : '-';
        $utm_medium      = $data["utm_medium"] ? $data["utm_medium"] : '-';
        $utm_campaign    = $data["utm_campaign"] ? $data["utm_campaign"] : '-';
        $utm_term        = $data["utm_term"] ? $data["utm_term"] : '-';
        $device_id       = $data["device_id"];
        $ip              = $data["ip"] ? $data["ip"] : 0;
        $userAgent       = $data["user_agent"] ? $data["user_agent"] : 0;
        $type            = $data["type"] ? $data["type"] : 0;
        $country         = $data["country"] ? $data["country"] : 0;
        $url             = $data["url"];
        $register_status = $data["register_status"];
        $tracking_site   = $data["tracking_site"] ? $data["tracking_site"] : "thenux";
        $today           = date("Y-m-d H:i:s");

        $flag = true;

        if (!$device_id) {

            while ($flag) {

                $randNum = rand(1, 100000000);
                $value   = $randNum;

                $db->where('device_id', $value);
                $result = $db->get('utm_record');

                if (!$result) {

                    $flag      = false;
                    $device_id = $value;
                }
            }
        }

        $fields = array("business_id", "business_name", "utm_source", "utm_medium", "utm_campaign", "utm_term", "device_id"
            , "ip", "user_agent", "type", "country", "created_at", "url", "register_status", "tracking_site");

        $values    = array($business_id, $business_name, $utm_source, $utm_medium, $utm_campaign, $utm_term, $device_id, $ip, $userAgent, $type, $country, $today, $url, $register_status, $tracking_site);
        $arrayData = array_combine($fields, $values);

        $debitID = $db->insert("utm_record", $arrayData);

        return array('status' => ok, 'code' => 1, 'statusMsg' => Success, 'device_id' => $device_id);

    }

    public function utm_list($data)
    {

        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;
        $general = $this->general;

        $pageNumber = $data['page'] ? $data['page'] : 1;
        //Get the limit.
        $limit = $general->getXunLimit($pageNumber);

        $searchCountry   = $data["country"];
        $searchDate_to   = $data["date_to"];
        $searchDate_form = $data["date_from"];
        $searchDevice_id = $data["device_id"];
        $searchIp        = $data["ip"];
        $searchUrl       = $data["url"];
        $searchTrackingSite = $data["tracking_site"];
        $export          = trim($data["seeAll"]);

        // $date_form =  date('Y-m-d H:i:s', $searchDate_form);
        // $date_to = date('Y-m-d H:i:s', $searchDate_to);

        foreach ($data as $key => $x_value) {

            $columnName = 'created_at';
            switch ($key) {
                case 'country':
                    if ($searchCountry != '') {
                        $db->where('country', $searchCountry);
                    }
                    break;
                case 'date_from':
                    if ($searchDate_form != '') {
                        // $db->where('created_at', $searchDate_form ,'>=');
                        $db->where($columnName, date('Y-m-d H:i:s', $searchDate_form), '>=');
                    }

                    break;
                case 'date_to':
                    if ($searchDate_to != '') {
                        $db->where($columnName, date('Y-m-d H:i:s', $searchDate_to), '<=');
                    }
                    break;
                case 'device_id':
                    if ($searchDevice_id != '') {
                        $db->where('device_id', $searchDevice_id . "%", 'LIKE');
                    }
                    break;

                case 'ip':
                    if ($searchIp != '') {
                        $db->where('ip', $searchIp . "%", 'LIKE');
                    }
                    break;

                case 'url':
                    if ($searchUrl != '') {
                        $db->where('url', "%" . $searchUrl . "%", 'LIKE');
                    }
                    break;

                case 'tracking_site':
                    if ($searchTrackingSite != '') {
                        $db->where('tracking_site', "%" . $searchTrackingSite . "%", 'LIKE');
                    }
                    break;

            }
        }

        $db->orderBy("created_at", "DESC");
        $copyDb = $db->copy();

        if ($export == 1) {
            $result = $db->get("utm_record");
        } else {
            $result = $db->get("utm_record", $limit);

        }
        if (!empty($result)) {
            foreach ($result as $value) {

                $utm_record['created_at']    = $value['created_at'];
                $utm_record['business_id']   = $value['business_id'];
                $utm_record['business_name'] = $value['business_name'];
                $utm_record['device_id']     = $value['device_id'];
                $utm_record['utm_source']    = $value['utm_source'];
                $utm_record['utm_medium']    = $value['utm_medium'];
                $utm_record['utm_term']      = $value['utm_term'];
                $utm_record['utm_campaign']  = $value['utm_campaign'];
                $utm_record['ip']            = $value['ip'];
                $utm_record['url']           = $value['url'];
                $utm_record['user_Agent']    = $value['user_Agent'];
                $utm_record['type']          = $value['type'];
                $utm_record['country']       = $value['country'];
                $utm_record["tracking_site"] = $value["tracking_site"];
                $countriesList[]             = $utm_record;
            }

            $totalRecords = $copyDb->getValue("utm_record", "count(id)");

            $data['countriesList'] = $countriesList;
            $data['totalPage']     = ceil($totalRecords / $limit[1]);
            $data['pageNumber']    = $pageNumber;
            $data['totalRecord']   = $totalRecords;
            $data['numRecord']     = $limit[1];

            return array('status' => "ok", 'code' => 1, 'statusMsg' => '', 'data' => $data);
        } else {
            return array('status' => "ok", 'code' => 1, 'statusMsg' => "No Results Found", 'data' => "");
        }

    }

//UTM _traking_ add
    public function utm_tracking($data)
    {
        $general = $this->general;
        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;
        global $action_tracking_number;
        global $config, $xunXmpp;
        
        $business_name = $data["business_name"] ? $data["business_name"] : '-';
        $mobile_number = $data["mobile_number"] ? $data["mobile_number"] : '-';
        $email_address = $data["email_address"] ? $data["email_address"] : '-';
        $device_id     = $data["device_id"] ? $data["device_id"] : 0;
        $utm_campaign  = $data["utm_campaign"] ? $data["utm_campaign"] : '-';
        $action_type   = $data["action_type"] ? $data["action_type"] : 0;

        $utm_source = $data["utm_source"] ? $data["utm_source"] : '-';
        $utm_medium = $data["utm_medium"] ? $data["utm_medium"] : '-';
        $utm_term   = $data["utm_term"] ? $data["utm_term"] : '-';
        $device     = $data["device"] ? $data["device"] : '-';
        $tracking_site     = $data["tracking_site"] ? $data["tracking_site"] : 'thenux';
        $ip            = $data["ip"] ? $data["ip"] : '-';
        $country       = $data["country"] ? $data["country"] : '-';
        $user_agent    = $data["user_agent"] ? $data["user_agent"] : '-';
        $url           = $data["url"] ? $data["url"] : '-';
        $content       = $data["content"] ? $data["content"] : '-';

        if($ip != '-' && $ip !=''){
            $xunIP = new XunIP($db);
            $ip_country = $xunIP->get_ip_country($ip);
        }
        else{
            $ip_country = '-';
        }

        $staus_msg = $data["status_msg"];

        $today = date("Y-m-d H:i:s");

        $fields    = array("business_name", "mobile_number", "email_address", "device_id", "utm_campaign", "action_type", "utm_Source", "utm_Medium", "utmTerm", "device", "ip" , "country" , "url", "tracking_site", "content", "created_at");
        $values    = array($business_name, $mobile_number, $email_address, $device_id, $utm_campaign, $action_type, $utm_source, $utm_medium, $utm_term, $device, $ip, $ip_country, $url, $tracking_site, $content, $today);
        $arrayData = array_combine($fields, $values);
 
        $debitID = $db->insert("utm_tracking", $arrayData);

        if ($staus_msg != "0") {

            $arr = array();

            $msg = "IP: ".$ip."\n";
            $msg .= "Country: " .$country."\n";
            $msg .= "Device: " .$device."\n";
            $msg .= "Device Id: ".$device_id."\n";
            $msg .= "OS: " .$user_agent."\n";
            $msg .= "URL: " .$url."\n";
            if($mobile_number != "-"){
                $msg .= "Mobile Number: " . $mobile_number . "\n";
            }
            $msg .= "Content: ".$content."\n";
            $msg .= "Time: ".date("Y-m-d H:i:s");

            $params["tag"] = "Marketing Tracking";
            $params["message"] = $msg;
            $params["mobile_list"] = array();
            //$general->send_thenux_notification($params, "thenux_pay");
            

            $msg = "IP : ".$ip."\n";
            $msg .= "Country : " .$country."\n\n";
            $msg .= "URL : " .$url."\n\n";
            $msg .= "Device Id : $device_id\r\n";
            $msg .= "Device : $device\r\n\r\n";
            $msg .= "Utm Source  : $utm_source\r\n";
            $msg .= "Utm Medium : $utm_medium\r\n";
            $msg .= "Utm Campaign : $utm_campaign\r\n";
            $msg .= "Utm Term : $utm_term\r\n\r\n";
            $msg .= "Action Type  : $action_type\r\n";
            $msg .= "Business Name : $business_name\r\n";
            $msg .= "Mobile Number : $mobile_number\r\n";
            $msg .= "Email : $email_address\r\n\r\n";
            $msg .= "Notice on : $today";
            $params = array(
                "api_key"     => $config["thenux_marketing_API"],
                "mobile_list" => $action_tracking_number,
                "tag"         => $device_id,
                "message"     => $msg,
                "business_id" => $config["thenux_marketing_bID"],
            );

            $params["mobile_list"] = $action_tracking_number;
            
            if ($staus_msg == "affiliate"){
                $msg = "IP : ".$ip."\n";
                $msg .= "Country : " .$country."\n\n";
                $msg .= "URL : " .$url."\n\n";
                $msg .= "Device Id : $device_id\r\n";
                $msg .= "Device : $device\r\n\r\n";
                $msg .= "Utm Campaign : $utm_campaign\r\n";
                $msg .= "Action Type  : $action_type\r\n";
                $msg .= "Business Name : $business_name\r\n";
                $msg .= "Mobile Number : $mobile_number\r\n";
                $msg .= "Notice on : $today";
                $params = array(
                    "api_key"     => $config["thenux_marketing_API"],
                    "mobile_list" => $action_tracking_number,
                    "tag"         => $device_id,
                    "message"     => $msg,
                    "business_id" => $config["thenux_marketing_bID"],
                );
                $thenux_result = $general->send_thenux_notification($params, "thenux_NuxPay_Landing_Page_Track");
            }

          
        }
        return array('code' => 1, 'status' => Success, 'statusMsg' => SUCCESS);

    }

// UTM _traking _list
    public function utm_tracking_list($data)
    {

        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;
        $general = $this->general;

        $pageNumber = $data['page'] ? $data['page'] : 1;
        //Get the limit.
        $limit = $general->getXunLimit($pageNumber);

        $searchActionType = $data["action_type"];
        $searchDate_to    = $data["date_to"];
        $searchDate_form  = $data["date_from"];
        $searchDevice_id  = $data["device_id"];
        $searchTracking_site  = $data["tracking_site"];
        $export = trim($data["seeAll"]);

        // $date_form =  date('Y-m-d H:i:s', $searchDate_form);
        // $date_to = date('Y-m-d H:i:s', $searchDate_to);

        foreach ($data as $key => $x_value) {

            $columnName = 'created_at';
            switch ($key) {
                case 'action_type':
                    if ($searchActionType != '') {
                        $db->where('action_type', $searchActionType . "%", 'LIKE');
                    }
                    break;
                case 'date_from':
                    if ($searchDate_form != '') {
                        // $db->where('created_at', $searchDate_form ,'>=');
                        $db->where($columnName, date('Y-m-d H:i:s', $searchDate_form), '>=');
                    }
                    break;
                case 'date_to':
                    if ($searchDate_to != '') {
                        $db->where($columnName, date('Y-m-d H:i:s', $searchDate_to), '<=');
                    }
                    break;
                case 'device_id':
                    if ($searchDevice_id != '') {
                        $db->where('device_id', $searchDevice_id . "%", 'LIKE');

                    }
                    break;
                case 'tracking_site':
                    if ($searchTracking_site != '') {
                        $db->where('tracking_site', "%" .$searchTracking_site . "%", 'LIKE');

                    }
                    break;

            }
        }

        $db->orderBy("created_at", "DESC");
        $copyDb = $db->copy();

        if ($export == 1) {
            $result = $db->get("utm_tracking");
        } else {
            $result = $db->get("utm_tracking", $limit);
        }

        if (!empty($result)) {
            foreach ($result as $value) {

                $utm_tracking['created_at']    = $value['created_at'];
                $utm_tracking['device_id']     = $value['device_id'];
                $utm_tracking['action_type']   = $value['action_type'];
                $utm_tracking['utm_campaign']  = $value['utm_campaign'];
                $utm_tracking['business_name'] = $value['business_name'];
                $utm_tracking['mobile_number'] = $value['mobile_number'];
                $utm_tracking['email_address'] = $value['email_address'];
                $utm_tracking['tracking_site'] = $value['tracking_site'];

                $countriesList[] = $utm_tracking;
            }

            $totalRecords = $copyDb->getValue("utm_tracking", "count(id)");

            $data['List']        = $countriesList;
            $data['totalPage']   = ceil($totalRecords / $limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord']   = $limit[1];

            return array('status' => "ok", 'code' => 1, 'statusMsg' => '', 'data' => $data);
        } else {
            return array('status' => "ok", 'code' => 1, 'statusMsg' => "No Results Found", 'data' => "");
        }

    }

    //referral
    public function get_business_listing($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;
        $general = $this->general;

        $pageNumber = $params['page'] ? $params['page'] : 1;
        //Get the limit.
        $limit = $general->getLimit($pageNumber);

        $business_email = $params["business_email"];
        $business_name  = $params["business_name"];
        $mobile         = $params["mobile"];
        $business_id    = $params["business_id"];
        $country        = $params["country"];
        $referral_code  = $params["referral_code"];

        foreach ($params as $key => $x_value) {

            $columnName = 'created_at';

            switch ($key) {
                case 'business_email':
                    if ($business_email != '') {
                        $db->where('b.email', $business_email . "%", 'LIKE');
                    }
                    break;
                case 'business_name':
                    if ($business_name != '') {
                        $db->where('b.name', $business_name . "%", 'LIKE');
                    }

                    break;
                case 'mobile':
                    if ($mobile != '') {
                        $db->where('b.phone_number', $mobile . "%", 'LIKE');
                    }
                    break;
                case 'business_id':
                    if ($business_id != '') {
                        $db->where('b.id', $business_id . "%", 'LIKE');
                    }
                    break;

                case 'country':
                    if ($country != '') {
                        $db->where('b.country', $country);
                    }
                    break;

                case 'referral_code':
                    if ($referral_code != '') {
                        $db->where('a.referral_code', $referral_code . "%", 'LIKE');
                    }
                    break;

            }
        }
        $db->join('xun_business_account a', 'b.email=a.email', 'LEFT');
        $db->orderBy("b.created_at", "DESC");
        $copyDb = $db->copy();

        if ($params['pagination'] == "No") {
            $result = $db->get("xun_business b", null, 'b.user_id as id, b.email, b.phone_number, b.created_at,b.country, b.name, a.last_login, a.referral_code');
            
            foreach ($result as $value) {

                $referral['business_id']           = (string) $value['id'];
                $referral['business_email']        = $value['email'];
                $referral['business_phone_number'] = $value['phone_number'] ?$value['phone_number'] : '' ;
                $referral['business_created_date'] = $value['created_at'];
                $referral['last_login']            = $value['last_login'] ? $value['last_login'] : '';
                $referral['business_country']      = $value['country'] ? $value['country'] : '';
                $referral['business_name']         = $value['name'];
                $referral['referral_code']         = $value['referral_code'] ? $value['referral_code'] : '';

                $referralList[] = $referral;

            }

            $totalRecords = $copyDb->getValue("xun_business b", "count(b.id)");
            $data['data'] = $referralList;

            $data['totalPage']   = 1;
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord']   = $totalRecords;

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Business listing", 'data' => $data);

        } else {
            $result = $db->get("xun_business b", $limit, 'b.user_id as id, b.email, b.phone_number, b.created_at,b.country, b.name, a.last_login, a.referral_code');

            foreach ($result as $value) {

                $referral['business_id']           = (string) $value['id'];
                $referral['business_email']        = $value['email'];
                $referral['business_phone_number'] = $value['phone_number'] ?$value['phone_number'] : '' ;
                $referral['business_created_date'] = $value['created_at'];
                $referral['last_login']            = $value['last_login'] ? $value['last_login'] : '';
                $referral['business_country']      = $value['country'] ? $value['country'] : '';
                $referral['business_name']         = $value['name'];
                $referral['referral_code']         = $value['referral_code'] ? $value['referral_code'] : '';

                $referralList[] = $referral;

            }

            $totalRecords = $copyDb->getValue("xun_business b", "count(b.id)");

            $data['data']        = $referralList;
            $data['totalPage']   = ceil($totalRecords / $limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord']   = $limit[1];

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Business listing", 'data' => $data);

        }
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "No Results Found", 'data' => "");

    }
    //-------------------------------------------------------------
    //Live chat setting
    //-------------------------------------------------------------
    public function add_edit_setting_admin($params)
    {

        $db = $this->db;

        $business_id                = $params["business_id"];
        $contactUsURL               = $params["contactUsURL"];
        $websiteUrl                 = $params["websiteUrl"];
        $liveChatNoAgentMsg         = $params["liveChatNoAgentMsg"];
        $liveChatAfterWorkingHrsMsg = $params["liveChatAfterWorkingHrsMsg"];
        $liveChatFirstMsg           = $params["liveChatFirstMsg"];
        $liveChatPromp              = $params["liveChatPromp"];
        $liveChatInfo               = $params["liveChatInfo"];

        $date = date("Y-m-d H:i:s");

        $final = $live_chat_info;

        if ($business_id == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Business id cannot be empty");
        }
        if ($liveChatInfo == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Live chat info cannot be empty");
        }

        if($contactUsURL){
            if(!filter_var($contactUsURL, FILTER_VALIDATE_URL)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please enter a valid Contact Us URL.", "developer_msg" => "contactUsURL is not a valid URL");
            }
        }

        if($websiteUrl){
            if(!filter_var($websiteUrl, FILTER_VALIDATE_URL)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please enter a valid Website URL.", "developer_msg" => "websiteUrl is not a valid URL");
            }
        }

        $db->where("business_id", $business_id);
        $result = $db->getOne("xun_business_livechat_setting");

        if (!$result) {

            $fields    = array("business_id", "contact_us_url", "website_url", "live_chat_no_agent_msg", "live_chat_after_working_hrs_msg", "live_chat_first_msg", "live_chat_prompt", "created_at");
            $values    = array($business_id, $contactUsURL, $websiteUrl, $liveChatNoAgentMsg, $liveChatAfterWorkingHrsMsg, $liveChatFirstMsg, $liveChatPromp, $date);
            $arrayData = array_combine($fields, $values);
            $insert    = $db->insert("xun_business_livechat_setting", $arrayData);

            $key["name"]  = $liveChatInfo[0];
            $key["email"] = $liveChatInfo[1];
            $final_key    = $key;

            foreach ($final_key as $key => $x) {

                $name  = $key;
                $value = $x;

                $fields_livechat_info = array("business_id", "livechat_setting_id", "live_chat_info", "type", "created_at", "updated_at");
                $values_livechat_info = array($business_id, $insert, $value, $name, $date, $date);
                $arrayData            = array_combine($fields_livechat_info, $values_livechat_info);
                $db->insert("xun_business_livechat_setting_livechat_info", $arrayData);

            }
        } else {
            $livechat_id = $result["id"];
            $updateData["contact_us_url"]                  = $contactUsURL;
            $updateData["website_url"]                     = $websiteUrl;
            $updateData["live_chat_no_agent_msg"]          = $liveChatNoAgentMsg;
            $updateData["live_chat_after_working_hrs_msg"] = $liveChatAfterWorkingHrsMsg;
            $updateData["live_chat_first_msg"]             = $liveChatFirstMsg;
            $updateData["live_chat_prompt"]                = $liveChatPromp;
            $updateData["updated_at"]                      = $date;

            $db->where("business_id", $business_id);
            $db->update("xun_business_livechat_setting", $updateData);

            $key["name"]  = $liveChatInfo[0];
            $key["email"] = $liveChatInfo[1];
            $final_key    = $key;

            foreach ($final_key as $key => $x) {

                $name  = $key;
                $value = $x;
                $updateData                        = [];
                $updateData["business_id"]         = $business_id;
                $updateData["livechat_setting_id"] = $livechat_id;
                $updateData["live_chat_info"]      = $value;
                $updateData["created_at"]          = $date;
                $updateData["updated_at"]          = $updated_at;

                $db->where("business_id", $business_id);
                $db->where("type", $name);

                $db->update("xun_business_livechat_setting_livechat_info", $updateData);
            }
            
        }
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Live Chat settings updated.", 'data' => "");
        
    }

    public function get_livechat_setting_admin($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;

        $business_id  = $params["business_id"];
        $check_record = $db->rawQuery("SELECT * FROM `xun_business_livechat_setting` WHERE  business_id = '$business_id'");

        $livechat_id = $check_record[0][id];

        if (!empty($check_record)) {

            $result_livechat_info = $db->rawQuery("SELECT `live_chat_info` FROM `xun_business_livechat_setting_livechat_info` WHERE  `business_id` = $business_id AND  `livechat_setting_id` = $livechat_id ");

            foreach ($result_livechat_info as $data) {

                $liveChatInfo = $data["live_chat_info"];

                $result[] = $liveChatInfo;

                $array_setting_info = $result;
            }
            foreach ($check_record as $data) {
                $business_id                    = $data["business_id"];
                $contactUsURL                   = $data["contact_us_url"];
                $websiteUrl                     = $data['website_url'];
                $contaliveChatNoAgentMsgctUsURL = $data["live_chat_no_agent_msg"];
                $liveChatAfterWorkingHrsMsg     = $data['live_chat_after_working_hrs_msg'];
                $liveChatFirstMsg               = $data["live_chat_first_msg"];
                $liveChatPromp                  = $data['live_chat_prompt'];

                $arrayData = array("business_id" => $business_id,
                    "contactUsURL"                   => $contactUsURL,
                    "websiteUrl"                     => $websiteUrl,
                    "liveChatNoAgentMsg"             => $liveChatNoAgentMsg,
                    "liveChatAfterWorkingHrsMsg"     => $liveChatAfterWorkingHrsMsg,
                    "liveChatFirstMsg"               => $liveChatFirstMsg,
                    "liveChatPromp"                  => $liveChatPromp,
                    "liveChatInfo"                   => $array_setting_info,
                );
                $array_setting = $arrayData;
            }

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => 'Setting details', 'data' => $array_setting);
        } else {

            $arrayData = array("business_id" => $business_id,
                "contactUsURL"                     => "",
                "websiteUrl"                       => "",
                "liveChatNoAgentMsg"               => "",
                "liveChatAfterWorkingHrsMsg"       => "",
                "liveChatFirstMsg"                 => "",
                "liveChatPromp"                    => "",
                "liveChatInfo"                     => array("Name", "Email"),
            );
            $array_setting = $arrayData;

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => 'Setting details', 'data' => $array_setting);

        }
    }
    //-------------------------------------------------------------
    //TEAM member
    //-------------------------------------------------------------
    public function admin_business_employee_add($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;

        $business_id = $params["business_id"];

        $employee_name   = $params["employee_name"];
        $employee_mobile = $params["employee_mobile"];
        $employee_role   = "employee";

        $companyName = $setting->systemSetting["companyName"];

        if ($business_id == '') {

            return array('status' => "error", "code" => 1, "statusMsg" => "Business Id cannot be empty");

        }

        if ($employee_name == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Employee Name cannot be empty");

        }

        if ($employee_mobile == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Employee Mobile cannot be empty");
        }

        $db->where("username", $employee_mobile);
        $result = $db->getOne("xun_user");
        
        if (empty($result)) {

            return array('status' => "error", "code" => 1, "statusMsg" => "This mobile number is not a registered " . $companyName . " user. Only registered " . $companyName . " users are allowed to be added as an employee.");
        }

        $db->where("business_id", $business_id);
        $db->where("mobile", $employee_mobile);
        $db->where("status", 1);
        $result = $db->getOne("xun_employee");

        if ($result) {

            return array('status' => "error", "code" => 1, "statusMsg" => "This mobile number has already been added as an employee.");

        }
        $old_id = $this->get_employee_old_id($business_id, $employee_mobile);

        $fields = array("business_id", "mobile", "name", "status", "employment_status", "created_at", "updated_at", "old_id", "role");
        $values = array($business_id, $employee_mobile, $employee_name, "1", "pending", date("Y-m-d H:i:s"), date("Y-m-d H:i:s"), $old_id, $employee_role);

        $insertData = array_combine($fields, $values);

        $new_employee_id = $db->insert("xun_employee", $insertData);

        //call api to send new employee message

        $newParams["business_id"]     = $business_id;
        $newParams["employee_mobile"] = $employee_mobile;
        $newParams["employee_id"]     = (string) $new_employee_id;
        $newParams["employee_role"]   = $employee_role;

        $url_string = "business/employee/add";
        $erlangReturn = $post->curl_post($url_string, $newParams);

        if ($erlangReturn["code"] == 0) {
            return $erlangReturn;
        }
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "New member successfully added.", 'result' => $returnData);

    }

    public function admin_business_employee_edit($params)
    {

        $db = $this->db;

        $business_id   = $params["business_id"];
        $employee_name = $params["employee_name"];
        $employee_id   = $params["employee_id"];

        if ($business_id == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Business Id cannot be empty");

        }

        if ($employee_name == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Employee Name cannot be empty");

        }

        if ($employee_id == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Employee Id cannot be empty");

        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('status' => "error", "code" => 1, "statusMsg" => "Business not found");

        }

       

        $updateData["name"] = $employee_name;

        if (strpos($employee_id, '_') !== false) {
            $db->where("business_id", $business_id);
            $db->where("old_id", $employee_id);
            $db->update("xun_employee", $updateData);
        }
        $db->where("business_id", $business_id);
        $db->where("id", $employee_id);
        $db->update("xun_employee", $updateData);

        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Business employee details updated.");

    }

    public function admin_business_employee_get($params)
    {

        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;
        $general = $this->general;

        $pageNumber = $params['page'] ? $params['page'] : 1;
        //Get the limit.
        $limit = $general->getLimit($pageNumber);

        $business_id = $params["business_id"];

        $db->orderBy("created_at", "DESC");
        $copyDb = $db->copy();
        $db->where('business_id', $business_id);
        $db->where('status',1);
        $history = $db->get("xun_employee", $limit);

        $db->where('business_id', $business_id);
        $db->where('status',1);
        $record = $db->get("xun_employee");

        // $history = $db->rawQuery("SELECT `id` , `sent_mobile_length`,`tag` ,`sent_datetime` FROM xun_publish_message_log  WHERE `business_id` = '$business_id'");
        foreach ($history as $value) {
            # code...

            $result[] = array('status' => $value[employment_status], 'name' => $value[name], 'mobile' => $value[mobile], 'employee_id' => $value[old_id], 'date' => $value[created_at]);
        }

        if (empty($history)) {
            return array('status' => 'ok', 'statusMsg' => "No Results Found", 'data' => "" , 'code' => 0);

        }

        $totalRecords = sizeof($record);
        //$totalRecords = $copyDb->getValue("xun_publish_message_log", "count(id)");
        $data['data'] = $result;

        $data['totalPage']   = ceil($totalRecords / $limit[1]);
        $data['pageNumber']  = $pageNumber;
        $data['totalRecord'] = $totalRecords;
        $data['numRecord']   = $limit[1];

        return array('status' => 'ok', 'statusMsg' => "Success", 'data' => $data , 'code' => 0);

    }

    public function admin_business_employee_confirm_list($params)
    {
        $db = $this->db;

        $business_id    = $params["business_id"];
       

        if ($business_id == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Business Id cannot be empty");
        }


        $db->where("business_id", $business_id);
        $db->where("employment_status", "confirmed");
        $db->where("status", 1);
        $db->where("role", 'employee');
        $result = $db->get("xun_employee");

        foreach ($result as $data) {
            $returnData[] = $this->compose_xun_employee($data);
        }

        return array('status' => 'ok', 'statusMsg' => "Search result", 'data' => $returnData , 'code' => 0);

    }

    public function admin_business_employee_details_get($params)
    {
        $db = $this->db;

        $business_id = $params["business_id"];
        $employee_id = $params["employee_id"];

        if ($business_id == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Business Id  cannot be empty");

        }

        if ($employee_id == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Employee Id cannot be empty");

        }

        $db->where("business_id", $business_id);
        $db->where("old_id", $employee_id);
        $result = $db->getOne("xun_employee");

        if (!$result) {
            return array('status' => "error", "code" => 1, "statusMsg" => "This record does not exist");
        }

        $returnData = $this->compose_xun_employee($result);

        return array('status' => "ok", "code" => 0, "statusMsg" => "Team member details", "data" => $returnData);

    }

    public function admin_business_employee_delete($params)
    {
        $db = $this->db;
        
        $business_id    = $params["business_id"];
        $employee_id    = $params["employee_id"];
        $now            = date("Y-m-d H:i:s");
        
        if ($business_id == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Business Id cannot be empty");
            
        }
        
        if ($employee_id == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Employee Id cannot be empty");
        }
        
        if (gettype($employee_id) == 'string') {
            $employee_id_arr = array($employee_id);
        } else {
            $employee_id_arr = $employee_id;
        }
        
        global $xunBusiness;
        $erlangReturn = $xunBusiness->delete_business_employee($business_id, $employee_id_arr);

        return array('status' => "ok", "code" => 0, "statusMsg" => "Team member successfully deleted");
    }

    public function admin_business_employee_delete_all($params)
    {
        $db = $this->db;

        $business_id    = $params["business_id"];
     

        if ($business_id == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Business Id cannot be empty");
        }

        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $db->where("role", "owner", "!=");

        $xun_employee = $db->get("xun_employee", null, "old_id");
        
        $employee_id_arr = [];
        
        foreach($xun_employee as $employee){
            $employee_id_arr[] =$employee['old_id'];
        }

        global $xunBusiness;
        $erlangReturn = $xunBusiness->delete_business_employee($business_id, $employee_id_arr);
        
        return array('status' => "ok", "code" => 0, "statusMsg" => "Team members successfully deleted");
    }


       private function compose_xun_employee($result)
    {
        $status = $result["employment_status"];

        if ($status == 'pending') {
            $status_cp = 0;
        } else {
            $status_cp = 1;
        }

        $returnData["employee_id"]            = $result["old_id"];
        $returnData["business_id"]            = $result["business_id"];
        $returnData["employee_mobile"]        = $result["mobile"];
        $returnData["employee_name"]          = $result["name"];
        $returnData["employee_status"]        = $status_cp;
        $returnData["employee_role"]          = $result["role"];
        $returnData["employee_created_date"]  = $result["created_at"];
        $returnData["employee_modified_date"] = $result["updated_at"];

        return $returnData;
    }




    //category
    public function admin_business_tag_add($params)
    {
        $db   = $this->db;
        $post = $this->post;

        $business_id       = $params["business_id"];
        $tag               = $params["tag"];
        $callback_url      = $params["callback_url"];
        $tag_description   = $params["tag_description"];
        $employee_mobile   = $params["employee_mobile"];
        $working_hour_from = $params["working_hour_from"];
        $working_hour_to   = $params["working_hour_to"];
        $priority          = $params["priority"];

        global $config;
        $employee_server = $config["erlang_server"];
        $date            = date("Y-m-d H:i:s");

        if ($business_id == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Business Id cannot be empty");

        }

        if ($tag == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Tag cannot be empty");

        }

        if ($priority == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Priority cannot be empty");

        }

        if($callback_url){
            if(!filter_var($callback_url, FILTER_VALIDATE_URL)){
                return array('status' => "error", "code" => 1, "statusMsg" => "Please enter a valid forward URL.");
            }
        }

        //check business
        $db->where("user_id", $business_id);
        $check_business = $db->getOne("xun_business");
        if (empty($check_business)) {
            return array('status' => "error", "code" => 1, "statusMsg" => "Invalid business record");

        }

        //check duplicate name
        $check_duplicate_name = $db->rawQuery("SELECT 'name' FROM `xun_business_tag` WHERE business_id = '$business_id' AND status = '1' AND  tag = '$tag'");
        if (!empty($check_duplicate_name)) {
            return array('status' => "error", "code" => 1, "statusMsg" => "This business already have a similar tag added");

        }

        //get employee mobile
        $get_owner_moblie   = $db->rawQuery("SELECT * FROM `xun_employee` WHERE business_id = '$business_id' AND status = '1' AND  role = 'owner'");
        $owner_mobile       = $get_owner_moblie[0][mobile];
        $new_owner_mobile[] = $owner_mobile;

        //store in database xun_business_tag
        $fields    = array("business_id", "tag", "description", "working_hour_from", "working_hour_to", "status", "priority", "created_at", "updated_at");
        $values    = array($business_id, $tag, $tag_description, $working_hour_from, $working_hour_to, "1", $priority, $date, $date);
        $arrayData = array_combine($fields, $values);
        $db->insert("xun_business_tag", $arrayData);

        //combine onwer mobile and moblie list
        if (empty($employee_mobile)) {

            $new_moblie_list = $new_owner_mobile;
        } else {

            $new_moblie_list = array_unique(array_merge($new_owner_mobile, $employee_mobile));

        }

        //store in database xun_business_tag
        foreach ($new_moblie_list as $value) {

            $get_employee_details = $db->rawQuery("SELECT * FROM `xun_employee` WHERE business_id = '$business_id' AND status = '1' AND  mobile = '$value' AND employment_status = 'confirmed'");

            // if (empty($get_employee_details)) {
            //   return array('code' => 0, 'message' => "FAILED", 'message_d' => "Not employee");
            //  }

            foreach ($get_employee_details as $value) {

                $employee_id = $get_employee_details[0][old_id];
                $username    = $get_employee_details[0][mobile];
                $business_id = $get_employee_details[0][business_id];

                $fields    = array("employee_id", "username", "business_id", "tag", "status", "created_at", "updated_at");
                $values    = array($employee_id, $username, $business_id, $tag, "1", $date, $date);
                $arrayData = array_combine($fields, $values);
                $db->insert("xun_business_tag_employee", $arrayData);

            }

        }

        //store xun business forward message
        if (!$callback_url == '') {
            $check_business_forward_message = $db->rawQuery("SELECT * FROM `xun_business_forward_message` WHERE business_id = '$business_id' AND tag = '1' AND  status = '$1'");

            if (empty($check_business_forward_message)) {
                $fields    = array("tag", "business_id", "forward_url", "status", "created_at", "updated_at");
                $values    = array($tag, $business_id, $callback_url, '1', $date, $date);
                $arrayData = array_combine($fields, $values);
                $db->insert("xun_business_forward_message", $arrayData);

            }

        }
        $new_moblie_list = array_filter($new_moblie_list);

        //builed final mobile list
        foreach ($new_moblie_list as $value) {

            $get_employee_details = $db->rawQuery("SELECT `mobile` , `role` FROM `xun_employee` WHERE business_id = '$business_id' AND status = '1' and  mobile = '$value'");

            $add_employee_mobile = $get_employee_details[0][mobile];
            $add_employee_server = $employee_server;
            $add_employee_role   = $get_employee_details[0][role];

            $final_employee_list[] = array('employee_mobile' => $add_employee_mobile,
                'employee_server'                                => $add_employee_server, 'employee_role' => $add_employee_role);

        }

        //subscribe list

        $subscribe_list = [];
        foreach ($new_moblie_list as $value) {

            $employe_number  = $value . "@" . $employee_server;
            $subscribe_list[] = $employe_number;

        }

        if (empty($get_employee_details)) {
            $final_employee_list = [];
        }

        $removed_employee_list = [];

        $erlangReturn = $this->send_xmpp_business_tag_event($business_id, $tag, $final_employee_list, $removed_employee_list, $subscribe_list);

        if ($erlangReturn["code"] === 0) {
            return array("status" => "error", "statusMsg" => $erlangReturn["message_d"], 'code' => 1);
        }

        return array("status" => "ok", "statusMsg" => "New category successfully added.", "code" => 0);

    }

    public function admin_business_tag_edit($params)
    {
        global $config;
        
        $db   = $this->db;
        $post = $this->post;

        $business_id       = $params["business_id"];
        $tag               = $params["tag"];
        $callback_url      = $params["callback_url"];
        $tag_description   = $params["tag_description"];
        $employee_mobile   = $params["employee_mobile"];
        $working_hour_from = $params["working_hour_from"];
        $working_hour_to   = $params["working_hour_to"];
        $priority          = $params["priority"];

        $date              = date("Y-m-d H:i:s");

        if ($business_id == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Business Id cannot be empty");
        }
        if ($tag == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Tag cannot be empty");
        }
        if ($priority == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Priority cannot be empty");

        }

        if($callback_url){
            if(!filter_var($callback_url, FILTER_VALIDATE_URL)){
                return array('status' => "error", "code" => 1, "statusMsg" => "Please enter a valid forward URL.");
            }
        }

        //check record in xun tag
        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", "1");
        $check_tag = $db->getOne("xun_business_tag");
        
        if (!$check_tag) {
            return array('status' => "error", "code" => 1, "statusMsg" => "Invalid record");
        } 

        $updateData["working_hour_from"] = $working_hour_from;
        $updateData["working_hour_to"]   = $working_hour_to;
        $updateData["description"]       = $tag_description;
        $updateData["priority"]          = $priority;
        $updateData["updated_at"]        = $date;

        $db->where("id", $check_tag["id"]);
        $db->update("xun_business_tag", $updateData);
            
        unset($updateData);

        //check tag_employee (check the record in xun_business_tag)
        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", "1");
        $check_tag_employee = $db->get("xun_business_tag_employee");
        
        $initial_tag_employee_list = array();
        $owner_mobile = array();

        // param employee id
        foreach ($check_tag_employee as $value) {
            $initial_tag_employee_list[] = $value["username"];
            $initial_employee_id[$value["username"]] = $value["id"];
        }
        
        $db->where("business_id", $business_id);
        $db->where("employment_status", "confirmed");
        $employee_result = $db->get("xun_employee");
        
        foreach($employee_result as $employee_data){
            
            if($employee_data["role"] == "owner"){
                $owner_mobile = $employee_data["mobile"];
            }
            
            $employee_ids[$employee_data["mobile"]] = $employee_data["old_id"];
            $employee_roles[$employee_data["mobile"]] = $employee_data["role"];
            
        }
        
        $employee_mobile = array_filter($employee_mobile);
        //remove owner mobile
        $initial_employee_list = array_diff($initial_tag_employee_list, array($owner_mobile));
    
        $remove_employee_list = array_diff($initial_employee_list, $employee_mobile);
        $add_employee_list = array_diff($employee_mobile, $initial_employee_list);
        
        if($add_employee_list){
            
            foreach($add_employee_list as $mobile){
                
                $employee_id = $employee_ids[$mobile];
                
                $fields = array("employee_id", "username", "business_id", "tag", "status", "created_at", "updated_at");
                $values = array($employee_id, $mobile, $business_id, $tag, "1", $date, $date);
                $insertData = array_combine($fields, $values);
                
                $db->insert("xun_business_tag_employee", $insertData);
                
            }
            
        }
        
        if($remove_employee_list){
            
            foreach($remove_employee_list as $mobile){
                
                $id = $initial_employee_id[$mobile];
                
                $updateData["status"] = "0";
                $updateData["updated_at"] = $date;
                
                $db->where("id", $id);
                $db->update("xun_business_tag_employee", $updateData);
                
            }
            
        }
        
        unset($updateData);

        //insert  / update url xun_business_forward table
        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $xun_business_forward_message = $db->getOne("xun_business_forward_message");

        if ($callback_url) {
            if(!$xun_business_forward_message){
                $fields    = array("tag", "business_id", "forward_url", "status", "created_at", "updated_at");
                $values    = array($tag, $business_id, $callback_url, '1', $date, $date);
                $arrayData = array_combine($fields, $values);
                $db->insert("xun_business_forward_message", $arrayData);

            } else {
                $id = $xun_business_forward_message["id"];

                $updateData["status"] = 1;
                $updateData["forward_url"] = $callback_url;
                $updateData["updated_at"]  = $date;
                $db->where("id", $id);
                $db->update("xun_business_forward_message", $updateData);
            }
        }else{
            if($xun_business_forward_message["status"] == 1){
                $id = $xun_business_forward_message["id"];

                $updateData["status"] = 0;
                $updateData["updated_at"]  = $date;
                $db->where("id", $id);
                $db->update("xun_business_forward_message", $updateData);
            }
        }
        
        // build final subscribers_jid
        $final_new_subscribers = array_unique(array_merge($initial_tag_employee_list, $add_employee_list));
        
        foreach($final_new_subscribers as $mobile){
            $subscribers_jid[] = $mobile."@".$config["erlang_server"];
        }
        
        foreach($add_employee_list as $mobile){
            $employee_role = $employee_roles[$mobile];
        
            $employee["employee_mobile"] = $mobile;
            $employee["employee_server"] = $config["erlang_server"];
            $employee["employee_role"]   = $employee_role;
        
            $new_employee_list[] = $employee;
        }
        
        foreach($remove_employee_list as $mobile){
            $employee_role = $employee_roles[$mobile];
        
            $employee["employee_mobile"] = $mobile;
            $employee["employee_server"] = $config["erlang_server"];
            $employee["employee_role"]   = $employee_role;
        
            $removed_employee_list[] = $employee;
        }

        $subscribers_jid = $subscribers_jid ? $subscribers_jid : array();
        $new_employee_list = $new_employee_list ? $new_employee_list : array();
        $removed_employee_list = $removed_employee_list ? $removed_employee_list : array();

        $erlangReturn = $this->send_xmpp_business_tag_event($business_id, $tag, $new_employee_list, $removed_employee_list, $subscribers_jid);

        if ($erlangReturn["code"] === 0) {
            return array("status" => "error", "statusMsg" => $erlangReturn["message_d"], 'code' => 1);
        }

        return array("status" => "ok", "statusMsg" => "Category details updated.", "code" => 0);

    }

    public function admin_business_tag_list($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $post    = $this->post;
        $general = $this->general;

        $pageNumber = $params['page'] ? $params['page'] : 1;
        //Get the limit.
        $limit = $general->getLimit($pageNumber);

        $business_id = $params["business_id"];

        if ($business_id == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Business Id cannot be empty");
        }

        $db->where("user_id", $business_id);
        $result_xun_business = $db->getOne("xun_business");

        if (!$result_xun_business) {
            return array('status' => "error", "code" => 1, "statusMsg" => "Invalid business id");

        }

        $db->orderBy("created_at", "DESC");
        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $result = $db->get("xun_business_tag", $limit);

        foreach ($result as $data) {

            $tag_description = $data["description"];
            if (is_null($tag_description)) {
                $tag_description = "";
            }

            $working_hour_to   = $data["working_hour_to"];
            $working_hour_from = $data["working_hour_from"];
            $tag_description   = $data["description"];
            $tag               = $data["tag"];
            $priority          = $data["priority"];
            $created_at        = $data["created_at"];

            $db->where("business_id", $business_id);
            $db->where("tag", $tag);
            $db->where("status", "1");
            $callback_result = $db->getOne("xun_business_forward_message");
            
            $forward_url       = $callback_result["callback_url"] ? $callback_result["callback_url"] : "";
            
            $number_employee_tag_rec = $db->rawQuery("SELECT count(*) as total_member FROM xun_business_tag_employee as bte JOIN xun_employee as xe on bte.employee_id = xe.id or bte.employee_id = xe.old_id WHERE bte.business_id = '" . $business_id . "' and xe.status = '1'  and bte.status = '1' and tag = '" . $tag . "' and role = 'employee' and xe.employment_status = 'confirmed'");

            $total_employee = $number_employee_tag_rec[0]["total_member"];

            $returnData[] = array("working_hour_to" => $working_hour_to,
                "working_hour_from"                     => $working_hour_from,
                "description"                           => $tag_description,
                "name"                                  => $tag,
                "priority"                              => $priority,
                "url"                                   => $forward_url,
                "date"                                  => $created_at,
                "count"                                 => $total_employee,
                "business_id"                           => $result[business_id],

            );

        }

        $sort = array();
        foreach ($returnData as $key => $row) {
            $sort[$key] = $row['priority'];
        }
        array_multisort($sort, SORT_ASC, $returnData);

       

        $totalRecords = sizeof($returnData);
        //$totalRecords = $copyDb->getValue("xun_publish_message_log", "count(id)");
        $record['data'] = $returnData;

        $record['totalPage']   = ceil($totalRecords / $limit[1]);
        $record['pageNumber']  = $pageNumber;
        $record['totalRecord'] = $totalRecords;
        $record['numRecord']   = $limit[1];


         if (empty($returnData)) {
            return array('status' => 'ok', 'code' => 0, 'statusMsg' => 'No Results Found', "data" => "");
            
        }

        return array('status' => 'ok', 'code' => 0, 'statusMsg' => 'Success', "data" => $record);

    }

    public function admin_business_tag_get($params)
    {
        $db = $this->db;

        $business_id = $params["business_id"];
        $tag         = $params["tag"];

        if ($business_id == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Business Id cannot be empty");
        }

        if ($tag == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Business tag cannot be empty");
        }

        $db->where("user_id", $business_id);
        $result_xun_business = $db->getOne("xun_business");

        if (!$result_xun_business) {
            return array('status' => "error", "code" => 1, "statusMsg" => "Invalid business id");
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid business id.");
        }

        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $result = $db->getOne("xun_business_tag");

        if (!$result) {
            return array('status' => "error", "code" => 1, "statusMsg" => "Business Tag not found", "business_id" => $business_id, 'data' => "");
        }

        $tag               = $result["tag"];
        $tag_description   = $result["description"];
        $created_date      = $result["created_at"];
        $working_hour_to   = $result["working_hour_to"];
        $working_hour_from = $result["working_hour_from"];
        $priority          = $result["priority"];

        $result_business_forward_message = $db->rawQuery("SELECT `forward_url` FROM `xun_business_forward_message` WHERE business_id = '$business_id' AND tag = '$tag' AND status = 1");

        if (empty($result_business_forward_message)) {
            $callback_url = "";
        } else {
            $callback_url = $result_business_forward_message[0]["forward_url"];
        }

        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $employee_result = $db->get("xun_business_tag_employee");
        foreach ($employee_result as $employee_data) {

            $employees[] = $employee_data["username"];

        }

        $returnData["business_id"]       = $business_id;
        $returnData["tag"]               = $tag;
        $returnData["callback_url"]      = $callback_url;
        $returnData["employee_mobile"]   = $employees;
        $returnData["created_date"]      = $result["created_at"];
        $returnData["working_hour_to"]   = $working_hour_to;
        $returnData["working_hour_from"] = $working_hour_from;
        $returnData["priority"]          = $priority;
        $returnData["tag_description"]   = $tag_description;

        return array('status' => 'ok', 'code' => 0 , 'statusMsg' => "Category details.", "business_id" => $business_id, 'data' => $returnData);
    }

    public function admin_business_tag_delete($params)
    {
        $db   = $this->db;
        $post = $this->post;

        $business_id = $params["business_id"];
        $tag_list    = $params["tag"];

        global $config;
        global $xunXmpp;

        $employee_server = $config["erlang_server"];

        $date = date("Y-m-d H:i:s");

        if ($business_id == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Business Id cannot be empty");
        }

        if ($tag_list == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Tag list cannot be empty");

        }

        if(!is_array($tag_list)){
            $tag_list = [$tag_list];
        }

        $returnResult = $this->delete_business_tag($business_id, $tag_list);

        return $returnResult;
    }

    public function admin_business_tag_delete_all($params)
    {

        $db   = $this->db;
        $post = $this->post;

        $business_id = $params["business_id"];
        $date        = date("Y-m-d H:i:s");

        global $config;
        $employee_server = $config["erlang_server"];

        if ($business_id == '') {
            return array('status' => "error", "code" => 1, "statusMsg" => "Business Id cannot be empty");
        }

        // get tag list of business
        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $xun_business_tag = $db->get("xun_business_tag", null, "tag");

        $tag_list = [];
        if($xun_business_tag){
            foreach($xun_business_tag as $value){
                $tag_list[] = $value["tag"];
            }
            
        }

        $returnResult = $this->delete_business_tag($business_id, $tag_list);

        return $returnResult;
    }

    private function delete_business_tag($business_id, $tag_list){
        $db   = $this->db;
        $post = $this->post;

        global $config;
        global $xunXmpp;

        $employee_server = $config["erlang_server"];
        $date = date("Y-m-d H:i:s");

        $default_tag_employee = [];
        foreach ($tag_list as $tag) {
            $db->where("business_id", $business_id);
            $db->where("tag", $tag);
            $db->where("status", 1);
            $xun_business_tag_employee = $db->get("xun_business_tag_employee");
            if($xun_business_tag_employee){
                $default_tag_employee[$tag][] = $xun_business_tag_employee;
            }
        }

        foreach ($tag_list as $tag) {
            $updateTag = [];
            $updateTag["status"]     = 0;
            $updateTag["updated_at"] = $date;
            $db->where("business_id", $business_id);
            $db->where("tag", $tag);
            $db->update("xun_business_tag", $updateTag);

            $updateTagEmployee = [];
            $updateTagEmployee["status"]     = 0;
            $updateTagEmployee["updated_at"] = $date;
            $db->where("business_id", $business_id);
            $db->where("tag", $tag);
            $db->where("status", 1);
            $db->update("xun_business_tag_employee", $updateTagEmployee);

            $updateForwardMessage = [];
            $updateForwardMessage["status"] = 0;
            $updateForwardMessage["updated_at"] = $date;
            $db->where("business_id", $business_id);
            $db->where("tag", $tag);
            $db->where("status", 1);
            $db->update("xun_business_forward_message", $updateForwardMessage);
        }

        /*
            get_default_tag_employee = [ [], [] ]
        */
        //build final remove employee

        $erlangReturnArr = [];
        foreach ($default_tag_employee as $tag => $tag_employee_arr) {
            // loop tag
            $final_remove_employee_list = [];
            $subscriber_jid_list = [];

            foreach ($tag_employee_arr[0] as $tag_employee) {
                // loop tag_employee
                # code...

                $employee_id = $tag_employee["employee_id"];
                $employee_mobile = $tag_employee["username"];

                $db->where("business_id", $business_id);
                $db->where("mobile", $employee_mobile);
                $db->where("status", 1);
                $xun_employee = $db->getOne("xun_employee");

                $remove_employee_mobile = $employee_mobile;
                $remove_employee_server = $employee_server;
                $remove_employee_role   = $xun_employee["role"];

                $remove_employee_arr = array(
                    'employee_mobile' => $remove_employee_mobile,
                    'employee_server'=> $remove_employee_server, 
                    'employee_role' => $remove_employee_role
                );

                $final_remove_employee_list[] = $remove_employee_arr;

                //  build sub employee
                $subscriber_jid_list[] = $employee_mobile . "@" . $employee_server;
            }
            
            // call erlang here - per tag
            // only call of new and removed list are empty
            $erlangReturnArr[] = $xunXmpp->send_xmpp_business_tag_event($business_id, $tag, [], $final_remove_employee_list, $subscriber_jid_list);
        }

        return array("status" => "ok", "statusMsg" => "The selected category has been deleted.", "code" => 0);
    }


    public function adminGetUserVerificationCodeListing($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $general = $this->general;
        $post    = $this->post;

        $admin_page_limit      = $setting->getAdminPageLimit();
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order                 = $params["order"];
        $mobile                = $params["mobile"];
        $status                = $params["status"];
        $from_datetime         = $params["from_datetime"];
        $to_datetime           = $params["to_datetime"];
        $action_type           = $params["action_type"];
        $country               = $params["country"];

        if ($mobile) {
            $db->where("mobile", "%$mobile%", "LIKE");
        }

        if ($status) {
            $status = strtolower($status);
            $db->where("status", "%$status%", "LIKE");
        }

        if ($from_datetime) {
            $from_datetime  = date("Y-m-d H:i:s", $from_datetime);
            $db->where("created_at", $from_datetime, ">=");
        }

        if ($to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $db->where("created_at", $to_datetime, "<=");
        }

        if ($action_type) {
            $action_type = strtolower($action_type);
            if($action_type == "request"){
                $db->where("request_at", 0, ">");
            }else if($action_type == "validate"){
                $db->where("verify_at", 0, ">");
            }
        }
    
        if ($country){
            $db->where("country", "%$country%", "LIKE");
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $copyDb = $db->copy();
        $db->orderBy("id", "DESC");
        $result = $db->get("xun_user_verification", $limit);

        foreach ($result as $data) {
            $record = [];
            $record["id"] = $data["id"];
            $record["mobile"] = $data["mobile"];
            $record["action_type"] = $data["request_at"] > 0 ? "request" : "validate";
            $record["verification_code"] = $data["verification_code"];
            $record["created_at"] = $data["created_at"];
            $record["status"] = $data["status"];
            $record["country"] = $data["country"];
            $record["device_os"] = $data["device_os"];
            $record["os_version"] = $data["os_version"];
            $record["phone_model"] = $data["phone_model"];
            $record["message"] = $data["message"];
            $record["sms_message_content"] = $data["sms_message_content"];
            $record["user_type"] = $data["user_type"];

            $return[] = $record;
        }

        if(!$return) return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
        //totalPage, pageNumber, totalRecord, numRecord
        $totalRecord = $copyDb->getValue("xun_user_verification", "count(id)");
        
        $returnData["data"] = $return;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;

        return array('status' => 'ok', 'code' => "0", 'statusMsg' => "", 'data' => $returnData);
    }

    public function add_news($params){

        $db = $this->db;

        $type = "news_update";
        $content_type = trim($params["content_type"]);
        $url_name = trim($params["url_name"]);
        $redirect_url = trim($params["redirect_url"]);
        // $image_name = trim($params["image_name"]);
        $image_url = trim($params["image_url"]);
        $title = trim($params["title"]);
        $source = trim($params["source"]);
        $content = trim($params["content"]);
        $meta_title = trim($params["meta_title"]);
        $meta_description = trim($params["meta_description"]);

        if ($content_type == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Content type cannot be empty");
        }
        
        if ($content_type == 'blog'){
            if ($url_name == ''){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "URL name cannot be empty");
            }
        }else if($content_type == 'news'){
            if ($redirect_url == ''){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Redirect URL cannot be empty");
            }
            
            if (!filter_var($redirect_url, FILTER_VALIDATE_URL)){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => "Please enter a valid redirect URL.");
            }
        }else{
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid content type.");
        }

        if ($title == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Title cannot be empty");
        }

        if ($content == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Content cannot be empty");
        }

        $now = date('Y-m-d H:i:s');
        if ($content_type == 'blog'){
            $insertData = array(
                "type" => $type,
                "content_type" => $content_type,
                "title" => $title,
                "url_name" => $url_name,
                "source" => $source,
                "content" => $content,
                "media_url" => $image_url,
                "meta_title" => $meta_title,
                "meta_description" => $meta_description,
                "status" => 1,
                "created_at" => $now,
                "updated_at" => $now
            );
        }else{
            $insertData = array(
                "type" => $type,
                "content_type" => $content_type,
                "title" => $title,
                "redirect_url" => $redirect_url,
                "source" => $source,
                "content" => $content,
                "meta_title" => $meta_title,
                "meta_description" => $meta_description,
                "status" => 1,
                "created_at" => $now,
                "updated_at" => $now
            );
        }
        $blog_post_id=$db->insert("xun_blog_post", $insertData);
       
         return array('status' => 'ok', 'code' => 0, 'statusMsg' => "News saved.");
        
    }

    public function add_article($params){
        $db = $this->db;

        $type = "new_blog";
        $media_type = "article";
        $title = trim($params["title"]);
        $content = trim($params["content"]);
        $meta_title = trim($params["meta_title"]);
        $meta_description = trim($params["meta_description"]);
        $image_url = trim($params["image_url"]);
        $tag = $params["tag"];
        $video_url = $params["video_url"];

        if($media_type == '' ){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Media type cannot be empty");
        }

        if($media_type == 'article'){
            if ($content == '') {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Content cannot be empty");
            }
        }
        else{
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid media type.");
        }
       
        if ($title == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Title cannot be empty");
        }

        if ($tag == ''){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Tag cannot be empty");
        }

        $tag_string = implode(",", $tag);


        $now = date('Y-m-d H:i:s');
        if ($media_type == 'article'){
            $insertData = array(
                "type" => $type,
                "media_type" => $media_type,
                "title" => $title,
                "content" => $content,
                "media_url" => $image_url,
                "meta_title" => $meta_title,
                "meta_description" => $meta_description,
                "tag" => $tag_string,
                "status" => 1,
                "created_at" => $now,
                "updated_at" => $now
            );
        }

        $blog_post_id=$db->insert("xun_blog_post", $insertData);

        /*
        foreach($tag as $data){
            $insertTag = array (
                "blog_post_id" => $blog_post_id,
                "tag" => $data,
            );
            $db->insert('xun_blog_tag', $insertTag);
        }*/
    
         return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Article saved.");
    }

    public function add_video($params)
    {
        $db = $this->db;

        $type = "new_blog";
        $media_type = "video";
        $title = trim($params["title"]);
        $content = trim($params["content"]);
        $meta_title = trim($params["meta_title"]);
        $meta_description = trim($params["meta_description"]);
        $image_url = trim($params["image_url"]);
        $tag = $params["tag"];
        $video_url = $params["video_url"];

        if($media_type == '' ){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Media type cannot be empty");
        }
        
        if($media_type == 'video'){
            if ($video_url == '') {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Video URL cannot be empty");
            }
        }
        else{
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid media type.");
        }

        if ($title == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Title cannot be empty");
        }

        if ($tag == ''){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Tag cannot be empty");
        }

        $tag_string = implode(",", $tag);

        $now = date('Y-m-d H:i:s');
        if($media_type == 'video'){
            $insertData = array(
                "type" => $type,
                "media_type" => $media_type,
                "title" => $title,
                "tag" => $tag_string,
                "media_url" => $video_url,
                "status" => 1,
                "created_at" => $now,
                "updated_at" => $now
            );
        }
        $blog_post_id=$db->insert("xun_blog_post", $insertData);
/*
        foreach($tag as $data){
            $insertTag = array (
                "blog_post_id" => $blog_post_id,
                "tag" => $data,
            );
            $db->insert('xun_blog_tag', $insertTag);
        }*/
    
         return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Video saved.");
    }
    public function edit_news($params)
    {
        $db = $this->db;
        
       
        $record_id = trim($params["id"]);
        $url_name = trim($params["url_name"]);
        $redirect_url = trim($params["redirect_url"]);
        $title = trim($params["title"]);
        $source = trim($params["source"]);
        $content = trim($params["content"]);
        $image_url = trim($params["image_url"]);
        $meta_title = trim($params["meta_title"]);
        $meta_description = trim($params["meta_description"]);
        
        if ($record_id == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "ID cannot be empty");
        }
        if ($title == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Title cannot be empty");
        }
        
        if ($content == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Content cannot be empty");
        }
        
       
        $now = date('Y-m-d H:i:s');
        
        $db->where("id", $record_id);
        $xun_blog_post = $db->getOne("xun_blog_post");
        
        if (!$xun_blog_post) {
            return array('status' => "error", "code" => 1, "statusMsg" => "This record does not exist");
        }
        
        $content_type = $xun_blog_post["content_type"];

        if ($content_type == 'blog'){
            if ($url_name == ''){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "URL name cannot be empty");
            }
            
            $updateData["url_name"] = $url_name;
            $updateData["media_url"] = $image_url;
            $updateData["title"] = $title;
            $updateData["source"] = $source;
            $updateData["content"] = $content;
            $updateData["meta_title"] = $meta_title;
            $updateData["meta_description"] = $meta_description;
            $updateData["updated_at"] = $now;
            $db->where("id", $record_id);
            $return =  $db->update("xun_blog_post", $updateData); return array("status" => "ok", "code" => 0, "statusMsg" => "Blog changes updated." );


        }else if($content_type == 'news'){
            if ($redirect_url == ''){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Redirect URL cannot be empty");
            }
            
            if (!filter_var($redirect_url, FILTER_VALIDATE_URL)){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => "Please enter a valid redirect URL.");
            }
            
            $updateData["redirect_url"] = $redirect_url;
            $updateData["title"] = $title;
            $updateData["source"] = $source;
            $updateData["content"] = $content;
            $updateData["meta_title"] = $meta_title;
            $updateData["meta_description"] = $meta_description;
            $updateData["updated_at"] = $now;
            $db->where("id", $record_id);
            $return =  $db->update("xun_blog_post", $updateData);
            return array("status" => "ok", "code" => 0, "statusMsg" => "News changes updated." );
        }

    }

    public function edit_article($params){
        $db = $this->db;
        
        $record_id = trim($params["id"]);
        $title = trim($params["title"]);
        $content = trim($params["content"]);
        $tag = $params["tag"];
        $image_url = trim($params["image_url"]);
        $meta_title = trim($params["meta_title"]);
        $meta_description = trim($params["meta_description"]);

        if ($record_id == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "ID cannot be empty");
        }
        if ($title == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Title cannot be empty");
        }
        
      
        $now = date('Y-m-d H:i:s');
        
        $db->where("id", $record_id);
        $xun_blog_post = $db->getOne("xun_blog_post");
        
        if (!$xun_blog_post) {
            return array('status' => "error", "code" => 1, "statusMsg" => "This record does not exist");
        }
        
        $post_table_tag = explode(",", $xun_blog_post["tag"]);
        $media_type = $xun_blog_post["media_type"];

        if ($media_type == 'article'){
            if ($content == '') {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Content cannot be empty");
            }
            $updateData["content"] = $content;
        }
        else{
            return array('status' => "error", 'code' => 1, 'statusMsg' => "This ID is not Article media type");
        }

        $tag_string = implode(",", $tag);

        $updateData["title"] = $title;
        $updateData["tag"] = $tag_string;
        $updateData["media_url"] = $image_url;
        $updateData["meta_title"] = $meta_title;
        $updateData["meta_description"] = $meta_description;
        $updateData["updated_at"] = $now;
        
        $db->where("id", $record_id);
        $db->update("xun_blog_post", $updateData);
        /*
        $deleteTag = array_diff($post_table_tag, $tag);

        if($deleteTag){
            foreach($deleteTag as $data){
                $db->where("blog_post_id", $record_id);
                $db->where("tag", $data);
                $db->delete('xun_blog_tag'); 
            }
        }
        
        $addTag = array_diff($tag, $post_table_tag);

        if($addTag){
            foreach($addTag as $data){
            $insertTag = array(
                "blog_post_id" => $record_id,
                "tag" => $data,
            );
            
            $db->insert('xun_blog_tag', $insertTag);
        }
    }*/
        
        return array("status" => "ok", "code" => 0, "statusMsg" => "Article changes updated." );
    }

    public function edit_video($params){
        $db = $this->db;
        
        $record_id = trim($params["id"]);
        $title = trim($params["title"]);      
        $tag = $params["tag"];
        $video_url = trim($params["video_url"]);

        if ($record_id == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "ID cannot be empty");
        }
        if ($title == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Title cannot be empty");
        }
        
      
        $now = date('Y-m-d H:i:s');
        
        $db->where("id", $record_id);
        $xun_blog_post = $db->getOne("xun_blog_post");
        
        if (!$xun_blog_post) {
            return array('status' => "error", "code" => 1, "statusMsg" => "This record does not exist");
        }
        
        $post_table_tag = explode(",", $xun_blog_post["tag"]);
        $media_type = $xun_blog_post["media_type"];

        if ($media_type == 'video'){
            if ($video_url == '') {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Video URL cannot be empty");
            }
    
            $updateData["media_url"] = $video_url;
        }
        else{
            return array('status' => "error", 'code' => 1, 'statusMsg' => "This ID is not Video media type");
        }

        $tag_string = implode(",", $tag);

        $updateData["title"] = $title;
        $updateData["tag"] = $tag_string;
        $updateData["meta_title"] = $meta_title;
        $updateData["meta_description"] = $meta_description;
        $updateData["updated_at"] = $now;
        
        $db->where("id", $record_id);
        $db->update("xun_blog_post", $updateData);
       
/*
        $deleteTag = array_diff($post_table_tag, $tag);
        if($deleteTag){

        foreach($deleteTag as $data){
            $db->where("blog_post_id", $record_id);
            $db->where("tag", $data);
            $return = $db->delete('xun_blog_tag');
            
        }
    }
        $addTag = array_diff($tag, $post_table_tag);

        if($addTag){
            foreach($addTag as $data){
                $insertTag = array(
                    "blog_post_id" => $record_id,
                    "tag" => $data,
                );
                
                $db->insert('xun_blog_tag', $insertTag);
            }

        } */
        return array("status" => "ok", "code" => 0, "statusMsg" => "Video changes updated." );

    }

    public function get_news_details($params)
    {
        $db = $this->db;

        $record_id = trim($params["id"]);

        if ($record_id == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "ID cannot be empty");
        }

        $db->where("id", $record_id);
        $xun_blog_post = $db->getOne("xun_blog_post");

        if (!$xun_blog_post) {
            return array('status' => "error", "code" => 1, "statusMsg" => "This record does not exist");
        }
        if($xun_blog_post["status"] === 0){
            return array('status' => "error", "code" => 1, "statusMsg" => "This record does not exist");
        }

        unset($xun_blog_post["status"]);

        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "News details.", 'data' => $xun_blog_post);
    }

    public function get_article_details($params){
        $db = $this->db;

        $record_id = trim($params["id"]);

        if ($record_id == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "ID cannot be empty");
        }

        $db->where("id", $record_id);
        $xun_blog_post = $db->getOne("xun_blog_post");



        if (!$xun_blog_post) {
            return array('status' => "error", "code" => 1, "statusMsg" => "This record does not exist");
        }
        if($xun_blog_post["status"] === 0){
            return array('status' => "error", "code" => 1, "statusMsg" => "This record does not exist");
        }


        $tag = explode(",",$xun_blog_post["tag"]);
        $relatedArticle = array();
        
        
        foreach($tag as $data){
            $data = "%$data%";
            $db->orWhere('tag', $data, 'like');
            $db->where('media_type', "article");
            $db->where('id', $record_id, '!=');  
            
        }
         $db->orderBy("created_at" ,"DESC");

        $result = $db->get ("xun_blog_post", null, "id,media_type,title, tag,media_url,content, meta_title, meta_description, created_at, updated_at");
        
        $relatedArticle [] = $result; 


        $returnData["article_details"] = $xun_blog_post;
        $returnData["related_article"] = $result;

        unset($blog_post["status"]);

        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Article details.", 'data' => $returnData);
    }

    public function get_video_details($params){
        $db = $this->db;

        $record_id = trim($params["id"]);

        if ($record_id == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "ID cannot be empty");
        }

        $db->where("id", $record_id);
        $xun_blog_post = $db->getOne("xun_blog_post", "id, title, media_type, tag, media_url, created_at, updated_at");

        if (!$xun_blog_post) {
            return array('status' => "error", "code" => 1, "statusMsg" => "This record does not exist");
        }
        if($xun_blog_post["status"] === 0){
            return array('status' => "error", "code" => 1, "statusMsg" => "This record does not exist");
        }


        unset($blog_post["status"]);

        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Video details.", 'data' => $xun_blog_post);


    }
    
    public function get_news_listing($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;
        $post    = $this->post;

        $admin_page_limit      = $setting->getAdminPageLimit();
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order                 = $params["order"];
        $from_datetime    = $params["from_datetime"];
        $to_datetime      = $params["to_datetime"];


        if ($from_datetime) {
            $from_datetime  = date("Y-m-d H:i:s", $from_datetime);
            $db->where("created_at", $from_datetime, '>=');
        }
        
        if ($to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $db->where("created_at", $to_datetime, '<=');
        }


        if ($page_number < 1) {
            $page_number = 1;
        }

        $db->where("status", 1);
        $db->where("type", "news_update");

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        $copyDb = $db->copy();
        $db->orderBy("id", $order);

        $result = $db->get("xun_blog_post", $limit, 'id, title, created_at, content_type');

        if(!$result) return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
        //totalPage, pageNumber, totalRecord, numRecord
        $totalRecord = $copyDb->getValue("xun_blog_post", "count(id)");
        
        $returnData["data"] = $result;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "News listing.", 'data' => $returnData);
    }

    public function get_article_listing($params){
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;
        $post    = $this->post;

        $admin_page_limit      = $setting->getAdminPageLimit();
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order                 = $params["order"];
        $from_datetime    = $params["from_datetime"];
        $to_datetime      = $params["to_datetime"];
        $title = $params["title"];
        $tag = $params["tag"];

        if ($from_datetime) {
            $from_datetime  = date("Y-m-d H:i:s", $from_datetime);
            $db->where("created_at", $from_datetime, '>=');
        }
        
        if ($to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $db->where("created_at", $to_datetime, '<=');
        }

        if($title) {
            $title = "%$title%";
            $db->where("title",$title, 'like');
        }

        if($tag) {
            $tag = "%$tag%";
            $db->where("tag",$tag, 'like');
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $db->where("status", 1);
        $db->where("media_type", "article");

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        $copyDb = $db->copy();
        $db->orderBy("id", $order);

        $result = $db->get("xun_blog_post", $limit, 'id, title, media_type, tag, created_at, updated_at');

        if(!$result) return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
        //totalPage, pageNumber, totalRecord, numRecord
        $totalRecord = $copyDb->getValue("xun_blog_post", "count(id)");
        
        $returnData["data"] = $result;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Article listing.", 'data' => $returnData);

    }

    public function get_video_listing($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;
        $post    = $this->post;

        $admin_page_limit      = $setting->getAdminPageLimit();
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order                 = $params["order"];
        $from_datetime    = $params["from_datetime"];
        $to_datetime      = $params["to_datetime"];
        $title = $params["title"];
        $tag = $params["tag"];

        if ($from_datetime) {
            $from_datetime  = date("Y-m-d H:i:s", $from_datetime);
            $db->where("created_at", $from_datetime, '>=');
        }
        
        if ($to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $db->where("created_at", $to_datetime, '<=');
        }

        if($title) {
            $title = "%$title%";
            $db->where("title",$title, 'like');
        }

        if($tag) {
            $tag = "%$tag%";
            $db->where("tag",$tag, 'like');
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $db->where("status", 1);
        $db->where("media_type", "video");

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        $copyDb = $db->copy();
        $db->orderBy("id", $order);

        $result = $db->get("xun_blog_post", $limit, 'id, title,  media_type, tag,created_at,updated_at');

        if(!$result) return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
        //totalPage, pageNumber, totalRecord, numRecord
        $totalRecord = $copyDb->getValue("xun_blog_post", "count(id)");
        
        $returnData["data"] = $result;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Video listing.", 'data' => $returnData);
   
    }
  
    public function delete_news($params){
        $db = $this->db;

        $record_id = $params["id"];

        if ($record_id == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "ID cannot be empty");
        }

        if(!is_array($record_id)){
            $record_id = [trim($record_id)];
        }

        $now = date('Y-m-d H:i:s');
        
        $updateData = [];
        $updateData["status"] = 0;
        $updateData["updated_at"] = $now;

        foreach ($record_id as $id){
            $db->where("id", $id);
            $db->update("xun_blog_post", $updateData);
        }

        return array('status' => "ok", "code" => 0, "statusMsg" => "News successfully deleted.");
    }

    public function delete_article($params){
        $db = $this->db;

        $record_id = $params["id"];

        if ($record_id == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "ID cannot be empty");
        }

        if(!is_array($record_id)){
            $record_id = [trim($record_id)];
        }

        $now = date('Y-m-d H:i:s');
        
        $updateData = [];
        $updateData["status"] = 0;
        $updateData["updated_at"] = $now;

        
        foreach ($record_id as $id){
            $db->where("id", $id);
            $db->update("xun_blog_post", $updateData);

            //$db->where("blog_post_id", $id);
            //$db->delete("xun_blog_tag");
        }

        return array('status' => "ok", "code" => 0, "statusMsg" => "Article successfully deleted.");
    }

    public function delete_video($params){
        $db = $this->db;

        $record_id = $params["id"];

        if ($record_id == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "ID cannot be empty");
        }

        if(!is_array($record_id)){
            $record_id = [trim($record_id)];
        }

        $now = date('Y-m-d H:i:s');
        
        $updateData = [];
        $updateData["status"] = 0;
        $updateData["updated_at"] = $now;

        foreach ($record_id as $id){
            $db->where("id", $id);
            $db->update("xun_blog_post", $updateData);

            //$db->where("blog_post_id", $id);
            //$db->delete("xun_blog_tag");
        }

        return array('status' => "ok", "code" => 0, "statusMsg" => "Video successfully deleted.");
    }

    

    public function get_blog_image_presign_url($params){
        global $xunAws;

        $setting = $this->setting;

        $file_name = trim($params["file_name"]);
        $content_type = trim($params["content_type"]);
        $content_size = trim($params["content_size"]);

        $bucket = $setting->systemSetting["awsS3ImageBucket"];
        $s3_folder = 'blog';
        $timestamp = time();
        $presigned_url_key = $s3_folder . '/' . $timestamp . '/' . $file_name;
        $expiration = '+20 minutes';
        
        $newParams = array(
            "s3_bucket" => $bucket,
            "s3_file_key" => $presigned_url_key,
            "content_type" => $content_type,
            "content_size" => $content_size,
            "expiration" => $expiration
        );

        $result = $xunAws->generate_put_presign_url($newParams);
        
        if(isset($result["error"])){
            return array("code" => 1, "status" => "error", "statusMsg" => "Error generating AWS S3 presigned URL.", "errorMsg" => $result["error"]);
        }
        
        $return_message = "AWS presigned url.";
        return array("code" => 0, "status" => "ok", "statusMsg" => $return_message, "data" => $result);

    }

    public function save_download_link_tracking($params){
        global $xunXmpp;
        $db = $this->db;
        $general = $this->general;
        
        $ip = trim($params["ip"]);
        $device = trim($params["device"]);
        $os = trim($params["os"]);
        $url = trim($params["url"]);
        $content = trim($params["content"]);
        $country = trim($params["country"]);
        $tracking_site = $params["tracking_site"] ? trim($params["tracking_site"]) : 'thenux';
        $grouping = $tracking_site == 'nuxpay' ? 'thenux_pay' : '';

        if ($ip == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "IP cannot be empty");
        }
        if ($device == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "device cannot be empty");
        }
        if ($os == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "os cannot be empty");
        }
        if ($url == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "url cannot be empty");
        }
        if ($content == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "content cannot be empty");
        }
        if ($country == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "country cannot be empty");
        }

        $date = date("Y-m-d H:i:s");

        $insertData = array(
            "ip" => $ip,
            "device" => $device,
            "os" => $os,
            "url" => $url,
            "content" => $content,
            "country" => $country,
            "tracking_site" => $tracking_site,
            "created_at" => $date
        );

        $db->insert("xun_download_link_tracking", $insertData);
        
        $message = "IP: " . $ip . "\n";
        $message .= "Country: " . $country . "\n";
        $message .= "Device: " . $device . "\n";
        $message .= "OS: " . $os . "\n";
        $message .= "URL: " . $url . "\n";
        $message .= "Content: " . $content . "\n";
        $message .= "\nTime: " . date("Y-m-d H:i:s");

        if($tracking_site == 'nuxpay'){
            $erlang_params["tag"] = "Link Tracking";
        }
        else{
            $erlang_params["tag"] = "Marketing tracking for Download link";
        }
       
        $thenux_params["message"] = $message;
        $thenux_params["mobile_list"] = array();
        $thenux_result = $general->send_thenux_notification($thenux_params, $grouping);

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "Success");
    }

    public function adminGetUserList($params){
        global $xunTree;
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        // $account_created_at = trim($params["account_created_at"]);
        $username = trim($params["username"]);
        $nickname = trim($params["nickname"]);
        $master_dealer_username = trim($params["master_dealer_username"]);
        $referrer_username = trim($params["referrer_username"]);
        $export = trim($params["seeAll"]);

        $admin_page_limit = $setting->getAdminPageLimit();
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order = $params["order"];
        $from_datetime = $params["account_from_datetime"];
        $to_datetime = $params["account_to_datetime"];

        $failed_return =  array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");

        if($username){
            $db->where("a.username", "%$username%", "LIKE");
        }

        if($nickname){
            $db->where("a.nickname", "%$nickname%", "LIKE");
        }

        if($master_dealer_username){
            $db->where("username", $master_dealer_username);
            $master_dealer_user_id = $db->getValue("xun_user", "id");

            if($master_dealer_user_id == ''){
                return $failed_return;
            }

            $master_dealer_tree = $xunTree->getSponsorUplineAndMasterUplineByUserID($master_dealer_user_id);

            if ($master_dealer_tree && $master_dealer_tree["master_upline"]) {
                $db->where("b.trace_key", "0/$master_dealer_user_id/%", "LIKE");
            }else{
                // return
                return $failed_return;
            }
        }

        if($referrer_username){
            if($referrer_username == $master_dealer_username){
                $referrer_user_id = $master_dealer_user_id;
            }else{
                $db->where("username", $referrer_username);
                $referrer_user_id = $db->getValue("xun_user", "id");

                if($referrer_user_id == ''){
                    return $failed_return;
                }
            }

            $db->where("b.upline_id", $referrer_user_id);
        }

        if ($from_datetime) {
            $from_datetime  = date("Y-m-d H:i:s", $from_datetime);
            $db->where("a.created_at", $from_datetime, '>=');
        }
        
        if ($to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $db->where("a.created_at", $to_datetime, '<=');
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $db->where("type", "user");
        $db->where("disabled", 0);

        if($export == 1 || $export == "true"){
            $limit = null;
            $export = 1;
        }else{
            $export = 0;
            $start_limit = ($page_number - 1) * $page_size;
            $limit       = array($start_limit, $page_size);
        }

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        $db->join('xun_tree_referral b', 'a.id=b.user_id', 'LEFT');

        $copyDb = $db->copy();
        $db->orderBy("a.id", $order);

        $copyDb = $db->copy();

        $result = $db->get("xun_user a", $limit, "a.id, a.username, a.nickname, a.created_at, b.upline_id, b.trace_key");

        if(!$result) return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
        //totalPage, pageNumber, totalRecord, numRecord

        $result_len = count($result);

        $upline_user_id_arr = [];
        $tree_master_user_id_arr = [];
        $user_id_arr = [];
        $username_arr = [];

        for ($i = 0; $i<$result_len; $i++){
            $record = $result[$i];
            $user_id_arr[] = $record["id"];
            $username_arr[] = $record["username"];
            $trace_key = $record["trace_key"];
            
            if($trace_key){
                $upline_id = $record["upline_id"];
                $tree_master_user_id = explode("/", $trace_key)[1];
                
                if(!in_array($upline_id, $upline_user_id_arr) && $upline_id !== 0){
                    $upline_user_id_arr[] = $upline_id;
                }

                if(!in_array($tree_master_user_id, $upline_user_id_arr)){
                    $upline_user_id_arr[] = $tree_master_user_id;

                }

                if(!in_array($tree_master_user_id, $tree_master_user_id_arr)){
                    $tree_master_user_id_arr[] = $tree_master_user_id;
                }

                $record["tree_master_user_id"] = $tree_master_user_id;
            }

            $record["country"] = '';
            $record["device"] = '';
            $record["match"] = '';
            $record["last_login_ip"] = '';
            $result[$i] = $record;
        }

        $db->where("user_id", $user_id_arr, "in");
        $db->where("active", 1);
        $db->where("address_type", "personal");
        $wallet_created_at_arr = $db->map("user_id")->ObjectBuilder()->get("xun_crypto_user_address", null, "id, user_id, created_at");

        $db->where("mobile_number", $username_arr, "in");
        $user_device_arr = $db->map("mobile_number")->ObjectBuilder()->get("xun_user_device", null, "id, mobile_number, os, app_version");

        $db->where("user_id", $user_id_arr, "in");
        $db->where("name", ["ipCountry", "lastLoginIP"], "in");
        $user_ip_arr = $db->map("user_id")->ObjectBuilder()->get("xun_user_setting", null, "user_id, name, value");

        $db->where("user_id", $user_id_arr, "in");
        $db->where("name", ["ipCountry", "lastLoginIP"], "in");
        $user_ip_arr = $db->get("xun_user_setting", null, "user_id, name, value");

        $new_user_ip_arr = [];
        for ($i = 0; $i < count($user_ip_arr); $i++){
            $user_ip_data = $user_ip_arr[$i];
            $user_ip_name = $user_ip_data["name"];
            $user_ip_value = $user_ip_data["value"];
            $new_user_ip_arr[$user_ip_data["user_id"]][$user_ip_name] = $user_ip_value;
        }
        $upline_user_data_arr = [];
        $tree_master_data_arr = [];
        
        // SELECT * FROM `xun_user_verification` where id in (SELECT MAX(id) from xun_user_verification where mobile in (') and verify_at is not null and is_verified = 1 and status = 'success' GROUP by mobile) 
        $sq = $db->subQuery();
        $sq->where("mobile", $username_arr, "in");
        $sq->where ("verify_at", NULL, 'IS NOT');
        $sq->where ("is_verified", '1');
        $sq->where ("status", 'success');
        $sq->groupBy("mobile");
        $sq->get("xun_user_verification", null, "max(id)");
        
        $db->where("id", $sq, "in");
        $user_verification_arr = $db->map("mobile")->ObjectBuilder()->get("xun_user_verification", null, "id, mobile, `match`, user_type");

        if(count($upline_user_id_arr) > 0){
            $db->where("id", $upline_user_id_arr, "in");
            $upline_user_data_arr = $db->map("id")->ObjectBuilder()->get("xun_user", null, "id, username, nickname, disabled");
            $db->where("user_id", $tree_master_user_id_arr, "in");
            $tree_master_data_arr = $db->map("user_id")->ObjectBuilder()->get("xun_tree_referral", null, "id, user_id, master_upline");
        }

        for ($i = 0; $i<$result_len; $i++){
            $record = $result[$i];
            $user_id = $record["id"];
            $record_username = $record["username"];
            $tree_master_user_id = $record["tree_master_user_id"];
            
            $user_referrer_username = '';
            $user_referrer_nickname = '';
            $user_master_dealer_username = '';
            $user_master_dealer_nickname = '';

            if($tree_master_user_id){
                $upline_id = $record["upline_id"];
                $upline_user_data = $upline_user_data_arr[$upline_id];
                $user_referrer_username = $upline_user_data->username;
                $user_referrer_nickname = $upline_user_data->nickname;

                $tree_master_user_id = $record["tree_master_user_id"];
                $tree_master_data = $tree_master_data_arr[$tree_master_user_id];

                if($tree_master_data->master_upline == 1){
                    $master_dealer_user_data = $upline_user_data_arr[$tree_master_user_id];
                    $user_master_dealer_username = $master_dealer_user_data->username;
                    $user_master_dealer_nickname = $master_dealer_user_data->nickname;
                }
            }
            $record["referrer_username"] = $user_referrer_username ? $user_referrer_username : '';
            $record["referrer_nickname"] = $user_referrer_nickname ? $user_referrer_nickname : '';
            $record["master_dealer_username"] = $user_master_dealer_username ? $user_master_dealer_username : '';
            $record["master_dealer_nickname"] = $user_master_dealer_nickname ? $user_master_dealer_nickname : '';
            unset($record["trace_key"]);
            unset($record["upline_id"]);
            unset($record["tree_master_user_id"]);

            $user_wallet = $wallet_created_at_arr[$user_id];
            $user_wallet_created_at = $user_wallet ? $user_wallet->created_at : '';
            $record["wallet_created_at"] = $user_wallet_created_at;

            $user_device_data = $user_device_arr[$record_username];
            $user_device = $user_device_data ? ($user_device_data->os == "1" ? "Android" : "iOS") : "";
            $user_app_version = $user_device_data ? ($user_device_data->app_version ? $user_device_data->app_version : "") : "";

            $user_ip_data = $new_user_ip_arr[$user_id];
            if($user_ip_data){
                $last_login_ip = $user_ip_data["lastLoginIP"];
                $country = $user_ip_data["ipCountry"];
            }
            $user_verification_data = $user_verification_arr[$record_username];
            if($user_verification_data){
                $match = $user_verification_data->match;
                $user_type = $user_verification_data->user_type;
            }

            $record["device"] = $user_device;
            $record["app_version"] = $user_app_version;
            $record["country"] = $country ? $country : "";
            $record["last_login_ip"] = $last_login_ip ? $last_login_ip : "";
            $record["match"] = $match ? $match : "No";
            $record["return_type"] = $user_type ? $user_type : "Return";
            $result[$i] = $record;
        }


        $totalRecord = $copyDb->getValue("xun_user a", "count(a.id)");
        
        $returnData["data"] = $result;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $export ? $totalRecord : $page_size;
        $returnData["totalPage"] = $export ? 1 : ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "User listing.", 'data' => $returnData);
    }

    public function get_user_kyc_listing($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $username = trim($params["username"]);
        $nickname = trim($params["nickname"]);
        $country = trim($params["country"]);
        $status = trim($params["status"]);
        $first_name = trim($params["first_name"]);
        $last_name = trim($params["last_name"]);
        $document_id = trim($params["document_id"]);
        $document_type = trim($params["document_type"]);
        $risk_level = trim($params["risk_level"]);
        $submitted_from_datetime = trim($params["submitted_from_datetime"]);
        $submitted_to_datetime = trim($params["submitted_to_datetime"]);
        $updated_from_datetime = trim($params["updated_from_datetime"]);
        $updated_to_datetime = trim($params["updated_to_datetime"]);

        $admin_page_limit = $setting->getAdminPageLimit();
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order = $params["order"];

        $failed_return =  array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");

        if($username){
            $db->where("b.username", "%$username%", "LIKE");
        }

        if($nickname){
            $db->where("b.nickname", "%$nickname%", "LIKE");
        }

        if($country){
            $db->where("a.country", "%$country%", "LIKE");
        }

        if($status){
            $db->where("a.status", "%$status%", "LIKE");
        }

        if($first_name){
            $db->where("a.given_name", "%$first_name%", "LIKE");
        }

        if($last_name){
            $db->where("a.surname", "%$last_name%", "LIKE");
        }

        if($document_id){
            $db->where("a.document_id", "%$document_id%", "LIKE");
        }

        if($document_type){
            $db->where("a.document_type", "%$document_type%", "LIKE");
        }

        if($risk_level){
            $db->where("a.risk_$risk_level", "%$risk_level%", "LIKE");
        }

        if ($submitted_from_datetime) {
            $from_datetime  = date("Y-m-d H:i:s", $submitted_from_datetime);
            $db->where("a.created_at", $from_datetime, '>=');
        }
        
        if ($submitted_to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $submitted_to_datetime);
            $db->where("a.created_at", $to_datetime, '<=');
        }

        if ($updated_from_datetime) {
            $from_datetime  = date("Y-m-d H:i:s", $updated_from_datetime);
            $db->where("a.updated_at", $from_datetime, '>=');
        }
        
        if ($updated_to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $updated_to_datetime);
            $db->where("a.updated_at", $to_datetime, '<=');
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $db->where("b.type", "user");
        // $db->where("disabled", 0);

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        $db->join('xun_user b', 'a.user_id=b.id', 'LEFT');

        $copyDb = $db->copy();
        $db->orderBy("a.id", $order);

        $copyDb = $db->copy();

        $result = $db->get("xun_kyc a", $limit, "a.id, a.user_id, a.country, a.given_name as first_name, a.surname as last_name, LOWER(a.document_type) as document_type, a.document_id, a.s3_link, a.status, a.risk_level, a.created_at, a.updated_at, b.username, b.nickname");

        if(!$result) return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
        //totalPage, pageNumber, totalRecord, numRecord

        $totalRecord = $copyDb->getValue("xun_kyc a", "count(a.id)");
        
        $returnData["data"] = $result;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "KYC listing.", 'data' => $returnData);
    }

    private function get_employee_old_id($business_id, $mobile){
        $new_mobile = str_replace("+", "", $mobile);

        $employee_id = $business_id . "_" . $new_mobile;
        return $employee_id;
    }

    private function send_xmpp_business_tag_event($business_id, $tag, $new_employee_list, $removed_employee_list, $subscriber_jid_list) {
        global $xunXmpp;

        $erlangReturn = $xunXmpp->send_xmpp_business_tag_event($business_id, $tag, $new_employee_list, $removed_employee_list, $subscriber_jid_list);
        
        return $erlangReturn;
    }

    public function send_business_update_profile_message($business_id, $event_type)
    {
        global $xunXmpp;
        $erlangReturn = $xunXmpp->send_business_update_profile_message($business_id, $event_type);

        return $erlangReturn;
    }

    public function send_business_message($params)
    {
        global $xunBusiness;
        $url_string = "business/broadcast";
        $res = $xunBusiness->business_message_sending($url_string, $params);
        return $res;
    }

    public function send_business_employee_message($params)
    {
        global $xunBusiness;
        $res = $xunBusiness->business_send_employee_message($params);
        return $res;
    }


    public function get_dispute_listing($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;
        $post    = $this->post;

        $page_number         = $params['page'] ? $params['page'] : 1;
        $admin_page_limit   = $setting->getAdminPageLimit();
        $page_size          = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $from_datetime      = $params["from_datetime"];
        $to_datetime        = $params["to_datetime"];
        $reportBy           = trim($params['reportBy']);
        $mobile_number      = trim($params['mobile_number']);
        $status             = trim($params['status']);

        if($reportBy || $mobile_number){
            if ($reportBy) {
                $db->where('nickname', "%$reportBy%", 'LIKE');
            }
    
            if ($mobile_number){
                $db->where('username', "%$mobile_number%", 'LIKE');
            }
            $xun_user = $db->get('xun_user', null, 'id, nickname, type');
            if (!$xun_user){
                return array('status' => "ok", 'code' => 1, 'statusMsg' => "No Results Found", 'data' => "");
            }

            $user_id = array_column($xun_user, 'id');
            $user_id_arr = implode(",", $user_id);

            $db->where("(user_id in (" . $user_id_arr .  ") or wallet_user_id in (". $user_id_arr ."))");
        }

        
        if ($from_datetime) {
            $from_datetime = date("Y-m-d H:i:s", $from_datetime);
            $db->where("created_at", $from_datetime, '>=');
        }
        
        if ($to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $db->where("created_at", $to_datetime, '<=');
        }

        if($status){
            $db->where('status', $status, 'LIKE');
        }


        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $db->orderBy("created_at", "DESC");
        $copyDb = $db->copy();

        if ($params['pagination'] == "No") {
            $xun_escrow_report = $db->get('xun_escrow_report');
        } else {
            $xun_escrow_report = $db->get('xun_escrow_report', $limit);
        }

        if (!empty($xun_escrow_report)) {
            $escrow_report_user_id = array_column($xun_escrow_report, 'user_id');

            $db->where('id', $escrow_report_user_id, "IN");
            $informer_info = $db->map("id")->ObjectBuilder()->get("xun_user", null, "id, username, nickname, type");

            foreach($xun_escrow_report as $escrow_report){
                $report_user_id = $escrow_report['user_id'];
                $wallet_user_id = $escrow_report['wallet_user_id'];

                $informer_obj = $informer_info[$report_user_id];
                $informer_business_obj = $informer_info[$wallet_user_id];

                $result['name'] = $informer_obj->nickname;
                $result['mobile_number'] = $informer_obj->username;
                if($wallet_user_id != $report_user_id){
                    $result['business_name'] = $informer_business_obj->nickname;
                }else
                    $result['business_name'] = '';

                if($escrow_report['transaction_type'] == 1){
                    $result['report_by'] = 'Sender';
                }else if ($escrow_report['transaction_type'] == 2){
                    $result['report_by'] = 'Recipient';
                }

                $result['id'] = $escrow_report['id'];
                $result['ticket_no'] = $escrow_report['id'];
                $result['description'] = $escrow_report['reason'];
                $result['status'] = $escrow_report['status'];
                $result['created_at'] = $escrow_report['created_at'];
                $result['updated_at'] = $escrow_report['updated_at'];
                $data[] = $result;
            }
            $totalRecords = $copyDb->getValue("xun_escrow_report", "count(id)");

            $returnData["result"]       = $data;
            $returnData['pageNumber']   = $page_number;
            $returnData['totalRecord']  = $totalRecords;
            $returnData["numRecord"]    = $page_size;
            $returnData["totalPage"]    = ceil($totalRecords/$page_size);

            return array('status' => "ok", 'code' => 1, 'statusMsg' => '', 'data' => $returnData);
        } else {
            return array('status' => "ok", 'code' => 1, 'statusMsg' => "No Results Found", 'data' => "");
        }
    }

    public function get_specific_dispute_details ($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;
        $post    = $this->post;

        $escrow_report_id   = trim($params['id']);
        if (!$escrow_report_id)
            return array('status' => 'error', 'code' => "1", 'statusMsg' => "Invalid Dispute", 'data' => "");

        $db->where('id', $escrow_report_id);
        $escrow_report = $db->getOne('xun_escrow_report');

        if (!empty($escrow_report)){
            $report_date = $escrow_report['created_at'];
            $result["report_date"] = date("Y-m-d", strtotime($report_date));
            $result['description'] = $escrow_report['reason'];

            $wallet_transaction_id = $escrow_report['wallet_transaction_id'];
            $db->where('id', $wallet_transaction_id);
            $wallet_transaction = $db->getOne('xun_wallet_transaction');

            $result['date_of_transaction'] = date("Y-m-d", strtotime($wallet_transaction['created_at']));
            $result['transaction_amount'] = $wallet_transaction['amount'];
            $result['transaction_currency'] = $wallet_transaction['wallet_type'];

            $xun_user_service = new XunUserService($db);

            $sender_address = $wallet_transaction['sender_address'];
            $recipient_address = $wallet_transaction['recipient_address'];
            $address_user_arr = $xun_user_service->getAddressAndUserDetailsByAddressList([$sender_address, $recipient_address]);

            $sender_data = $address_user_arr[$sender_address];
            $recipient_data = $address_user_arr[$recipient_address];
            
            $result['sender_phone'] = $sender_data['username'];
            $result['sender_name'] = $sender_data['nickname'];
            $result['sender_type'] = $sender_data['type'];

            $result['recipient_phone'] = $recipient_data['username'];
            $result['recipient_name'] = $recipient_data['nickname'];
            $result['recipient_type'] = $recipient_data['type'];

            $informer_id = $escrow_report['user_id'];
            $db->where('id', $informer_id);
            $reportBy = $db->getValue('xun_user', 'nickname');

            $result['report_by'] = $reportBy;

            $data['result'] = $result;
            return array('status' => 'ok', 'code' => "0", 'statusMsg' => "", 'data' => $data);
        }else{
            return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found.", 'data' => "");
        }
    }

    public function specific_dispute_details_action ($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;
        $post    = $this->post;

        $escrow_report_id = trim($params['id']);
        $admin_username = trim($params['username']);
        $admin_password = trim($params['password']);
        $action = trim($params['action']);

        if (!$escrow_report_id)
            return array('status' => 'error', 'code' => "1", 'statusMsg' => "Invalid Escrow Report", 'data' => "");

        if (!$admin_username)
            return array('status' => 'error', 'code' => "1", 'statusMsg' => "Invalid Username", 'data' => "");

        if (!$admin_password)
            return array('status' => 'error', 'code' => "1", 'statusMsg' => "Please enter your password", 'data' => "");

        if (!$action)
            return array('status' => 'error', 'code' => "1", 'statusMsg' => "Invalid Action Performed", 'data' => "");
            
        $passwordEncryption = $setting->getMemberPasswordEncryption();

        if (!empty($admin_username)) {
            $db->where("username", $admin_username);
            if($passwordEncryption == "bcrypt") {
                // Bcrypt encryption
                // Hash can only be checked from the raw values
            }
            else if ($passwordEncryption == "mysql") {
                // Mysql DB encryption
                $db->where('password', $db->encrypt($admin_password));
            }
            else {
                // No encryption
                $db->where('password', $admin_password);
            }
        }
        else {
            return array('status' => 'error', 'code' => 1, 'statusMsg' => "Invalid Admin");
        }

        $admin_result = $db->getOne('admin');

        if (!empty($admin_result)) {
            if($passwordEncryption == "bcrypt") {
                // We need to verify hash password by using this function
                if(!password_verify($admin_password, $admin_result['password']))
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => "Invalid Password Entered", 'data' => $data);
            }

            if($admin_result['disabled'] == 1) {
                // Return error if account is disabled
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00182"][$language] /* Your account is disabled. */, 'data' => '');
            }

            if (!empty($action) && !empty($escrow_report_id)){
                $db->where('id', $escrow_report_id);
                $escrow_report = $db->getOne('xun_escrow_report');
                if (!$escrow_report){
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => "Invalid Escrow Dispute", 'data' => '');
                }

                $escrow_report_status = strtolower($escrow_report["status"]);

                if(in_array($escrow_report_status, ["approved", "declined"])){
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => "This dispute has been approved or declined.", 'data' => '');
                }

                $action = strtolower($action);

                if($action == "approved"){
                    $updateData["status"]       = ucfirst($action);
                    $updateData["updated_at"]   = date('Y-m-d H:i:s');
                }else if($action == "declined"){
                    $updateData["status"]       = ucfirst($action);
                    $updateData["updated_at"]   = date('Y-m-d H:i:s');
                }else{
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => "Invalid Action Performed");
                }
                $db->where("id", $escrow_report_id);
                $db->update("xun_escrow_report", $updateData);

                
                //  perform refund
                //  call wallet server
                $transaction_type = $escrow_report["transaction_type"];
                // 1 - sender, 2 - recipient
                if($action == "approved"){
                    $refund_to = $transaction_type == "1" ? "buyer" : "seller";
                    $other_party_status = "declined";
                }else{
                    $refund_to = $transaction_type == "1" ? "seller" : "buyer";
                    $other_party_status = "approved";
                }
                
                $wallet_transaction_id = $escrow_report['wallet_transaction_id'];
                $db->where("wallet_transaction_id", $wallet_transaction_id);
                $db->where("id", $escrow_report_id, "!=");
                $transaction_escrow_report = $db->getOne("xun_escrow_report");
                if(!empty($transaction_escrow_report)){
                    $update_data["status"] = ucfirst($other_party_status);
                    $update_data["updated_at"] = date('Y-m-d H:i:s');
                    $db->where("id", $transaction_escrow_report["id"]);
                    $db->update("xun_escrow_report", $update_data);
                }

                $db->where('id', $wallet_transaction_id);
                $wallet_transaction = $db->getOne('xun_wallet_transaction');

                if(empty($wallet_transaction)){
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => "Unable to retrieve transaction.");
                }

                $escrow_params = array(
                    "refundTo" => $refund_to,
                    "senderAddress" => $wallet_transaction["sender_address"],
                    "receiverAddress" => $wallet_transaction["recipient_address"],
                    "amount" => $wallet_transaction["amount"],
                    "walletType" => $wallet_transaction["wallet_type"],
                    "escrowContractAddress" => $wallet_transaction["escrow_contract_address"],
                    "walletTransactionID" => $wallet_transaction_id,
                    "referenceID" => $wallet_transaction["reference_id"]
                );

                $xun_company_wallet = new XunCompanyWallet($db, $setting, $post);
                $curlParams = $xun_company_wallet->escrowDecision($escrow_params);

                return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Escrow Dispute " . $action . " Successfully");
            }else{
                return array('status' => 'error', 'code' => 1, 'statusMsg' => "Unable perform action on the escrow dispute");
            }

        }else
            return array('status' => 'error', 'code' => 1, 'statusMsg' => "Invalid Account");        
 
    }

    public function get_specific_escrow_inbox ($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;
        $post    = $this->post;

        $page_number        = $params['page'] ? $params['page'] : 1;
        $admin_page_limit   = $setting->getAdminPageLimit();
        $page_size          = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $escrow_report_id   = $params['id'];

        if (!$escrow_report_id)
            return array('status' => "error", 'code' => 0, 'statusMsg' => "Invalid Escrow Inbox", 'data' => "");
        
        $db->where('id', $escrow_report_id);
        $wallet_transaction_id = $db->getValue('xun_escrow_report', 'wallet_transaction_id');

        $db->where('id', $wallet_transaction_id);
        $transaction_hash = $db->getValue('xun_wallet_transaction', 'transaction_hash');

        $db->where('peer', $transaction_hash.'@crypto.dev.xun.global');

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $db->orderBy("created_at", "DESC");
        $copyDb = $db->copy();

        if ($params['pagination'] == "No") {
            $xun_message_archive = $db->get('xun_message_archive');
        } else {
            $xun_message_archive = $db->get('xun_message_archive', $limit);
        }

        if (!empty($xun_message_archive)) {
            $msg_username = array_column($xun_message_archive, 'username');

            $db->where('username', $msg_username, "IN");
            $user_info = $db->map("username")->ObjectBuilder()->get("xun_user", null, "id, username, nickname, type");

            foreach($xun_message_archive as $inbox_info){
                $message_created_time = $inbox_info['created_at'];
                $msg = $inbox_info['txt'];
                $msg_username = $inbox_info['username'];

                $inbox_user_obj = $user_info[$msg_username];

                $result['name'] = $inbox_user_obj->nickname;
                $result['message'] = $msg;
                $result['created_at'] = $inbox_info['created_at'];
                $data[] = $result;
            }
            $totalRecords = $copyDb->getValue("xun_message_archive", "count(id)");

            $returnData["result"]       = $data;
            $returnData['pageNumber']   = $page_number;
            $returnData['totalRecord']  = $totalRecords;
            $returnData["numRecord"]    = $page_size;
            $returnData["totalPage"]    = ceil($totalRecords/$page_size);

            return array('status' => "ok", 'code' => 1, 'statusMsg' => '', 'data' => $returnData);
        } else {
            return array('status' => "ok", 'code' => 1, 'statusMsg' => "No Results Found", 'data' => "");
        }
    }

    public function get_commission_listing($params){
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;
        $post    = $this->post;

        $admin_page_limit      = $setting->getAdminPageLimit();
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order                 = $params["order"];
        $from_datetime         = $params["from_datetime"];
        $to_datetime           = $params["to_datetime"];
        $phone                 = $params["phone"];
        $coin_type             = $params["coin_type"];
        $status                = $params["status"];
        $transaction_hash      = $params["transaction_hash"];

        if ($page_number < 1) {
            $page_number = 1;
        }

        if ($from_datetime) {
            $from_datetime  = date("Y-m-d H:i:s", $from_datetime);
            $db->where("b.created_at", $from_datetime, '>=');
        }
        
        if ($to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $db->where("b.created_at", $to_datetime, '<=');
        }

        if($phone){
            $db->where('a.username', "%$phone%", "LIKE");
        }

        if($coin_type){
            $db->where('b.wallet_type', "%$coin_type%", 'LIKE');
        }

        if($transaction_hash){
            $db->where('b.transaction_hash', "%$transaction_hash%" , 'LIKE');
        }

        if($status){
            $db->where('b.status', $status);
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        $db->where('b.address_type', "service_charge");
        $db->join('xun_user a', 'b.user_id = a.id' , 'LEFT');
        $db->join('xun_service_charge_audit c', 'b.id = c.wallet_transaction_id', 'LEFT');
        $copyDb = $db->copy();
        $db->orderBy("b.id", $order);
        $result = $db->get('xun_wallet_transaction b', $limit, "a.username, a.type, a.nickname as name, b.user_id, b.amount, b.wallet_type, b.status, b.created_at,b.updated_at,b.transaction_hash, c.id, c.ori_tx_amount, c.ori_tx_wallet_type, c.service_charge_type");
        if(!$result) return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
        //totalPage, pageNumber, totalRecord, numRecord

        foreach($result as &$data){
            if ($data['type'] == 'business'){
                $data['username'] = $data['user_id'];
            }

            $tx_status = $data["status"];
            if($tx_status == "completed"){
                $tx_status = "Completed";
            }else if($tx_status == "pending"){
                $tx_status = "Pending";
            }else if($tx_status == "wallet_success"){
                $tx_status = "Pending callback";
            }

            $tx_type = $data["service_charge_type"];
            if($tx_type == "payment_gateway"){
                $tx_type = "Payment Gateway";
            }else if ($tx_type == "external_transfer"){
                $tx_type = "External Transfer";
            }else if ($tx_type == "internal_transfer"){
                $tx_type = "Internal Transfer";
            }else if ($tx_type == "escrow"){
                $tx_type = "Escrow";
            }else if ($tx_type == "bc_external_transfer"){
                $tx_type = "Blockchain external transfer";
            }

            $data["status"] = $tx_status;
            $data["service_charge_type"] = $tx_type;
        }
        
      
        $totalRecord = $copyDb->getValue("xun_wallet_transaction b", "count(b.id)");
       
        $returnData["data"] = $result;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;

        return array('status' => 'ok', 'code' => "0", 'statusMsg' => "", 'data' => $returnData);

    }

    public function subcribe_crowdfunding($params){
        global $xunXmpp;
        $db = $this->db;

        $name = trim($params["name"]);
        $phone_number = trim($params["phone_number"]);
        $email = trim($params["email"]);
        $url = trim($params["url"]);
        $ip = trim($params["ip"]);
        $country = trim($params["country"]);
        $device = trim($params["device"]);

        if($name == ''){
            return array("message" => "FAILED", 'code' => 0, 'message_d' => "Name cannot be empty");
        }

        if($phone_number == ''){
            return array("message" => "FAILED", 'code' => 0, 'message_d' => "Phone number cannot be empty");
        }

        if($email == ""){
            return array("message" => "FAILED", 'code' => 0, 'message_d' => "Email cannot be empty");
        }
        
        if($url == ""){
            return array("message" => "FAILED", 'code' => 0, 'message_d' => "URL cannot be empty");
        }

        if($ip == ""){
            return array("message" => "FAILED", 'code' => 0, 'message_d' => "IP cannot be empty");
        }

        if($country == ""){
            return array("message" => "FAILED", 'code' => 0, 'message_d' => "Country cannot be empty");
        }

        if($device == ""){
            return array("message" => "FAILED", 'code' => 0, 'message_d' => "Device cannot be empty");
        }

        $insert_data = array(
            "name" => $name,
            "phone_number" => $phone_number,
            "email" => $email,
            "url" => $url,
            "ip" => $ip,
            "country" => $country,
            "device" => $device
        );

        $row_id = $db->insert("xun_crowdfunding", $insert_data);

        $message = "Name: " . $name . "\n";
        $message .= "Phone Number: " . $phone_number . "\n";
        $message .= "Email: " . $Email . "\n";
        $message .= "URL: " . $url . "\n";
        $message .= "IP: " . $ip . "\n";
        $message .= "Country: " . $country . "\n";
        $message .= "Device: " . $device . "\n";
        $message .= "ID: " . $row_id . "\n";
        $message .= "\nTime: " . date("Y-m-d H:i:s");

        $erlang_params["tag"] = "Crowdfunding Subscription";
        $erlang_params["message"] = $message;
        $erlang_params["mobile_list"] = array();
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Success");
    }

    public function adminGetCrowdfundingListing($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $admin_page_limit = $setting->getAdminPageLimit();
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order = $params["order"];
        $name = trim($params["name"]);
        $phone_number = trim($params["phone_number"]);
        $email = trim($params["email"]);
        $url = trim($params["url"]);
        $ip = trim($params["ip"]);
        $country = trim($params["country"]);
        $device = trim($params["device"]);
        $from_timestamp = trim($params["from_datetime"]);
        $to_timestamp = trim($params["to_datetime"]);

        if ($name) {
            $db->where("name", "%$name%", "LIKE");
        }

        if ($phone_number) {
            $db->where("phone_number", "%$phone_number%", "LIKE");
        }

        if ($email) {
            $db->where("email", "%$email%", "LIKE");
        }

        if ($url) {
            $db->where("url", "%$url%", "LIKE");
        }

        if ($country) {
            $db->where("country", "%$country%", "LIKE");
        }

        if ($device) {
            $db->where("device", "%$device%", "LIKE");
        }

        if ($ip) {
            $db->where("ip", "%$ip%", "LIKE");
        }

        if ($from_timestamp) {
            $from_datetime  = date("Y-m-d H:i:s", $from_timestamp);
            $db->where("created_at", $from_datetime, '>=');
        }
        
        if ($to_timestamp) {
            $to_datetime  = date("Y-m-d H:i:s", $to_timestamp);
            $db->where("created_at", $to_datetime, '<=');
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $copyDb = $db->copy();
        $db->orderBy("id", "DESC");
        $result = $db->get("xun_crowdfunding", $limit);

        if(!$result) return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
        //totalPage, pageNumber, totalRecord, numRecord
        $totalRecord = $copyDb->getValue("xun_crowdfunding", "count(id)");
        
        $returnData["data"] = $result;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "", 'data' => $returnData);
    }

    public function getCrowdfundingIDetails($params){
        global $xunCurrency;
        $db = $this->db;
        
        $tnc_rate = $xunCurrency->get_rate("thenuxcoin", "usd");
        $return_data = [];
        $return_data["amount_funded"] = "849559";
        $return_data["goal"] = "10000000";
        $return_data["process_pct"] = "56";
        $return_data["tnc_rate"] = $tnc_rate;
        $return_data["total_funder"] = "10";
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Crowdfunding details.", "data" => $return_data);
    }

    public function adminGetBusinessCommission($params)
    {
        $db      = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $admin_page_limit = $setting->getAdminPageLimit();
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order = $params["order"];
        $business_id = trim($params["business_id"]);
        $transaction_type = trim($params["transaction_type"]);
        $service_charge_type = trim($params["service_charge_type"]);
        $amount = trim($params["amount"]);
        $wallet_type = trim($params["wallet_type"]);
        $status = trim($params["status"]);
        $from_timestamp = trim($params["from_datetime"]);
        $to_timestamp = trim($params["to_datetime"]);
        $transaction_username = trim($params["transaction_username"]);

        if ($business_id == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Business id cannot be empty");
        }
        // select c.*, d.* from xun_php.xun_wallet_transaction d right join (SELECT a.*, b.reference_id FROM xun_php.xun_service_charge_audit a join xun_php.xun_wallet_transaction b on a.wallet_transaction_id = b.id) c on c.reference_id = d.id where d.sender_user_id = 9 or recipient_address = 9;

        if($transaction_username){
            $db->where("username", "%$transaction_username%", "LIKE");
            $user_id_arr = $db->getValue("xun_user", "id", null);
        }
        $sq = $db->subQuery("c");
        $sq->where("a.user_id", "%$business_id%", "LIKE");

        if ($type) {
            $sq->where("a.type", "%$type%", "LIKE");
        }

        if ($amount) {
            $sq->where("a.amount", "%$amount%", "LIKE");
        }

        if ($wallet_type) {
            $sq->where("a.wallet_type", "%$wallet_type%", "LIKE");
        }

        if ($status) {
            $sq->where("a.status", "%$status%", "LIKE");
        }

        if ($transaction_type) {
            $sq->where("a.transaction_type", "%$transaction_type%", "LIKE");
        }

        if ($service_charge_type) {
            $sq->where("a.service_charge_type", "%$service_charge_type%", "LIKE");
        }

        if ($from_timestamp) {
            $from_datetime  = date("Y-m-d H:i:s", $from_timestamp);
            $sq->where("a.created_at", $from_datetime, '>=');
        }
        
        if ($to_timestamp) {
            $to_datetime  = date("Y-m-d H:i:s", $to_timestamp);
            $sq->where("a.created_at", $to_datetime, '<=');
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $sq->join("xun_wallet_transaction b", "a.wallet_transaction_id=b.id", "LEFT");
        $sq->get("xun_service_charge_audit a", null, "a.*, b.reference_id");
        if(!empty($user_id_arr)){
            $db->where("d.sender_user_id", $user_id_arr, "IN");
            $db->orWhere("d.recipient_user_id", $user_id_arr, "IN");
        }
        $db->join($sq, "c.reference_id=d.id", "RIGHT");
        $copyDb = $db->copy();
        $db->orderBy("c.created_at", "DESC");
        $result = $db->get("xun_wallet_transaction d", $limit, "c.*, d.sender_user_id, d.recipient_user_id");

        if(!$result) return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");

        //totalPage, pageNumber, totalRecord, numRecord
        $totalRecord = $copyDb->getValue("xun_wallet_transaction d", "count(*)");
        
        $user_id_arr = [];
        foreach($result as &$data){
            $reference_id = $data["reference_id"];
            if($reference_id != ''){
                $transaction_type = $data["transaction_type"];
                if($transaction_type == "receive"){
                    $transaction_user_id = $data["sender_user_id"];
                }else{
                    $transaction_user_id = $data["recipient_user_id"];
                }

                $user_id_arr[] = $transaction_user_id;
            }

            $tx_status = $data["status"];
            if($tx_status == "completed"){
                $tx_status = "Completed";
            }else if($tx_status == "pending"){
                $tx_status = "Pending";
            }else if($tx_status == "wallet_success"){
                $tx_status = "Pending callback";
            }

            $tx_type = $data["service_charge_type"];
            if($tx_type == "payment_gateway"){
                $tx_type = "Payment Gateway";
            }else if ($tx_type == "external_transfer"){
                $tx_type = "External Transfer";
            }else if ($tx_type == "internal_transfer"){
                $tx_type = "Internal Transfer";
            }else if ($tx_type == "escrow"){
                $tx_type = "Escrow";
            }else if ($tx_type == "bc_external_transfer"){
                $tx_type = "Blockchain external transfer";
            }

            $data["status"] = $tx_status;
            $data["service_charge_type"] = $tx_type;
            $data["transaction_user_id"] = $transaction_user_id;
            unset($transaction_user_id);
        }

        if(!empty($user_id_arr)){
            $db->where("id", $user_id_arr, "IN");
            $xun_user_arr = $db->map("id")->ArrayBuilder()->get("xun_user", null, "id, username, nickname, type");
        }

        foreach($result as &$data){
            $transaction_user_id = $data["transaction_user_id"];

            $transaction_user_data = $xun_user_arr[$transaction_user_id];
            if($transaction_user_data){
                $tx_user_type = $transaction_user_data["type"];
                $tx_username = $transaction_user_data["username"];
                $tx_nickname = $transaction_user_data["nickname"];
            }

            $data["transaction_user_type"] = $tx_user_type;
            $data["transaction_username"] = $tx_username;
            $data["transaction_user_nickname"] = $tx_nickname;

            unset($tx_user_type);
            unset($tx_username);
            unset($tx_nickname);
        }

        $returnData["data"] = $result;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "", 'data' => $returnData);
    }

    public function adminGetCommissionDetails($params){
        $db      = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $id = trim($params["id"]);

        $address_type_arr = ["upline", "master_upline", "company_acc"];
        $db->where("a.reference_id", $id);
        $db->where("a.address_type", $address_type_arr, "IN");
        $db->join("xun_user b", "a.recipient_user_id=b.id", "LEFT");
        $wallet_transaction = $db->get("xun_wallet_transaction a", null, "a.id, a.amount, a.wallet_type, a.transaction_hash, a.status, a.address_type, a.created_at, a.updated_at, b.id as user_id, b.type as user_type, b.nickname, b.username");


        if(!$wallet_transaction){
            return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => []);
        }

        foreach($wallet_transaction as &$data){
            $address_type = $data["address_type"];
            if($address_type == "upline"){
                $address_type = "Referrer";
            }else if($address_type == "master_upline"){
                $address_type = "Master dealer";
            }else if($address_type == "company_pool"){
                $address_type = "Company Pool";
            }else if($address_type == "company_acc"){
                $address_type = "Company Account";
            }

            unset($data["address_type"]);
            $data["recipient_type"] = $address_type;
            $data["status"] = ucfirst($data["status"]);
        }

        $return_data = [];
        $return_data["tx_data"] = $wallet_transaction;

        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Commission Details", 'data' => $return_data);
    }

    public function adminGetStoryListing($params){
        global $xunCurrency;
        $db      = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $admin_page_limit = $setting->getAdminPageLimit();
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order = $params["order"] ? $params["order"] : "DESC";
        $story_id = $params["id"];
        $from_timestamp = trim($params["from_datetime"]);
        $to_timestamp = trim($params["to_datetime"]);
        $title = trim($params["title"]);
        $description = trim($params["description"]);
        $username = trim($params["username"]);
        $status = trim($params["status"]);
        $category_type = trim($params["category_type"]);
        
    
        if($story_id){
            $db->where('b.id', $story_id);
        }
        
        if($title){
            $db->where('a.title', "%$title%", "LIKE");
        }

        if($description){
            $db->where('a.description', "%$description%", "LIKE");
        }

        if($username){
            $db->where('(c.username like ? or c.id = ?)', Array($username, $username));
        }

        if($category_type){
            $db->where('d.category', "%$category_type%", "LIKE");
        }

        if($status){
            $db->where('b.status', "%$status%", "LIKE");
        }

        if ($from_timestamp) {
            $from_datetime  = date("Y-m-d H:i:s", $from_timestamp);
            $db->where("b.created_at", $from_datetime, '>=');
        }
        
        if ($to_timestamp) {
            $to_datetime  = date("Y-m-d H:i:s", $to_timestamp);
            $db->where("b.created_at", $to_datetime, '<=');
        }

        if ($page_number < 1) {
            $page_number = 1;
        }
        
        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $db->where('a.story_type', "story");
        $db->join('xun_story_category d', 'd.id = b.category_id', "LEFT");
        $db->join('xun_user c', "c.id=b.user_id", "LEFT");
        $db->join('xun_story_updates a', "a.story_id = b.id", "LEFT");
        $copyDb = $db->copy();
        $db->orderBy('b.id', $order);
        $story_result = $db->get('xun_story b', $limit, "b.id, a.title, a.description, b.total_supporters, b.currency_id, b.user_id, b.category_id, b.fund_period, b.status, b.fund_amount, b.fund_collected, b.recommended, b.expires_at, b.created_at, b.updated_at, c.username, c.nickname, c.type, d.category");

       // print_r($story_result);
        $totalRecord = $copyDb->getValue('xun_story b', "count(b.id)");
        $user_id_arr = [];
        $currency_id_arr = [];
        foreach($story_result as $key => $value){
            $user_id = $value["user_id"];
            $currency_id = $value["currency_id"];

            if(!in_array($user_id, $user_id_arr)){
                $user_id_arr[] = $user_id;
            }
            
            if(!in_array($currency_id, $currency_id_arr)){
                $currency_id_arr[] = $currency_id;
            }
        }
       
        if($user_id_arr){
            $db->where('status', "approved");
            $db->where('user_id', $user_id_arr, "IN");
            $kyc_record = $db->map('user_id')->ArrayBuilder()->get('xun_kyc');
        }

        if($currency_id_arr){
            $db->where('fiat_currency_id', $currency_id_arr, "IN");
            $xun_fiat = $db->map('fiat_currency_id')->ArrayBuilder()->get('xun_fiat');
        }
       
        // $story_category = $db->map('category_id')->ArrayBuilder()->get('xun_story_category');

        foreach($story_result as $story_key => $story_value){
               $story_id = $story_value["id"];
               $user_id = $story_value["user_id"];
               $currency_id = $story_value["currency_id"];
               $story_title = $story_value["title"];
               $story_description = $story_value["description"];
               $username = $story_value["username"] ? $story_value['username'] : $user_id;
               $nickname = $story_value["nickname"];
               $category_type = $story_value["category"];
               $user_verified = $kyc_record[$user_id] ? 1 : 0; 
               $user_type = $story_value["type"];
               $currency_name = strtoupper($xun_fiat[$currency_id]["name"]);

               $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
               $creditType = $decimal_place_setting["credit_type"]; 

               $story_data = array(
                   "id" => $story_id,
                   "title" => $story_title,
                   "description" => $story_description,
                   "username" => $username,
                   "nickname" => $nickname,
                   "user_type" => $user_type,
                   "user_verified" => $user_verified,
                   "category_type" => $category_type,
                   "fund_amount" =>  $setting->setDecimal($story_value["fund_amount"], $creditType),
                   "fund_collected" => $setting->setDecimal($story_value["fund_collected"], $creditType),
                   "currency_name" => $currency_name,
                   "total_supporters" => $story_value["total_supporters"],
                   "recommended" => $story_value["recommended"],
                   "status" => $story_value["status"],
                   "fund_period" => $story_value["fund_period"],
                   "expired_at" => $story_value["expires_at"],
                   "created_at" => $story_value["created_at"],
                   "updated_at" => $story_value["updated_at"],
        
               );
               $story_list[] = $story_data; 
        }

        $returnData["data"] = $story_list;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;

        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Story Listing.", 'data' => $returnData);
    }

    public function admin_story_details_edit($params){
        global $xunStory, $xunCurrency;

        $db      = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $id = trim($params["id"]);
        $recommended = trim($params["recommended"]);
             
        if ($id == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Id cannot be empty");
        }

        if ($recommended == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Recommended cannot be empty");
        }

        if($recommended != 0 && $recommended != 1){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid recommended value");
        }

        $db->where('id', $id);
        $xun_story = $db->getOne('xun_story');

        if(!$xun_story){

            return array('status' => "error", 'code' => 1, 'statusMsg' => "Story not found!");
        }

        $updateStory = array(
            "recommended" => $recommended,
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $db->where('id',$id);
        $updated = $db->update('xun_story', $updateStory);

        if(!$updated){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Update recommended failed");
        }  

        return array("status" => "ok", "code" => 0, "statusMsg" => "Story details updated.");
    }

    public function adminGetStoryDetails($params){
        global $xunStory, $xunCurrency;
        $db      = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $id = trim($params["id"]);

        if ($id == '') {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Id cannot be empty");
        }

        $db->where('id', $id);
        $xun_story = $db->getOne('xun_story');

        if(!$xun_story){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Story not found.");
        }

        $user_id = $xun_story["user_id"];
        $category_id = $xun_story["category_id"];
        $currency_id = $xun_story["currency_id"];

        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
        $creditType = $decimal_place_setting["credit_type"];
        $fund_collected = $setting->setDecimal($fund_collected, $creditType);
    
        $db->where('id', $category_id);
        $story_category = $db->getOne('xun_story_category', "category");
        $category_type = $story_category["category"];

        $db->where('story_id', $id);
        $db->where('story_type', "story");
        $story_updates = $db->getOne('xun_story_updates');

        $db->where('fiat_currency_id', $currency_id);
        $xun_fiat = $db->getOne('xun_fiat',"name");
        
        $db->where('id', $user_id);
        $user_result = $db->getOne('xun_user', "username, nickname, type");
        $nickname = $user_result["nickname"];
        $username = $user_result["username"] ? $user_result["username"] : $user_id;
        $user_type = $user_result["type"];

        $db->where('story_id', $id);
        $db->orderBy('id', "DESC");
        $story_withdrawal = $db->getOne('xun_story_withdrawal');

        $details = array(
            "id" => $id,
            "username" => $username,
            "nickname" => $nickname,
            "user_type" => $user_type,
            "category_type" => $category_type,
            "total_supporters" => $xun_story["total_supporters"],
            "recommended" => $xun_story["recommended"],
            "title" => $story_updates["title"],
            "description" => $story_updates["description"],
            "fund_amount" => $setting->setDecimal($xun_story["fund_amount"], $creditType),
            "fund_collected" => $setting->setDecimal($xun_story["fund_collected"], $creditType),
            "currency_name" => strtoupper($xun_fiat["name"]),
            "fund_period" => $xun_story["fund_period"],
            "status" => $xun_story["status"],
            "withdrawal_status" => $story_withdrawal["status"],
            "withdrawal_reference_number" => $xun_story["reference_number"],
            "withdrawal_processing_fee" => $setting->setDecimal($xun_story["withdrawal_processing_fee"], $creditType),
            "expired_at" => $xun_story["expires_at"],
            "created_at" => $xun_story["created_at"],
            "updated_at" => $xun_story["updated_at"],
        );
        
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Story Details", 'data' => $details);
        
    }

    public function admin_get_transaction_list($params){
        $db      = $this->db;
        $setting = $this->setting;

        $admin_page_limit = $setting->getAdminPageLimit();
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order = $params["order"] ? $params["order"] : "DESC";
        $user_id = $params["user_id"];
        $name = $params["name"];
        $coin_type = $params["coin_type"];
        $tx_type = $params["tx_type"];

        if($user_id){
            $db->where('(a.sender_user_id = ? or a.recipient_user_id = ?)',array($user_id, $user_id));
        }

        if($name){
            $db->where('b.nickname', $name, "LIKE");
        }

        if($coin_type){
            $db->where('a.wallet_type',$coin_type);
        }

        if($tx_type){
            $type_details = $this->get_wallet_tx_address_type($tx_type);
            $address_type = $type_details["address_type"];
            $transaction_type = $type_details["transaction_type"];
            
            $db->where('a.address_type', $address_type);

            if($transaction_type){
                $db->where('a.transaction_type', $transaction_type);
            }
        }

        if ($page_number < 1) {
            $page_number = 1;
        }
        
        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);
        $db->orderBy('a.id', "DESC");
 
        $db->join('xun_user b', "b.id = a.sender_user_id", "LEFT");
        $copyDb = $db->copy();
        $wallet_transaction = $db->get('xun_wallet_transaction a', $limit, "a.sender_user_id, a.recipient_user_id, a.address_type, a.transaction_type, a.amount, a.wallet_type, a.fee, a.fee_unit, a.status, a.reference_id, a.created_at");

        $xun_coins = $db->get('xun_coins', null, 'currency_id');

        $coins_list = array_column($xun_coins, 'currency_id');

        $totalRecord = $copyDb->getValue('xun_wallet_transaction a', "count(a.id)");

        //get the whole tx type for dropdown
        $tx_type_list = $this->get_transaction_type();
        $tx_list = [];
        if($wallet_transaction){
            $user_id_array = [];
            foreach($wallet_transaction as $tx_key => $tx_value){
                $sender_user_id = $tx_value["sender_user_id"];
                $recipient_user_id = $tx_value["recipient_user_id"];
                if(!in_array($sender_user_id, $user_id_array)){
                    array_push($user_id_array, $sender_user_id);
                }
                if(!in_array($recipient_user_id, $user_id_arr)){
                    array_push($user_id_array, $recipient_user_id);
                }
            }

            $db->where('id', $user_id_array, "IN");
            $xun_user = $db->map('id')->ArrayBuilder()->get('xun_user', null, "id, username ,nickname");

            foreach($wallet_transaction as $key => $value){
                $sender_user_id = $value["sender_user_id"] ? $value["sender_user_id"] : '';
                $recipient_user_id = $value["recipient_user_id"] ? $value["recipient_user_id"] : '';
                $reference_id = $value["reference_id"];
                $commission_amount = 0;
                $sender_nickname = '';
                $recipient_nickname = '';
                $address_type = $value["address_type"];
                $transaction_type = $value["transaction_type"];//send or receive
                $amount = $value["amount"];
                if($sender_user_id){
                    $sender_nickname = $xun_user[$sender_user_id]["nickname"] ? $xun_user[$sender_user_id]["nickname"] : '';
                    if(!$sender_nickname){
                        $sender_nickname = $sender_user_id;
                    }
                }
               
                if($recipient_user_id){
                    $recipient_nickname = $xun_user[$recipient_user_id]["nickname"] ? $xun_user[$recipient_user_id]["nickname"] : '';
                    if(!$recipient_nickname){
                        $recipient_nickname = $recipient_user_id;
                        $recipient_user_id = '';
                    }
                }
                
                if($reference_id != 0){
                    $db->where('id',$reference_id);
                    $db->where('address_type', "service_charge");
                    $sc_result = $db->getOne('xun_wallet_transaction', "amount");
                    $commission_amount = $sc_result["amount"];
                }

                $new_tx_type = $this->map_transaction_type($address_type, $transaction_type);
                
                $tx_array = array(
                    "sender_user_id" => $sender_user_id,
                    "sender_nickname" => $sender_nickname,
                    "recipient_user_id" => $recipient_user_id,
                    "recipient_nickname" => $recipient_nickname,
                    "tx_type" => $new_tx_type,
                    "tx_amount" => $amount,
                    "commission_amount" => $commission_amount,
                    "coin_type" => $value["wallet_type"],
                    "miner_fee" => $value["fee"]." ".$value["fee_unit"],
                    "status" => $value["status"],
                    "date" => $value["created_at"],
                );

                $tx_list[] = $tx_array;
            }
        }

        $data["coins_list"] = $coins_list;
        $data["tx_type_list"] = $tx_type_list;
        $data["tx_list"]  = $tx_list;

        $returnData["data"] = $data;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;


        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Admin Transaction Listing", 'data' => $returnData);

    }

    public function admin_get_commission_contribute_receive_list($params){
        $db      = $this->db;
        $setting = $this->setting;

        $admin_page_limit = $setting->getAdminPageLimit();
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order = $params["order"] ? $params["order"] : "DESC";
        $name = $params["name"];
        $coin_type = $params["coin_type"];

        if ($page_number < 1) {
            $page_number = 1;
        }
        
        $start_limit = ($page_number - 1) * $page_size;
        //$limit       = array($start_limit, $page_size);

        if ($from_datetime) {
            $from_datetime  = date("Y-m-d H:i:s", $from_datetime);
            $db->where("created_at", $from_datetime, '>=');
        }
        
        if ($to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $db->where("created_at", $to_datetime, '<=');
        }

        $combine_string = "";
        if($name) {
            $name_string = "where nickname like '%$name%'";
        }

        if($coin_type){
            $coin_string = "and a.wallet_type = '$coin_type'";
            $referral_coin_string = "where crypto_currency = '$coin_type'";
        }

        $query = "select b.user_id, b.crypto_currency, b.quantity, c.user_id, c.wallet_type, c.amount, d.nickname from
        (select a.user_id, a.wallet_type, sum(amount) as amount from xun_service_charge_audit a where a.status = 'completed' $coin_string group by a.user_id, a.wallet_type) c left join
        (select user_id, crypto_currency, sum(quantity) as quantity from xun_referral_transaction $referral_coin_string group by user_id, crypto_currency) b
        on c.user_id=b.user_id and c.wallet_type=b.crypto_currency
        join (select id, nickname from xun_user $name_string) d
        on d.id = b.user_id or d.id = c.user_id
        union
        (select b.user_id, b.crypto_currency, b.quantity, c.user_id, c.wallet_type, c.amount, d.nickname from
        (select a.user_id, a.wallet_type, sum(amount) as amount from xun_service_charge_audit a where a.status = 'completed' $coin_string group by a.user_id, a.wallet_type) c right join
        (select user_id, crypto_currency, sum(quantity) as quantity from xun_referral_transaction $referral_coin_string group by user_id, crypto_currency) b
        on c.user_id=b.user_id and c.wallet_type=b.crypto_currency
        join (select id, nickname from xun_user $name_string) d
        on d.id = b.user_id or d.id = c.user_id) limit $start_limit, $page_size;";
        
        $commission_result = $db->rawQuery($query);

        $total_record_query = "select count(*) as totalRecord from (select b.user_id, b.crypto_currency, b.quantity, c.user_id as cuserid, c.wallet_type, c.amount, d.nickname from
        (select a.user_id, a.wallet_type, sum(amount) as amount from xun_service_charge_audit a where a.status = 'completed' $coin_string group by a.user_id, a.wallet_type) c left join
        (select user_id, crypto_currency, sum(quantity) as quantity from xun_referral_transaction $referral_coin_string group by user_id, crypto_currency) b
        on c.user_id=b.user_id and c.wallet_type=b.crypto_currency
        join (select id, nickname from xun_user $name_string ) d
        on d.id = b.user_id or d.id = c.user_id
        union
        (select b.user_id, b.crypto_currency, b.quantity, c.user_id as cuserid, c.wallet_type, c.amount, d.nickname from
        (select a.user_id, a.wallet_type, sum(amount) as amount from xun_service_charge_audit a where a.status = 'completed' $coin_string group by a.user_id, a.wallet_type) c right join
        (select user_id, crypto_currency, sum(quantity) as quantity from xun_referral_transaction $referral_coin_string group by user_id, crypto_currency) b
        on c.user_id=b.user_id and c.wallet_type=b.crypto_currency
        join (select id, nickname from xun_user $name_string ) d
        on d.id = b.user_id or d.id = c.user_id)) e";

       $totalRecord = $db->rawQueryOne($total_record_query);
       $totalRecord = $totalRecord["totalRecord"];

        $commission_listing = [];
        foreach($commission_result as $key => $value){
            $user_id = $value["user_id"];
            $nickname = $value["nickname"];
            $quantity = $value["quantity"] ? $value["quantity"] : 0; //total commission received
            $amount = $value["amount"] ? $value["amount"] : 0;//total commission contributed
            $coin_type = $value["wallet_type"] ? $value["wallet_type"] : $value["crypto_currency"];

            $commission_arr = array(
                "user_id" => $user_id,
                "name" => $nickname,
                "total_commission_received" => $quantity,
                "total_commission_contributed" => $amount,
                "coin_type" => $coin_type,
            );
            $commission_listing[] = $commission_arr;
        }
        
        $data["coins_list"] = $this->get_coins_list();
        $data["commission_list"] =  $commission_listing;

        $returnData["data"] = $data;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;

        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Admin Commission Contribute Receive Listing", 'data' => $returnData);

    }

    public function admin_get_commission_received_details($params){
        $db      = $this->db;
        $setting = $this->setting;

        $admin_page_limit = $setting->getAdminPageLimit();
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order = $params["order"] ? $params["order"] : "DESC";
        $user_id = $params["user_id"];
        $coin_type = $params["coin_type"];
        $from_datetime    = $params["from_datetime"];
        $to_datetime      = $params["to_datetime"];
        $commission_type = $params["commission_type"];

        if($user_id == ''){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "User id cannot be empty");
        }

        if($coin_type == ''){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Coin type cannot be empty");
        }

        if ($from_datetime) {
            $from_datetime  = date("Y-m-d H:i:s", $from_datetime);
            $db->where("a.created_at", $from_datetime, '>=');
        }
        
        if ($to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $db->where("a.created_at", $to_datetime, '<=');
        }

        if($commission_type){
            $type_details = $this->get_wallet_tx_address_type($commission_type);
            $address_type = $type_details["address_type"];
            $transaction_type = $type_details["transaction_type"];
            
            $db->where('a.address_type', $address_type);

            if($transaction_type){
                $db->where('a.transaction_type', $transaction_type);
            }
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $address_type_arr = array('upline', 'master_upline');
        $db->where('a.user_id', $user_id);
        $db->where('a.wallet_type', $coin_type);
        $db->where('a.address_type' , $address_type_arr, "IN");
        $db->join('xun_service_charge_audit c', "c.id = a.reference_id", "LEFT");
        $db->join('xun_user b', "b.id = a.user_id", "LEFT");
        $copyDb = $db->copy();
        $wallet_tx = $db->get('xun_wallet_transaction a', $limit, "a.user_id, b.nickname, a.address_type, a.transaction_type, a.amount, c.ori_tx_amount, c.ori_tx_wallet_type, a.wallet_type, a.status, a.created_at");

        $totalRecord = $copyDb->getValue('xun_wallet_transaction a', "count(a.id)");
        $commission_received_list = [];
        foreach($wallet_tx as  $key => $value){
            $user_id = $value["user_id"];
            $nickname = $value["nickname"];
            $address_type = $value["address_type"];
            $transaction_type = $value["transaction_type"];
            $amount = $value["amount"];
            $ori_tx_amount = $value["ori_tx_amount"];
            $ori_tx_wallet_type = $value["ori_tx_wallet_type"];
            $wallet_type = $value["wallet_type"];
            $status = $value["status"];
            $created_at = $value["created_at"];

            $new_tx_type = $this->map_transaction_type($address_type, $transaction_type);

            $tx_arr = array(
                "user_id" => $user_id,
                "nickname" => $nickname,
                "commission_type" => $new_tx_type,
                "commission_amount" => $amount,
                "tx_amount" => $ori_tx_amount,
                "coin_type" => $wallet_type,
                "tx_coin_type" => $ori_tx_wallet_type,
                "status" => $status,
                "created_at" => $created_at

            );
            $commission_received_list[] = $tx_arr;
        }

        $tx_type_arr = [];
        foreach($address_type_arr as $address_value){
            $dropdown_tx_type = $this->map_transaction_type($address_value, '');
            array_push($tx_type_arr, $dropdown_tx_type);
        }

        $data["coins_list"]= $this->get_coins_list();
        $data["commission_type"] = $tx_type_arr;
        $data["commission_received_list"] = $commission_received_list;

        $returnData["data"] = $data;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;

        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Admin Commission Received Details", 'data' => $returnData);

    }

    public function admin_get_commission_contributed_details($params){
        $db      = $this->db;
        $setting = $this->setting;

        $admin_page_limit = $setting->getAdminPageLimit();
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $order = $params["order"] ? $params["order"] : "DESC";
        $user_id = $params["user_id"];
        $coin_type = $params["coin_type"];
        $from_datetime    = $params["from_datetime"];
        $to_datetime      = $params["to_datetime"];
        $tx_type =  $params["tx_type"];

        if($user_id == ''){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "User id cannot be empty");
        }

        if($coin_type == ''){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Coin type cannot be empty");
        }

        if($tx_type){
            $type_details = $this->get_wallet_tx_address_type($tx_type);
            $address_type = $type_details["address_type"];
            $transaction_type = $type_details["transaction_type"];
            
            $db->where('a.service_charge_type', $address_type);

            if($transaction_type){
                $db->where('a.transaction_type', $transaction_type);
            }
        }

        if ($from_datetime) {
            $from_datetime  = date("Y-m-d H:i:s", $from_datetime);
            $db->where("a.created_at", $from_datetime, '>=');
        }
        
        if ($to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $db->where("a.created_at", $to_datetime, '<=');
        }

        if ($page_number < 1) {
            $page_number = 1;
        }
        
        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $address_type = array('payment_gateway', 'external_transfer', 'internal_transfer', 'escrow');
        $db->where('a.user_id', $user_id);
        $db->where('a.wallet_type', $coin_type);
        $db->where('a.service_charge_type', $address_type, "IN");
        $copyDb = $db->copy();
        $db->join('xun_user b', "b.id = a.user_id", "LEFT");
        $sc_tx = $db->get('xun_service_charge_audit a', $limit, "a.user_id, b.nickname, a.service_charge_type, a.transaction_type, a.amount, a.ori_tx_amount, a.ori_tx_wallet_type,a.wallet_type,a.status, a.created_at");

        $totalRecord = $copyDb->getValue('xun_service_charge_audit a', "count(a.id)");
        $commission_contributed_list = [];

        foreach($sc_tx as $key=>$value){
            $user_id = $value["user_id"];
            $nickname = $value["nickname"];
            $service_charge_type = $value["service_charge_type"];
            $transaction_type = $value["transaction_type"];
            $amount = $value["amount"];
            $ori_tx_amount = $value["ori_tx_amount"];
            $ori_tx_wallet_type = $value["ori_tx_wallet_type"];
            $wallet_type = $value["wallet_type"];
            $status = $value["status"];
            $created_at = $value["created_at"];
            $new_tx_type = $this->map_transaction_type($service_charge_type, $transaction_type);

            $tx_arr = array(
                "user_id" => $user_id,
                "nickname" => $nickname,
                "tx_type" => $new_tx_type,
                "commission_amount" => $amount,
                "tx_amount" => $ori_tx_amount,
                "coin_type" => $wallet_type,
                "tx_coin_type" => $ori_tx_wallet_type,
                "status" => $status,
                "created_at" => $created_at
            );
            $commission_contributed_list[] = $tx_arr;

        }
        //hardcode transaction_type into array to map the transaction type
        $transaction_type_arr = array(
            "0" => array(
                "address_type" => "external_transfer",
                "transaction_type" => "send",
            ),
            "1" => array(
                "address_type" => "external_transfer",
                "transaction_type" => "receive",
            ),
            "2" => array(
                "address_type" => "escrow",
                "transaction_type" => "",
            ),
            "3" => array(
                "address_type" => "internal_transfer",
                "transaction_type" => "",
            ),
            "4" => array(
                "address_type" => "payment_gateway",
                "transaction_type" => "",
            )
        );
        $tx_type_arr = [];
        foreach($transaction_type_arr as $tx_key => $tx_value){
            $address_type = $tx_value["address_type"];
            $transaction_type = $tx_value["transaction_type"];
            $dropdown_tx_type = $this->map_transaction_type($address_type, $transaction_type);
            array_push($tx_type_arr, $dropdown_tx_type);
        }

        $data["coins_list"] = $this->get_coins_list();
        $data["tx_type"] = $tx_type_arr;
        $data["commission_contributed_list"] = $commission_contributed_list;
 
        $returnData["data"] = $data;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;

        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Admin Commission Contributed Details", 'data' => $returnData);
    }
    

    public function admin_get_tx_summary($params){
        $db      = $this->db;

        $from_datetime    = $params["from_datetime"];
        $to_datetime      = $params["to_datetime"];

        if ($from_datetime) {
            $from_datetime  = date("Y-m-d H:i:s", $from_datetime);
            $db->where("created_at", $from_datetime, '>=');
        }
        
        if ($to_datetime) {
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $db->where("created_at", $to_datetime, '<=');
           
        }

        $copyDb = $db->copy();

        $address_type = array("payment_gateway", "external_transfer", "internal_transfer", "service_charge", "escrow", "pay", "company_acc");
        $db->where('address_type',$address_type , "IN");
        $db->groupBy('address_type, wallet_type');
        $wallet_tx = $db->get('xun_wallet_transaction', null, 'address_type, wallet_type, transaction_type, sum(amount) as total_amount');

        $copyDb->groupBy('fee_unit');
        $fee_tx = $copyDb->get('xun_wallet_transaction', null, "fee_unit, sum(fee) as total_fee" );

        $db->where('type', "cryptocurrency");
        $marketplace_currencies =$db->map('symbol')->ArrayBuilder()->get('xun_marketplace_currencies',null,  "symbol, currency_id");

        $tx_summary_arr = [];
        $tx_type_list = $this->get_transaction_type(1);
        array_push($tx_type_list, "Miner Fee");

        foreach($tx_type_list as $tx_list_value){
               
            foreach($wallet_tx as $key => $value){
                $wallet_type = $value["wallet_type"];
                $address_type = $value["address_type"];
                $transaction_type = $value["transaction_type"];
                $amount = $value["total_amount"] ? $value["total_amount"] : 0;
                $new_tx_type = $this->map_transaction_type($address_type, $transaction_type);
                if($tx_list_value == $new_tx_type){
                    $tx_summary_arr[$wallet_type][$tx_list_value] = $amount;
                }
                else{
                    $tx_summary_arr[$wallet_type][$tx_list_value] = 0;
                }

                //If is Miner Fee
                if($tx_list_value == "Miner Fee"){
                    foreach($fee_tx as $key => $value){
                        $fee_unit = strtolower($value["fee_unit"]);
                        $total_fee = $value["total_fee"];
                        $currency_id = $marketplace_currencies[$fee_unit];

                        if($wallet_type == $currency_id){
                            $tx_summary_arr[$wallet_type][$tx_list_value] = $total_fee;
                        }
                        
                    }
                }
                
            }     
            
        }
        $data["tx_summary_list"] = $tx_summary_arr;

        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Admin Transaction Summary", 'data' => $data);
        
    }

    public function get_transaction_type($is_tx_summary = ''){
        $db = $this->db;
        
        if($is_tx_summary){
            $address_type = array('master_upline', 'upline', 'company_pool', 'story');
            $db->where('address_type', $address_type, 'NOT IN');
        }
        $db->groupBy('address_type, transaction_type');
        $tx = $db->get('xun_wallet_transaction', null, "transaction_type, address_type");

        $type_arr = [];
        foreach($tx as $key => $value){
            $tx_type = $value["transaction_type"];
            $address_type = $value["address_type"];

            $type = $this->map_transaction_type($address_type, $tx_type, 1);
            if($type != ''){
                if(!in_array( $type,$type_arr)){
                    $type_arr[] = $type;
                }   
            }
                
        }
        return $type_arr;
    }

    public function map_transaction_type($address_type, $tx_type, $is_dropdown_list = 0){

        if($address_type == "payment_gateway"){
            $type = "Payment Gateway";
        }else if ($address_type == "external_transfer" && $tx_type == "receive"){
            $type = "Fund In";
        }else if ($address_type == "external_transfer" && $tx_type == "send"){
            $type = "Fund Out";
        }else if ($address_type == "internal_transfer"){
            $type = "Internal Transfer";
        }else if ($address_type == "escrow"){
            $type = "Escrow";
        }else if($address_type == "service_charge"){
            $type = "Service Charge";
        }else if($address_type == "pay" && $tx_type == "receive"){
            $type = "Pay Refund";
        }else if($address_type == "pay" && $tx_type == "send"){
            $type = "Pay";
        }else if($address_type == "upline"){
            $type = "Upline";
        }else if($address_type == "master_upline"){
            $type = "Master Upline";
        }else if($address_type == "company_pool"){
            $type = "Company Pool";
        }else if($address_type == "company_acc"){
            $type = "Company Account";
        }else if($address_type == "story"){
            $type = "Story";
        }
        else{
            $type = $address_type;
            if($is_dropdown_list == 1){
                $type = '';
            }
            
        }

        return $type;
    }

    //get the address type and transaction type value
    public function get_wallet_tx_address_type($type){
        $tx_type = '';

        if($type == "Payment Gateway"){
            $address_type = "payment_gateway";

        }elseif($type =="Fund In"){
            $address_type = "external_transfer";
            $tx_type = "receive";
            
        }elseif($type =="Fund Out"){
            $address_type = "external_transfer";
            $tx_type = "send";

        }elseif($type == "Internal Transfer"){
            $address_type = "internal_transfer";

        }elseif($type == "Escrow"){
            $address_type = "escrow";

        }elseif($type == "Service Charge"){
            $address_type = "service_charge";

        }elseif($type == "Pay Refund"){
            $address_type =  "pay";
            $tx_type = "receive";

        }elseif($type == "Pay"){
            $address_type = "pay";
            $tx_type = "send";

        }elseif($type == "Upline"){
            $address_type = "upline";

        }elseif($type == "Master Upline"){
            $address_type = "master_upline";
            
        }elseif($type == "Company Pool"){
            $address_type = "company_pool";

        }elseif($type == "Company Account"){
            $address_type = "company_acc";

        }elseif($type == "Story"){
            $address_type = "story";

        }else{
            $address_type = $type;
        }

        $tx_array = array (
            "address_type" => $address_type,
            "transaction_type" => $tx_type,
        );

        return $tx_array;
    }

    public function get_coins_list(){
        $db = $this->db;

        $xun_coins = $db->get('xun_coins', null, "currency_id");
        $coins_list = array_column($xun_coins, 'currency_id');

        return $coins_list; 
    }

    public function get_wallet_type($params){
        
        $db = $this->db;
        
        $db->where('a.is_payment_gateway', 1);
        $db->join('xun_marketplace_currencies b', 'a.currency_id = b.currency_id', 'LEFT');
        $xun_coins = $db->get('xun_coins a', null, 'b.name, b.currency_id, b.image');
 
         foreach($xun_coins as $key => $value){
             $wallet_type = $value['currency_id'];
             $name = $value['name'];
             $image = $value['image'];
 
             if($wallet_type == 'tetherusd'){
                 $wallet_list[] = $wallet_type;
                 $coin_array = array(
                     "name" => $name,
                     "wallet_type" => $wallet_type,
                     "image" => $image
                 );
                 $coin_list[] = $coin_array;
             }
         }
 
        foreach($xun_coins as $key => $value){
            $wallet_type = $value['currency_id'];
            $name = $value['name'];
            $image = $value['image'];
 
             if($wallet_type != 'tetherusd'){
                 $wallet_list[] = $wallet_type;
                 $coin_array = array(
                     "name" => $name,
                     "wallet_type" => $wallet_type,
                     "image" => $image
                 );
                 $coin_list[] = $coin_array;
             }
        }
         
         $returnData["wallet_types"] = $wallet_list;
         $returnData["coin_data"] = $coin_list;
         
         return array("status" => "ok", 'code' => 1, 'statusMsg'=> "$this->get_translation_message('B00090')" /*Wallet Types.*/, 'data' => $returnData);
    }

    public function send_nuxpay_contact_us_notification($ip, $ip_country, $keyword, $name, $email_address, $country, $mobile_number, $content, $status, $error_message = ''){
        global $xunPaymentGateway;

        $tag = "Enquiry";

        $msg = "IP: ".$ip."\n";
        $msg .= "Country: ".$ip_country."\n";
        $msg .= "Keyword: ".$keyword."\n";
        $msg .= "Name: ".$name."\n";
        $msg .= "Email: ".$email_address."\n";
        $msg .= "Country: " .$country."\n";
        $msg .= "Phone number: ".$mobile_number."\n";
        $msg .= "Message: ".$content."\n";
        $msg .= "Status: ".$status."\n";
        $msg .= "System Message: ".$error_message."\n";
        $xunPaymentGateway->send_nuxpay_notification($tag, $msg);
    }

    public function admin_mobile_submit_listing($params){
        $db= $this->db;
        $general = $this->general;

        $pageNumber = $params['page'] ? $params['page'] : 1;
        //Get the limit.
        $limit = $general->getXunLimit($pageNumber);

        $from_datetime    = $params["date_from"];
        $to_datetime  = $params["date_to"];
        $mobile = $params['mobile'];
        $domain  = $params["domain"];
        $url = $params['url'];
        $country = $params['country'];

        if($from_datetime){
            $from_datetime  = date("Y-m-d H:i:s", $from_datetime);
            $db->where('created_at', $from_datetime, '>=');
        }

        if($to_datetime){
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $db->where('created_at', $to_datetime, '<');
        }

        if($mobile){
            $db->where('mobile_number', "%$mobile%", 'LIKE');
        }

        if($domain){
            $db->where('tracking_site', "%$domain%", 'LIKE');
        }

        if($url){
            $db->where('url', "%$url%", 'LIKE');
        }

        if($country){
            $db->where('country', $country);
        }

        $db->where('(action_type like ? or action_type like ?)', array("%LandingPage Get Started button%", "%Home Get Started button%"));
        $copyDb= $db->copy();
        $db->orderBy('id', 'desc');
        $utm_result = $db->get('utm_tracking', $limit, 'mobile_number, ip, country, url, tracking_site, created_at');

        $totalRecords = $copyDb->getValue('utm_tracking', 'count(id)');

        if($utm_result){
            $utm_list = [];
            foreach($utm_result as $key=>$value){
                $mobile_number = $value['mobile_number'];
                $ip = $value['ip'];
                $country = $value['country'];
                $url = $value['url'];
                $tracking_site = $value['tracking_site'];
                $created_at = $value['created_at'];
    
                $utm_array = array(
                    "mobile_number" => $mobile_number,
                    "created_at" => $created_at,
                    "ip" => $ip,
                    "country" => $country,
                    "url" => $url,
                    "domain" => $tracking_site
                );
    
                $utm_list[] = $utm_array;
                
            }
            $data['listing']        = $utm_list;
            $data['totalPage']   = ceil($totalRecords / $limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord']   = $limit[1];

            return array('status' => "ok", 'code' => 1, 'statusMsg' => 'Admin Mobile Submit List', 'data' => $data);
        } else {
            return array('status' => "ok", 'code' => 1, 'statusMsg' => "No Results Found", 'data' => "");
        }


    }

    public function admin_approve_cashpool_topup($params)
    {
        global $xunCurrency, $account, $xunCrypto;
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $id = $params['id'];
        $status = $params['status'];

        if($id == ''){
            return array("status" => "error", "code" => "0", "statusMsg" => $this->get_translation_message('E00006') /*ID cannot be empty.*/);
        }

        if($status == ''){
            return array("status" => "error", "code" => "0", "statusMsg" => $this->get_translation_message('E00012') /*Status cannot be empty.*/);
        }
        
        $db->where('id', $id);
        $db->where('status', 'processing');
        $cashpool_topup = $db->getOne('xun_cashpool_topup');

        if (!$cashpool_topup) {
            return array('code' => 0, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00448') /*This transaction are not allowed to be update.*/);
        }

        $cashpool_topup_id = $cashpool_topup['id'];
        $topup_amount = $cashpool_topup['amount'];
        $topup_currency_id = $cashpool_topup['fiat_currency_id'];
        $business_id = $cashpool_topup['business_id'];

        $update_status = array(
            "status" => $status,
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $db->where('id', $id);
        $updated_topup = $db->update('xun_cashpool_topup', $update_status);

        if (!$updated_topup) {
            return array('code' => 0, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
        }

        $db->where('reference_id', $id);
        $db->where('transaction_type', 'topup');
        $updated_tx = $db->update('xun_cashpool_transaction', $update_status);

        if (!$updated_tx) {
            return array('code' => 0, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
        }

        $db->where('business_id', $business_id);
        $business_coin = $db->get('xun_business_coin');

        if(!$business_coin){
            return array('code' => 0, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00323') /*Business coin does not exist.*/);
        }

        $xun_business_service = new XunBusinessService($db);

        $business_address_data = $xun_business_service->getActiveAddressByUserIDandType($business_id, "reward", "id, address");
                
        $internal_address = $business_address_data["address"];

        foreach($business_coin as $test_coin){
            $currency_type_check[] = $test_coin['type'];
        }


        $reference_price = $business_coin[0]['reference_price'];
        $token_fiat_currency_id = $business_coin[0]['fiat_currency_id'];

        $rewardTokenDecimalPlaces = $setting->systemSetting['rewardTokenDecimalPlaces'];
        $fiat_currency_arr = array($token_fiat_currency_id, $topup_currency_id);
        $fiat_currency_price_arr = $xunCurrency->get_latest_fiat_price($fiat_currency_arr);
        
        //token fiat value
        $token_fiat_currency_value = $fiat_currency_price_arr[$token_fiat_currency_id]['exchange_rate'];
        //topup fiat value
        $topup_fiat_currency_value = $fiat_currency_price_arr[$topup_currency_id]['exchange_rate'];
        
        $token_fiat_value = bcdiv($token_fiat_currency_value, $topup_fiat_currency_value, '8');
        $topup_value = bcmul($token_fiat_value, $topup_amount, '8');
        $topup_supply = bcdiv($topup_value, $reference_price, '8');

        $topup_supply = number_format($topup_supply, 0, ",", "");

        $tx_obj = new stdClass();
        if (in_array("cash_token", $currency_type_check)){
            foreach($business_coin as $coin){
                if ($coin['type'] == 'cash_token'){
                    $wallet_type = $coin['wallet_type'];
        
                    $tx_obj->userID = $business_id;
                    $tx_obj->walletType = $wallet_type;
                    $tx_obj->amount = $topup_amount;
            
                    $satoshi_amount = $xunCrypto->get_satoshi_amount($wallet_type, $topup_amount);;
        
                    $transaction_token = $xun_business_service->insertCustomCoinTransactionToken($tx_obj);
        
                    //params for BC credit transfer request
                    $credit_transfer_params = array(
                        "walletType" => $wallet_type,
                        "receiverAddress" => $internal_address,
                        "amount" => $satoshi_amount,
                        "transactionToken" => $transaction_token
                    );
        
                    $notification_params = array(
                        "business_id" => $business_id,
                        "business_name" => $coin['business_name'],
                        "wallet_type" => $wallet_type,
                        "cashpool_topup_id" => $cashpool_topup_id,
                        "topup_amount" => $topup_amount,
                        "tag" => "Credit Transfer Request"
                    );
                    //request BC to transfer credit to company pool
                    $this->credit_transfer_send_notification($notification_params);
                    $return = $xunCrypto->request_credit_transfer_pool($credit_transfer_params);
                }
            }
        }else if (!in_array("cash_token", $currency_type_check)){
            foreach($business_coin as $coin){
                if ($coin['type'] == 'reward'){
                    // when cash reward is not created
                    $new_business_name = $coin['business_name'] . " reward";
                    $new_reward_symbol = $coin['symbol'] . "R";
                    $name_checking_params = array(
                        "name" => $new_business_name,
                        "symbol" => $new_reward_symbol
                    );

                    $check_token = $xunCrypto->check_token_name_availability($name_checking_params);
                    $card_business_name = $new_business_name;
                    $card_reward_symbol = $new_reward_symbol;
                    do{
                        $name_checking_data = $check_token["data"];
                        //name
                        if($name_checking_data["errorCode"] == "E10004"){
                            $rand_string = $general->generateAlpaNumeric(3);
                            $card_business_name = $new_business_name . $rand_string;
                        }
                        //symbol
                        if($name_checking_data["errorCode"] == "E10005"){
                            $rand_char = $general->generateAlpaNumeric(1);
                            $card_reward_symbol = $coin['symbol'] . $rand_char;
                        }
                        $name_checking_params = array(
                            "name" => $card_business_name,
                            "symbol" => $card_reward_symbol
                        );
                        $check_token = $xunCrypto->check_token_name_availability($name_checking_params);    
                    }while($check_token['code'] == 1);


                    $usd_value = bcdiv(1, $token_fiat_currency_value, '8');
                    $value = bcmul($usd_value, $reference_price, '8');

                    $businessObj->businessID = $business_id;
                    $businessObj->businessName = $card_business_name ? $card_business_name : $new_business_name;
                    $businessObj->rewardSymbol = $card_reward_symbol ? $card_reward_symbol : $new_reward_symbol;
                    $businessObj->fiatCurrencyID = $coin['fiat_currency_id'];
                    $businessObj->totalSupply = $topup_supply;
                    $businessObj->referencePrice = $coin['reference_price'];
                    $businessObj->cardBackgroundUrl = $coin['card_image_url'];
                    $businessObj->type = "cash_token";
                    $businessObj->fontColor = $coin['font_color'];

                    $business_coin_id = $xun_business_service->createBusinessCoin($businessObj);

                    //add cash reward token
                    $new_token_params = array(
                        "name" => $card_business_name ? $card_business_name : $new_business_name,
                        "symbol" => $card_reward_symbol ? $card_reward_symbol : $new_reward_symbol,
                        "decimalPlaces" => $rewardTokenDecimalPlaces,
                        "totalSupply" => $topup_supply,
                        "totalSupplyHolder" => $internal_address,
                        "exchangeRate" => array(
                            "usd" => $value,
                            $token_fiat_currency_id => $reference_price,
                        ),
                        "referenceID" => $business_coin_id
                    );

                    $add_reward_token = $xunCrypto->add_reward_token($new_token_params);
                    if($add_reward_token['status'] == 'error'){
                        $update_status = array(
                            "status" => 'failed',
                            "updated_at" => date("Y-m-d H:i:s"),
                        );

                        $db->where('id', $id);
                        $updated_topup = $db->update('xun_cashpool_topup', $update_status);

                        if (!$updated_topup) {
                            return array('code' => 0, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
                        }

                        $db->where('reference_id', $id);
                        $db->where('transaction_type', 'topup');
                        $updated_tx = $db->update('xun_cashpool_transaction', $update_status);

                        if (!$updated_tx) {
                            return array('code' => 0, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
                        }
                        return array("status" => "error", "code" => "0", "statusMsg" => $add_reward_token['statusMsg'], 'developer_msg' => $add_reward_token['statusMsg']);
                    }
                }
            }
        }

        if($return){
            if ($return['code'] == '0'){
                $return_status = "success";
                $account_res = $account->insertDebitTransaction($business_id, 'cashpool', $topup_amount, $cashpool_topup_id, date("Y-m-d H:i:s"));
            }else{
                $return_status = "failed";
            }

            //Notification after Credit transfer
            $notification_params = array(
                "business_id" => $business_id,
                "business_name" => $coin['business_name'],
                "wallet_type" => $wallet_type,
                "cashpool_topup_id" => $cashpool_topup_id,
                "topup_amount" => $topup_amount,
                "status" => $return_status,
                "tag" => "Credit Transfer Result"
            );
            $this->credit_transfer_send_notification($notification_params);

            if($return_status == 'failed'){
                $update_status = array(
                    "status" => $return_status,
                    "updated_at" => date("Y-m-d H:i:s"),
                );

                $db->where('id', $id);
                $updated_topup = $db->update('xun_cashpool_topup', $update_status);

                if (!$updated_topup) {
                    return array('code' => 0, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
                }

                $db->where('reference_id', $id);
                $db->where('transaction_type', 'topup');
                $updated_tx = $db->update('xun_cashpool_transaction', $update_status);

                if (!$updated_tx) {
                    return array('code' => 0, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
                }
                return array("status" => "error", "code" => "0", 'statusMsg' => $return['statusMsg'], 'developer_msg' => $return['statusMsg']);
            }
        }

        return array("code" => 1, "status" => "ok", "statusMsg" => $this->get_translation_message('B00242') /*Update Topup Status Success.*/);
    }

    public function credit_transfer_send_notification ($params) {
        global $xunXmpp, $config, $xun_numbers;

        $general = $this->general;

        $business_id = $params['business_id'];
        $business_name = $params['business_name'];
        $wallet_type = $params['wallet_type'];
        $cashpool_topup_id = $params['cashpool_topup_id'];
        $topup_amount = $params['topup_amount'];
        $status = $params['status'];
        $tag = $params['tag'];

        //Notification when request to transfer
        $message = "Business ID: " . $business_id . "\n";
        $message .= "Business Name: " . $business_name . "\n";
        $message .= "Wallet Type: " . $wallet_type . "\n";
        $message .= "Top Up ID: " . $cashpool_topup_id . "\n";
        $message .= "Amount: " . $topup_amount . "\n";
        if ($status){
            $message .= "Status: " . ucfirst($status) . "\n";
        }
        $message .= "\nTime: " . date("Y-m-d H:i:s");

        $thenux_params["tag"] = $tag;
        $thenux_params["message"] = $message;
        $thenux_params["mobile_list"] = $xun_numbers;
        $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
    }

    public function admin_cashpool_topup_list($params)
    {
        $db = $this->db;
        $setting = $this->setting;

        $business_id = $params['business_id'];
        $to_datetime = $params["date_to"];
        $from_datetime = $params["date_from"];
        $mobile = $params['mobile'];
        $business_name = $params['business_name'];
        $status = $params['status'];
        $status = strtolower($status);

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;

        if ($page_number < 1) {
            $page_number = 1;
        }

        //Get the limit.
        $start_limit = ($page_number - 1) * $page_size;
        $limit = array($start_limit, $page_size);

        if($business_id){
            $db->where('a.business_id', $business_id);
        }
        if ($from_datetime) {
            $from_datetime = date("Y-m-d H:i:s", $from_datetime);
            $db->where('a.created_at', $from_datetime, '>=');
        }

        if ($to_datetime) {
            $to_datetime = date("Y-m-d H:i:s", $to_datetime);
            $db->where('a.created_at', $to_datetime, '<');
        }

        if($mobile){
            $db->where('b.main_mobile', "%$mobile%", 'LIKE');
        }

        if($business_name){
            $db->where('c.name', "%$business_name%" , 'LIKE');
        }

        if($status){
            $db->where('a.status', $status);
        }
        
        $db->orderBy('a.id', 'desc');
        $db->join('xun_business c', 'c.user_id = a.business_id', 'LEFT');
        $db->join('xun_business_account b', 'b.user_id = a.business_id', 'LEFT');
        $copyDb= $db->copy();
        $cashpool_topup = $db->get('xun_cashpool_topup a', $limit, 'a.id, a.business_id, a.bank_name, a.fiat_currency_id, a.account_number, a.amount as topup_amount, a.bankslip_url, a.status, a.created_at, a.updated_at, b.main_mobile as mobile, c.name as business_name');

        $totalRecord = $copyDb->getValue('xun_cashpool_topup a', 'count(a.id)');

        if($cashpool_topup){
            foreach ($cashpool_topup as $key => $value) {
                $status = ucfirst($value['status']);

                $cashpool_topup[$key]['status'] = $status;
            }
        }
        if(!$cashpool_topup) return array('status' => 'ok', 'code' => 1, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");

        $numRecord = count($cashpool_topup);
        $returnData['data'] = $cashpool_topup;
        $returnData['totalPage'] = ceil($totalRecord / $limit[1]);
        $returnData['pageNumber'] = $page_number;
        $returnData['totalRecord'] = $totalRecord;
        $returnData['numRecord'] = $numRecord;

        return array("code" => 1, "status" => "ok", "statusMsg" => $this->get_translation_message('B00245') /*Admin Cashpool Topup Listing.*/, 'data' => $returnData);

    }

    public function get_translation_message($message_code)
    {
        // Language Translations.
        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $message = $translations[$message_code][$language];
        return $message;
    }

    public function nuxpay_admin_total_coin_transaction_amount_list($params){
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;

        $to_datetime = $params["date_to"];
        $from_datetime = $params["date_from"];

        if(!$to_datetime && !$from_datetime){
            return array('code' => 0, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00503') /*From datetime  and To datetime is required.*/, 'developer_msg' => 'From datetime  and To datetime is required.');
        }

        $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
        $from_datetime = date("Y-m-d H:i:s", $from_datetime);

        $db->where('a.created_at', $from_datetime, '>');
        $db->where('a.created_at' , $to_datetime, '<=');
        $db->where('a.status', 'success');
        $db->where('b.type', 'business');
        $db->join('xun_user b', 'a.business_id = b.id', 'LEFT');
        $crypto_history = $db->get('xun_crypto_history a');
       
        if(!$crypto_history){
            return array('status' => 'ok', 'code' => 1, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
        } 

        $marketplace_currencies = $xunCurrency->get_cryptocurrency_list();

        // foreach($marketplace_currencies as $mc_key => $mc_value){
        //     $name = $mc_value['name'];
        //     $wallet_type = $mc_value['currency_id'];
        //     $symbol = strtoupper($mc_value['symbol']);
        //     $coin_image_url = $mc_value['image'];
 
        //     $total_transaction_array = array(
        //         "name" => $name,
        //         "amount" => '0.00000000',
        //         "symbol" => $symbol,
        //         "coin_image_url" => $coin_image_url,
        //     );
            
        //     $total_transaction_list[$wallet_type] = $total_transaction_array;
        // }


        foreach($crypto_history as $key => $value){
            $amount_receive = $value['amount_receive'];
            $wallet_type = strtolower($value['wallet_type']);
            $symbol = strtoupper($marketplace_currencies[$wallet_type]['symbol']);
            $coin_image_url = $marketplace_currencies[$wallet_type]['image'];

            if(!$total_transaction_list[$wallet_type]){
                $total_transaction_list[$wallet_type]['name'] = $marketplace_currencies[$wallet_type]['name'];
                $total_transaction_list[$wallet_type]['symbol']= $symbol;
                $total_transaction_list[$wallet_type]['wallet_type']= $wallet_type;
                $total_transaction_list[$wallet_type]['coin_image_url'] = $coin_image_url;
            }
            $total_transaction_amount = bcadd($total_transaction_list[$wallet_type]['amount'], $amount_receive, 8);

            $total_transaction_list[$wallet_type]['amount'] = $total_transaction_amount;
        }
    
        $total_transaction_list = array_values($total_transaction_list);
        $data['coin_transaction_list'] = $total_transaction_list;

        return array("code" => 1, "status" => "ok", "statusMsg" => $this->get_translation_message('B00269') /*Admin NuxPay Total Coin Amount List*/, 'data' => $data);

    }

    public function admin_nuxpay_top_ten_transaction_list($params){
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;

        $to_datetime = $params["date_to"];
        $from_datetime = $params["date_from"];

        if(!$to_datetime && !$from_datetime){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00503') /*From datetime  and To datetime is required.*/, 'developer_msg' => 'From datetime and To datetime is required.');
        }

        $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
        $from_datetime = date("Y-m-d H:i:s", $from_datetime);

        $db->where('a.created_at', $from_datetime, '>');
        $db->where('a.created_at' , $to_datetime, '<=');
        $db->where('a.status', 'success');
        $db->where('c.type', 'business');
        $db->join('xun_user c', 'a.business_id = c.id', 'left');
        $db->join('xun_business b', 'a.business_id = b.user_id', 'LEFT');
        $crypto_history = $db->get('xun_crypto_history a', null, 'a.business_id, a.wallet_type, a.amount_receive as amount, a.created_at, b.name as business_name, b.profile_picture_url as business_image_url');

        if(!$crypto_history){
            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
        } 

        $marketplace_currencies = $xunCurrency->get_cryptocurrency_list('a.id, a.currency_id, a.symbol');
        $crypto_rate = $xunCurrency->get_cryptocurrency_rate_with_stable_coin($marketplace_currencies);

        $business_id_arr = [];
        foreach($crypto_history as $key => $value){
            $amount_receive = $value['amount'];
            $wallet_type = strtolower($value['wallet_type']);
            $business_id = $value['business_id'];
            $symbol = strtoupper($marketplace_currencies[$wallet_type]['symbol']);

            $business_image_url = $value['business_image_url'] ? $value['business_image_url'] : $setting->systemSetting['businessDefaultImageUrl'];

            $crypto_history[$key]['business_image_url'] = $business_image_url;
            $crypto_history[$key]['symbol']= $symbol;

            if(!in_array($business_id, $business_id_arr)){
                array_push($business_id_arr, $business_id);
            }

            $usd_amount = bcmul($amount_receive, $crypto_rate[$wallet_type], 2);

            $crypto_history[$key]['usd_amount'] = $usd_amount;
            $crypto_history[$key]['wallet_type']= $wallet_type;

        }

        //Sort array descending order by usd_amount
        usort($crypto_history, function($a, $b) {
            return $a['usd_amount'] < $b['usd_amount'];
        });

        //Get only the first 10 transaction of the list.
        $top_ten_transaction_list = array_splice($crypto_history, 0, 10);

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00270') /*Admin NuxPay Top Ten Transaction List.*/, 'data' => $top_ten_transaction_list);
    }

    public function admin_nuxpay_top_ten_merchants_list($params){
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;

        $to_datetime = $params["date_to"];
        $from_datetime = $params["date_from"];

        if(!$to_datetime && !$from_datetime){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00503') /*From datetime  and To datetime is required.*/, 'developer_msg' => 'From datetime and To datetime is required.');
        }

        $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
        $from_datetime = date("Y-m-d H:i:s", $from_datetime);

        $db->where('a.created_at', $from_datetime, '>');
        $db->where('a.created_at' , $to_datetime, '<=');
        $db->where('a.status', 'success');
        $db->where('b.type', 'business');
        $db->orderBy('a.id', 'DESC');
        $db->join('xun_user b', 'a.business_id = b.id', 'LEFT');
        $crypto_history = $db->get('xun_crypto_history a', null, 'a.business_id, a.wallet_type, a.amount_receive as amount, a.created_at, b.nickname');

        if(!$crypto_history){
            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
        } 

        $cryptocurrency_list = $xunCurrency->get_cryptocurrency_list('a.id, a.currency_id, a.symbol');
        $crypto_rate = $xunCurrency->get_cryptocurrency_rate_with_stable_coin($cryptocurrency_list);

        $business_id_arr = [];


        // foreach($cryptocurrency_list as $currency_key => $currency_value){
        //     $wallet_type = $currency_value['currency_id'];
        //     $crypto_arr = array(
        //         "symbol" => strtoupper($currency_value['symbol']),
        //         "wallet_type" => $currency_value['currency_id'],
        //         "amount" => '0.00000000',
        //     );

        //     $crypto_list[$wallet_type] = $crypto_arr;
        // }

        // foreach($crypto_history as $key => $value){ 
        //     $business_id = $value['business_id'];


        //     $merchant_arr = array(
        //         "business_name" => $business_name,
        //         "total_usd_amount" => '0.00',
        //         "crypto_list" => $crypto_list,
        //     );
        //     $merchant_list[$business_id] = $merchant_arr;
        // }

        foreach($crypto_history as $key => $value){
            $business_id = $value['business_id'];
            $amount_receive = $value['amount'];
            $business_name = $value['nickname'];
            $wallet_type = strtolower($value['wallet_type']);
            $total_usd_amount = $merchant_list[$business_id]['total_usd_amount'];

            if(!$merchant_list[$business_id]['crypto_list'][$wallet_type]){
                    $crypto_arr = array(
                        "symbol" => strtoupper($cryptocurrency_list[$wallet_type]['symbol']),
                        "wallet_type" => $wallet_type,
                        "amount" => '0.00000000',
                    );
        
                    $merchant_list[$business_id]['crypto_list'][$wallet_type] = $crypto_arr;
            }

            $crypto_amount = $merchant_list[$business_id]['crypto_list'][$wallet_type]['amount'];

            $usd_amount = bcmul($amount_receive, $crypto_rate[$wallet_type], 2);
            $new_total_usd_amount = bcadd($total_usd_amount, $usd_amount, 2);
            $new_crypto_amount = bcadd($crypto_amount, $amount_receive, 8);
            $merchant_list[$business_id]['business_name']  = $business_name;
            $merchant_list[$business_id]['total_usd_amount'] = $new_total_usd_amount;
            $merchant_list[$business_id]['crypto_list'][$wallet_type]['amount'] = $new_crypto_amount;
            
        }

        foreach($merchant_list as $key => $value){
            $crypto_list = $value['crypto_list'];

            $new_crypto_list = array_values($crypto_list);
            $merchant_list[$key]['crypto_list']= $new_crypto_list;
        }

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00272') /*Admin Nuxpay Top Ten Merchants List.*/, 'data' => $merchant_list);

    }

    public function admin_nuxpay_latest_transaction_list($params){
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;

        $limit = $params['limit'];
        $tx_type = $params['tx_type']; //fund_in/fund_out

        if($limit == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00505') /*Limit is required.*/, 'developer_msg' => 'Limit is required.');
        }
    
        // if(!$to_datetime && !$from_datetime){
        //     return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00503') /*From datetime  and To datetime is required.*/, 'developer_msg' => 'From datetime and To datetime is required.');
        // }

        // $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
        // $from_datetime = date("Y-m-d H:i:s", $from_datetime);

        // $db->where('a.created_at', $from_datetime, '>');
        // $db->where('a.created_at' , $to_datetime, '<=');
       
        if($tx_type == 'fund_out'){
            $db->where('a.status', 'confirmed');
            $db->where('b.type', 'business');
            $db->orderBy('a.id', 'DESC');
            $db->join('xun_user b', 'a.business_id = b.id', 'LEFT');
            $crypto_fund_out_list = $db->get('xun_crypto_fund_out_details a', array(0, $limit), 'a.business_id, a.wallet_type, a.amount, a.service_charge_amount as service_fee, a.service_charge_wallet_type as tx_fee_wallet_type,  a.status, a.created_at');

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
            $db->where('type', 'business');
            $xun_user = $db->map('id')->ArrayBuilder()->get('xun_user', null, 'id, nickname, service_charge_rate');
    
            foreach($crypto_fund_out_list as $key => $value){
                $business_id = $value['business_id'];
                $business_name = $xun_user[$business_id]['nickname'];
                $service_charge_rate = $xun_user[$business_id]['service_charge_rate'] ? $xun_user[$business_id]['service_charge_rate']  : '0.2';
     
                $crypto_fund_out_list[$key]['business_name'] = $business_name;
                $crypto_fund_out_list[$key]['service_charge_rate'] = $service_charge_rate;
    
            }
    
        }
        else{
            $db->where('a.status', 'success');
            $db->where('b.type', 'business');
            $db->orderBy('a.id', 'DESC');
            $db->join('xun_user b', 'a.business_id = b.id', 'LEFT');
            $crypto_history = $db->get('xun_crypto_history a', array(0, $limit), 'a.business_id, a.wallet_type, a.amount_receive as amount, a.transaction_fee as service_fee, a.tx_fee_wallet_type,  a.status, a.created_at');

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
            $db->where('type', 'business');
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
        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00271') /*Admin NuxPay Latest Transaction List.*/, 'data' => $data);

    }

    public function admin_nuxpay_top_merchant_service_fee($params){
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;

        $to_datetime = $params["date_to"];
        $from_datetime = $params["date_from"];

        if(!$to_datetime && !$from_datetime){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00503') /*From datetime  and To datetime is required.*/, 'developer_msg' => 'From datetime and To datetime is required.');
        }

        $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
        $from_datetime = date("Y-m-d H:i:s", $from_datetime);

        $db->where('a.created_at', $from_datetime, '>');
        $db->where('a.created_at' , $to_datetime, '<=');
        $db->where('a.status', 'success');
        $db->where('b.type', 'business');
        $db->orderBy('a.id', 'DESC');
        $db->join('xun_user b', 'a.business_id = b.id', 'LEFT');
        $crypto_history = $db->get('xun_crypto_history a', null, 'a.business_id, a.wallet_type, a.amount_receive as amount,a.transaction_fee, a.tx_fee_wallet_type, a.created_at,  b.nickname, b.service_charge_rate');
        if(!$crypto_history){
            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
        } 

        $cryptocurrency_list = $xunCurrency->get_cryptocurrency_list('a.id, a.currency_id, a.symbol');
        $crypto_rate = $xunCurrency->get_cryptocurrency_rate_with_stable_coin($cryptocurrency_list);

        $business_id_arr = [];
        foreach($crypto_history as $key => $value){
            $business_id = $value['business_id'];
            if(!in_array($business_id, $business_id_arr)){
                array_push($business_id_arr, $business_id);
            }

            $business_id = $value['business_id'];
            $business_name = $value['nickname'];
            $service_charge_rate = $value['service_charge_rate'] ? $value['service_charge_rate'] : '0.2';

            $merchant_arr = array(
                "business_id" => $business_id,
                "business_name" => $business_name,
                "service_charge_rate" => $service_charge_rate,
                "total_transaction_amount" => '0.00',
                "total_service_charge_amount" => '0.00',
            );
            $merchant_list[$business_id] = $merchant_arr;
        }

        foreach($crypto_history as $ch_key => $ch_value){
            $amount_receive = $ch_value['amount'];
            $wallet_type = strtolower($ch_value['wallet_type']);
            $business_id = $ch_value['business_id'];
            $transaction_fee = $ch_value['transaction_fee'];

            $transaction_usd_amount = bcmul($amount_receive, $crypto_rate[$wallet_type], 2);
            $transaction_fee_usd_amount = bcmul($transaction_fee, $crypto_rate[$wallet_type], 2);

            $current_total_transaction_amount = $merchant_list[$business_id]['total_transaction_amount'];
            $new_total_transaction_amount = bcadd($current_total_transaction_amount, $transaction_usd_amount, 2);

            $current_total_service_charge_amount = $merchant_list[$business_id]['total_service_charge_amount'];
            $new_total_service_charge_amount = bcadd($current_total_service_charge_amount, $transaction_fee_usd_amount, 2);

            $merchant_list[$business_id]['total_transaction_amount'] = $new_total_transaction_amount;
            $merchant_list[$business_id]['total_service_charge_amount'] = $new_total_service_charge_amount;

        }

        $new_merchant_list = array_values($merchant_list);
        $top_merchant_list = array_splice($new_merchant_list, 0, 10);

        if(!$top_merchant_list){
            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
        } 

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00273') /*Admin Nuxpay Top Merchant Service Fee.*/, 'data' => $top_merchant_list);

    }

    public function admin_nuxpay_transaction_history_listing($params){
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;

        $admin_page_limit      = $setting->getAdminPageLimit();
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $to_datetime           = $params["date_to"];
        $from_datetime         = $params["date_from"];
        $business_id           = $params["business_id"];
        $business_name         = $params["business_name"];
        $reseller              = $params["reseller"];
        $distributor           = $params["distributor"];
        $site                  = $params["site"];
        //
        $tx_hash               = $params['tx_hash'];
        //$status                = $params['status'];
        $phone_no              = $params['phone_no'];
        //
        $address               = $params['address'];
        $dest_address          = $params['dest_address'];
        $sender_address          = $params['sender_address'];
        $coin_type             = $params['coin_type'];

        if($business_id == '' && $business_name == '' && $to_datetime == '' && $from_datetime == '' && $tx_hash == '' /*&& $status == '' */&& $reseller == '' && $distributor == '' && $site == '' && $phone_no == '' && $address == '' && $dest_address == '' && $sender_address == '' && $coin_type == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00504') /*Searching parameter is required.*/, 'developer_msg' => 'Searching parameter is required.');
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
        $db->where('b.type', 'business');
        $db->join('xun_business_account c', 'a.business_id = c.user_id', 'INNER');
        $db->join('xun_user b', 'a.business_id = b.id', 'INNER');
        $db->join("reseller r", "r.id=b.reseller_id", "LEFT");

        $db->join("reseller r2", "r2.id=r.distributor_id", "LEFT");

        if($reseller) {
            $db->where("r.username", $reseller);
        }

        if($distributor) {
            $db->where("r2.username", $distributor);
        }

        if($site) {
            $db->where("r.source", $site);
        }

        if($business_id){
            $db->where('a.business_id', $business_id);
        }

        if($phone_no){
            $db->where('c.main_mobile', "%$phone_no%", 'LIKE');
        };

        if($address){
            $db->where('a.address', "%$address%", 'LIKE');
        }

        // if($dest_address){
        //     $db->where('a.recipient_internal', "%$dest_address%", 'LIKE')->where('a.recipient_external', "%$dest_address%", 'LIKE', 'OR');
        // }

        // if($sender_address){
        //     $db->where('a.sender_internal', "%$sender_address%", 'LIKE')->where('a.sender_external', "%$sender_address%", 'LIKE', 'OR');
        // }

        if($address){
            $db->where('a.receiver_address', "%$address%", 'LIKE');
        }

        if($sender_address){
            $db->where('a.sender_address', "%$sender_address%", 'LIKE');
        }

        if($coin_type){
            $db->where('a.wallet_type', $coin_type);
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

     

     

        $copyDb = $db->copy();
        $copyDb2 = $db->copy();
        $crypto_history = $db->get('xun_payment_gateway_fund_in a', $limit, 'a.business_id, a.wallet_type, a.amount_receive as amount, a.exchange_rate as exchange_rate, a.miner_fee, a.sender_address, a.receiver_address, a.transaction_fee, a.miner_fee_wallet_type, a.transaction_id as transaction_hash, a.created_at, b.nickname as business_name, b.service_charge_rate, c.main_mobile as username, IF(r.username is null, "-", r.username) as reseller, IF(r2.username is null, "-", r2.username) as distributor, b.register_site as site ');

        $overall_total_transaction = 0;
        if(!$crypto_history){
            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
        } 

        $crypto_history2 = $copyDb->get('xun_payment_gateway_fund_in a', null, 'a.business_id, a.wallet_type, a.miner_fee_wallet_type, a.amount_receive as amount,a.transaction_fee, a.transaction_id as transaction_hash, a.created_at, b.nickname as business_name, b.service_charge_rate');
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

        //post
        // $test = array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00307') /*Reseller Details*/, 'data' => $returnData);
        // echo json_encode($test);

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00274') /*Admin Nuxpay Transaction History List.*/, 'data' => $returnData);
    }

    public function admin_nuxpay_audit_summary_report($params){
        global $xunCurrency;
        $db = $this->db;
        $fundInDb = $db->copy();
        $fundInDb2 = $db->copy();
        $fundOutDb = $db->copy();
        $withdrawalDb = $db->copy();
        $withdrawalDb2 = $db->copy();
        $minerFeeDb = $db->copy();
        $withdrawalCopyDb = $db->copy();

        $setting = $this->setting;

        $admin_page_limit      = $setting->getAdminPageLimit();
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $to_datetime           = $params["date_to"];
        $from_datetime         = $params["date_from"];
        $business_id           = $params["business_id"];
        $type                  = $params['type'];

        if($business_id == '' && $to_datetime == '' && $from_datetime == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00504') /*Searching parameter is required.*/, 'developer_msg' => 'Searching parameter is required.');
        }
        if($from_datetime){
            $from_datetime = date("Y-m-d H:i:s", $from_datetime);
            $fundInDb->where('a.created_at', $from_datetime, '>');
            $fundInDb2->where('a.created_at', $from_datetime, '>');
            $fundOutDb->where('a.created_at', $from_datetime, '>');
            $withdrawalDb->where('h.created_at', $from_datetime, '>');
            $withdrawalDb2->where('h.created_at', $from_datetime, '>');
            $minerFeeDb->where('created_at', $from_datetime, '>');
        }

        if($to_datetime){
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $fundInDb->where('a.created_at' , $to_datetime, '<=');
            $fundInDb2->where('a.created_at' , $to_datetime, '<=');
            $fundOutDb->where('a.created_at' , $to_datetime, '<=');
            $withdrawalDb->where('h.created_at' , $to_datetime, '<=');
            $withdrawalDb2->where('h.created_at' , $to_datetime, '<=');
            $minerFeeDb->where('created_at' , $to_datetime, '<=');
        }

        // fund in
        $fundInDb->where('b.type', 'business');
        $fundInDb->join('xun_business_account c', 'a.business_id = c.user_id', 'INNER');
        $fundInDb->join('xun_user b', 'a.business_id = b.id', 'INNER');
        $fundInDb->join("reseller r", "r.id=b.reseller_id", "LEFT");
        $fundInDb->join("reseller r2", "r2.id=r.distributor_id", "LEFT");
        // fund out
        $fundOutDb->join('xun_user b', 'a.business_id = b.id', 'INNER');
        $fundOutDb->join("reseller r", "r.id=b.reseller_id", "LEFT");
        $fundOutDb->join("reseller r2", "r2.id=r.distributor_id", "LEFT");
        // withdrawal history2
        $withdrawalDb->where("u.type", "business");
        $withdrawalDb->join("xun_user u", "u.id=h.business_id", "INNER");
        $withdrawalDb->join("reseller r", "r.id=u.reseller_id", "LEFT");
        $withdrawalDb->join("reseller r2", "r2.id=r.distributor_id", "LEFT");

        $withdrawalCopyDb->where("u.type", "business");
        $withdrawalCopyDb->join("xun_user u", "u.id=h.business_id", "INNER");
        $withdrawalCopyDb->join("reseller r", "r.id=u.reseller_id", "LEFT");
        $withdrawalCopyDb->join("reseller r2", "r2.id=r.distributor_id", "LEFT");

        $withdrawalCopyDb2 = $withdrawalDb->copy();
        $withdrawalCopyDb3 = $withdrawalDb->copy();
        $withdrawalCopyDb1 = $withdrawalCopyDb->copy();

        if($business_id){
            $fundInDb->where('a.business_id', $business_id);
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        // withdrawal history2
        $sq = $db->subQuery();
        $sq->where("f.created_at", $from_datetime, ">");
        $sq->where("f.created_at", $to_datetime, "<=");
        $sq->where("f.status", "success");
        $sq->join("xun_crypto_history f1", "f.transaction_id = f1.received_transaction_id", "LEFT");
        $sq->where('f1.transaction_id','', '!=');
        $sq->get("xun_payment_gateway_fund_in f", null, "f1.transaction_id");
        $withdraw = $withdrawalDb2->get("xun_payment_gateway_withdrawal h", null, " h.created_at");
        if($withdraw)
        {
            $withdrawalCopyDb1->groupBy("h.status");
            $withdrawalCopyDb1->where("h.transaction_hash",$sq,"in");
            $result = $withdrawalCopyDb1->get("xun_payment_gateway_withdrawal h", null, "h.status, COUNT(*) as total_transaction");
            // $lq = $withdrawalCopyDb1->getLastQuery();
            // return array("code" => 110, "status" => "ok", "statusMsg" => $lq);
            // $withdrawalDb->where("h.transaction_hash in(SELECT f.transaction_id from xun_crypto_history f WHERE f.status = 'success')");
            $withdrawal_history2_transaction_summary = array("total_transaction"=>0, "success"=>0, "failed"=>0, "pending"=>0);
            foreach($result as $key => $value){
                $status = strtolower($value['status']);
                if($status=="success") {
                    $withdrawal_history2_transaction_summary['success'] = $value['total_transaction'];
                    $withdrawal_history2_transaction_summary['total_transaction'] += $value['total_transaction'];
                } else if($status=="failed") {
                    $withdrawal_history2_transaction_summary['failed'] = $value['total_transaction'];
                    $withdrawal_history2_transaction_summary['total_transaction'] += $value['total_transaction'];
                } else {
                    $withdrawal_history2_transaction_summary['pending'] = $value['total_transaction'];
                    $withdrawal_history2_transaction_summary['total_transaction'] += $value['total_transaction'];
                }
            }
            $sq = $db->subQuery();
            $sq->where("f.created_at", $from_datetime, ">");
            $sq->where("f.created_at", $to_datetime, "<=");
            $sq->where("f.status", "success");
            $sq->join("xun_crypto_history f1", "f.transaction_id = f1.received_transaction_id", "LEFT");
            $sq->where('f1.transaction_id','', '!=');
            $sq->get("xun_payment_gateway_fund_in f", null, "f1.transaction_id");
            // $withdrawalCopyDb->where("h.transaction_hash in(SELECT f.transaction_id from xun_crypto_history f WHERE f.status = 'success')");
            $withdrawalCopyDb->groupBy("h.wallet_type");
            $withdrawalCopyDb->where("h.transaction_hash",$sq,"in");
            // $result2 = $withdrawalCopyDb->get("xun_payment_gateway_withdrawal h", null, "h.wallet_type, SUM(h.amount_receive) as total_amount_receive, SUM(h.miner_fee) as total_miner_fee, SUM(h.transaction_fee) as total_processing_fee");
            $withdrawal_history2_currency_summary = $withdrawalCopyDb->map('wallet_type')->ArrayBuilder()->get("xun_payment_gateway_withdrawal h", null, "h.wallet_type, SUM(h.amount_receive) as total_amount_receive, SUM(h.miner_fee) as total_miner_fee, SUM(h.transaction_fee) as total_processing_fee");
            // wallet type 
            $wallet_type_arr = [];
            foreach($withdrawal_history2_currency_summary as $key => $value){
                $wallet_type = strtolower($value['wallet_type']);
                array_push($wallet_type_arr, $wallet_type);
                
            }
            $withdrawalCopyDb->where('currency_id', $wallet_type_arr, 'IN');
            $marketplace_currencies = $withdrawalCopyDb->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies');

            foreach($withdrawal_history2_currency_summary as $key => $value){
                $wallet_type = strtolower($value['wallet_type']);

                $withdrawal_history2_currency_summary[$wallet_type]['coin_name']= $marketplace_currencies[$wallet_type]['name'];
                $withdrawal_history2_currency_summary[$wallet_type]['tx_fee_symbol']= strtoupper($marketplace_currencies[$wallet_type]['symbol']);
                $withdrawal_history2_currency_summary[$wallet_type]["total_net_amount"]= ($value['total_amount_receive'] - $value['total_processing_fee'] - $value['total_miner_fee']);
            }

            $result3 = $withdrawalCopyDb3->get("xun_payment_gateway_withdrawal h", $limit, "h.created_at, u.register_site, u.reseller_id, u.id, u.nickname, h.wallet_type, h.amount, h.miner_fee, h.sender_address, h.recipient_address, h.transaction_hash, h.transaction_fee, h.status, IF(r.username is null, '-', r.username) as reseller_username, IF(r2.username is null, '-', r2.username) as distributor_username");
            if($result3){
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
            }
        }

        // fund in
        $copyDb = $fundInDb->copy();
        $copyDb2 = $fundInDb->copy();
        
        $crypto_history = $fundInDb->get('xun_payment_gateway_fund_in a', $limit, 'a.business_id, a.wallet_type, a.amount_receive as amount, a.exchange_rate as exchange_rate, a.miner_fee, a.sender_address, a.receiver_address, a.transaction_fee, a.miner_fee_wallet_type, a.transaction_id as transaction_hash, a.created_at, b.nickname as business_name, b.service_charge_rate, c.main_mobile as username, IF(r.username is null, "-", r.username) as reseller, IF(r2.username is null, "-", r2.username) as distributor, b.register_site as site ');
        $overall_total_transaction = 0;
        if($crypto_history)
        {
            $copyDb->where('f1.transaction_id','','!=');
            $copyDb->join("xun_crypto_history f1", "a.transaction_id = f1.received_transaction_id", "LEFT");
            $crypto_history2 = $copyDb->get('xun_payment_gateway_fund_in a', null, 'a.business_id, a.wallet_type, a.miner_fee_wallet_type, a.amount_receive as amount, a.status as transaction_status,a.transaction_fee, a.transaction_id as transaction_hash, a.created_at, b.nickname as business_name, b.service_charge_rate');
            // $lq = $copyDb->getLastQuery();
            // return array("code" => 110, "status" => "ok", "statusMsg" => $lq);
            $totalRecord = $copyDb2->getValue('xun_payment_gateway_fund_in a', 'count(a.id)');
            $cryptocurrency_list = $xunCurrency->get_all_cryptocurrency_list();
            // for transaction status table
            $fundInDb2->groupBy("a.status");
            $fundInDb2->where('f1.transaction_id','','!=');
            $fundInDb2->join("xun_crypto_history f1", "a.transaction_id = f1.received_transaction_id", "LEFT");
            $result = $fundInDb2->get("xun_payment_gateway_fund_in a", null, "a.status, COUNT(*) as total_transaction");
            $fund_in_transaction_status = array("total_transaction"=>0, "success"=>0, "failed"=>0, "pending"=>0);
            foreach($result as $key => $value){
                $status = strtolower($value['status']);
                if($status=="success") {
                    $fund_in_transaction_status['success'] = $value['total_transaction'];
                    $fund_in_transaction_status['total_transaction'] += $value['total_transaction'];
                } else if($status=="failed") {
                    $fund_in_transaction_status['failed'] = $value['total_transaction'];
                    $fund_in_transaction_status['total_transaction'] += $value['total_transaction'];
                } else {
                    $fund_in_transaction_status['pending'] = $value['total_transaction'];
                    $fund_in_transaction_status['total_transaction'] += $value['total_transaction'];
                }
                
            }
            // end of transaction status table

            foreach($crypto_history as $key => $value){
                $service_charge_rate = $value['service_charge_rate'] ? $value['service_charge_rate'] : '0.2';
                $wallet_type = strtolower($value['wallet_type']);
                $amount = $value['amount'];

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
                $overall_total_transaction++;
            }
            foreach($transaction_summary_list as $tx_key => $tx_value){
                $wallet_type = $tx_key;

                $transaction_summary_list[$wallet_type]['coin_name'] = $cryptocurrency_list[$wallet_type]['name'];
            }
            $transaction_summary_list = array_values($transaction_summary_list);
            }
        // Fund Out Listing
        $fundOutCopyDb = $fundOutDb->copy();
        $fundOutCopyDb2 = $fundOutDb->copy();
        $fundOutCopyDb3 = $fundOutDb->copy();
        $fund_out_details = $fundOutDb->get('xun_crypto_fund_out_details a', $limit, 'a.business_id, a.sender_address, a.recipient_address, a.amount, a.wallet_type, a.service_charge_amount, a.pool_amount, a.status, a.tx_hash, a.created_at, b.nickname, IF(r.username is null, "-", r.username) as reseller_name, IF(r2.username is null, "-", r2.username) as distributor_name, b.register_site as site');
        if($fund_out_details)
        {
            $fundOutTotalRecord = $fundOutCopyDb->getValue('xun_crypto_fund_out_details a', 'count(a.id)');
            $fundOutCopyDb2->groupBy('a.wallet_type');
            $fundOutSummary_list = $fundOutCopyDb2->map('wallet_type')->ArrayBuilder()->get('xun_crypto_fund_out_details a', null, 'sum(a.amount) as total_amount, sum(a.service_charge_amount) as total_service_fee, sum(a.pool_amount) as total_miner_fee, count(a.id) as total_transaction, a.wallet_type, (SUM(a.amount) - sum(a.service_charge_amount) - SUM(a.pool_amount)) AS total_nett_amount');
            $fundOutCopyDb3->groupBy('a.status');
            $fundOutTotal_transaction_by_status = $fundOutCopyDb3->get("xun_crypto_fund_out_details a", null, "a.status, COUNT(*) as total_transaction");
            foreach($fundOutSummary_list as $key => $value){
                $wallet_type = strtolower($value['wallet_type']);
                array_push($wallet_type_arr, $wallet_type);
            }
            $fundOutDb->where('currency_id', $wallet_type_arr, 'IN');
            $marketplace_currencies = $fundOutDb->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies');

            foreach($fundOutSummary_list as $key => $value){
                $wallet_type = strtolower($value['wallet_type']);
                $fundOutSummary_list[$wallet_type]['coin_name']= $marketplace_currencies[$wallet_type]['name'];
                $fundOutSummary_list[$wallet_type]['tx_fee_symbol']= strtoupper($marketplace_currencies[$wallet_type]['symbol']);
            }
            $fundOutTotal_transaction_by_status2 = array("total_transaction"=>0, "success"=>0, "failed"=>0, "pending"=>0);
            foreach($fundOutTotal_transaction_by_status as $key => $value)
            {
                $status = strtolower($value['status']);
                if($status=="confirmed") {
                    $fundOutTotal_transaction_by_status2['success'] = $value['total_transaction'];
                    $fundOutTotal_transaction_by_status2['total_transaction'] += $value['total_transaction'];
                } else if($status=="failed") {
                    $fundOutTotal_transaction_by_status2['failed'] = $value['total_transaction'];
                    $fundOutTotal_transaction_by_status2['total_transaction'] += $value['total_transaction'];
                } else {
                    $fundOutTotal_transaction_by_status2['pending'] = $value['total_transaction'];
                    $fundOutTotal_transaction_by_status2['total_transaction'] += $value['total_transaction'];
                }
            }
        }
        // miner fee report
        $miner_fee_arr = []; //miner fee in usd
        $miner_fee_fund_out_arr = []; //miner fee that actually company spent
        $miner_fee_collected = []; //miner fee that actually collected from transaction

        if($type == "marketer_fundout"){
            $minerFeeDb->where('address_type', 'marketer');
            $minerFeeDb->where('status', 'completed');
            $xun_wallet_transaction = $minerFeeDb->get('xun_wallet_transaction');
            if($xun_wallet_transaction) 
            {
                $feeUnitArr = [];
                $feeWalletTypeArr = [];
                foreach($xun_wallet_transaction as $key => $value){
                    $fee = $value['fee'];
                    $fee_unit = strtolower($value['fee_unit']);
                    $fee_wallet_type = strtolower($value['wallet_type']);
        
                    if(!in_array($fee_unit, $feeUnitArr)){
                        array_push($feeUnitArr, $fee_unit);
                    }
        
                    if(!in_array($fee_wallet_type, $feeWalletTypeArr)){
                        array_push($feeWalletTypeArr, $fee_wallet_type);
                    }
                }
                
                $minerFeeDb->where('symbol', $feeUnitArr, 'in');
                $marketplace_currencies = $minerFeeDb->map('symbol')->ArrayBuilder()->get('xun_marketplace_currencies');                
                $minerFeeDb->where('currency_id', $feeWalletTypeArr, 'IN');
                $marketplace_currencies1 = $minerFeeDb->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies');
                foreach($xun_wallet_transaction as $key => $value){
                    $fee = $value['fee'];
                    $fee_unit = strtolower($value['fee_unit']);
                    $miner_fee_wallet_type = $marketplace_currencies[$fee_unit]['currency_id'];
                    $miner_fee_exchange_rate = $value['miner_fee_exchange_rate'];
        
                    $exchange_rate = $value['exchange_rate'];
                    $wallet_type = $value['wallet_type'];
                    $amount = $value['amount'];
                    $created_at = $value['created_at'];
                    $name = $marketplace_currencies1[$wallet_type]['name'];
                    $unit = $marketplace_currencies1[$wallet_type]['symbol'];
        
                    if($miner_fee_wallet_type == 'ethereum'){
                        $decimal_places = 18;
                    }
                    else{
                        $decimal_places = $xunCurrency->get_currency_decimal_places($miner_fee_wallet_type);
                    }
                    
                    $miner_fee_value = bcmul($fee, $miner_fee_exchange_rate, 4);
                    if($miner_fee_arr[$miner_fee_wallet_type]){
                        $total_miner_fee = $miner_fee_arr[$miner_fee_wallet_type]['amount'];
                        $new_miner_fee_amount = bcadd($total_miner_fee, $miner_fee_value, 4);
                        if($new_miner_fee_amount >= 0.0000000001 || $new_miner_fee_amount != null)
                        {
                            $miner_fee_arr[$miner_fee_wallet_type] = array(
                                "amount" => $new_miner_fee_amount,
                                "unit" => $fee_unit,
                                "coin_name" => $name
                            );
                        }
                    }
                    else{
                        if($miner_fee_value >= 0.0000000001 || $miner_fee_value != null)
                        {
                            $miner_fee_arr[$miner_fee_wallet_type] = array(
                                "amount" => $miner_fee_value,
                                "unit" => $fee_unit,
                                "coin_name" => $coin_name
                            );
                        }
                    }

                    if($miner_fee_fund_out_arr[$miner_fee_wallet_type]){
                        $total_miner_fee_fund_out = $miner_fee_fund_out_arr[$miner_fee_wallet_type]['amount'];
                        $new_miner_fee_amount = bcadd($total_miner_fee_fund_out, $fee, $decimal_places);
                        if($new_miner_fee_amount >= 0.0000000001 || $new_miner_fee_amount != null)
                        {
                            $miner_fee_fund_out_arr[$miner_fee_wallet_type] = array(
                                "amount" => $new_miner_fee_amount,
                                "unit" => $fee_unit,
                                "coin_name" => $name
                            );
                        }
                    }
                    else{
                        if($fee >= 0.0000000001 || $fee != null)
                        {
                            $miner_fee_fund_out_arr[$miner_fee_wallet_type] = array(
                                "amount" => $fee,
                                "unit" => $fee_unit,
                                "coin_name" => $coin_name
                            );
                        }
                    }

                    if($miner_fee_collected[$wallet_type]){
                        if($miner_fee_wallet_type == $wallet_type){
                            $total_miner_fee_collected = $miner_fee_collected[$wallet_type]['amount'];
                            $new_miner_fee_collected_amount = bcadd($total_miner_fee_collected, $fee, $decimal_places);
                            if($new_miner_fee_collected_amount >= 0.0000000001 || $new_miner_fee_collected_amount != null)
                            {
                                $miner_fee_collected[$wallet_type] = array(
                                    "amount" => $new_miner_fee_collected_amount,
                                    "unit" => $unit,
                                    "coin_name" => $name,
                                );
                            }
                        }
                        else{
                            $miner_fee_native_value = bcdiv($miner_fee_value, $exchange_rate, $decimal_places); 
                            $total_miner_fee_collected = $miner_fee_collected[$wallet_type]['amount'];
                            $new_miner_fee_collected_amount = bcadd($total_miner_fee_collected, $miner_fee_native_value, $decimal_places);
                            if($new_miner_fee_collected_amount >= 0.0000000001 || $new_miner_fee_collected_amount != null)
                            {
                                $miner_fee_collected[$wallet_type] = array(
                                    "amount" => $new_miner_fee_collected_amount,
                                    "unit" => $unit,
                                    "coin_name" => $name,
                                );
                            }
                        }
                        
                    }
                    else{
            
                        if($miner_fee_wallet_type == $wallet_type){
                            if($fee >= 0.0000000001 || $fee != null)
                            {
                                $miner_fee_collected[$wallet_type] = array(
                                    "amount" =>  $fee,
                                    "unit"=> $unit,
                                    "coin_name" => $name                        
                                );
                            }
                        }
                        else{
                            $miner_fee_native_value = bcdiv($miner_fee_value, $exchange_rate, 18);
                            if($miner_fee_native_value >= 0.0000000001 || $miner_fee_native_value != null)
                            {
                                $miner_fee_collected[$wallet_type] = array(
                                    "amount" => $miner_fee_native_value,
                                    "unit" => $unit,
                                    "coin_name"  => $name,
                                );
                            }
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
            }
    
            // $data = array(
            //     "listing" => $miner_fee_listing, 
            //     "total_miner_fee_value" => $miner_fee_arr,
            //     "total_miner_fee_fund_out" => $miner_fee_fund_out_arr,
            //     "total_miner_fee_collected" => $miner_fee_collected,
                
            // );
            // $returnData['listing'] = $miner_fee_listing;
            
        } elseif($type == 'blockchain_fundout')
        {
            $minerFeeDb->where('status', 'confirmed');
            $crypto_fund_out_details = $minerFeeDb->get('xun_crypto_fund_out_details');
    
            $feeUnitArr = [];
            $minerFeeWalletTypeArr = [];

            foreach($crypto_fund_out_details as $key => $value){
                $miner_fee_amount = $value['pool_amount'];
                $transaction_details = json_decode($value['transaction_details'], true);
                $miner_fee_exchange_rate = $transaction_details['feeDetails']['exchangeRate']['USD'];
                $exchange_rate = $transaction_details['feeChargeDetails']['exchangeRate']['USD'];
                $feeUnit = $transaction_details['feeDetails']['unit'];
                $miner_fee_wallet_type = $value['pool_wallet_type'] ? $value['pool_wallet_type'] : $value['wallet_type'];
    
                if(!in_array($feeUnit, $feeUnitArr)){
                    array_push($feeUnitArr, $feeUnit);
                }
    
                if(!in_array($miner_fee_wallet_type, $minerFeeWalletTypeArr)){
                    array_push($minerFeeWalletTypeArr, $miner_fee_wallet_type);
                }
    
            }
    
            if($feeUnitArr){
                $minerFeeDb->where('symbol', $feeUnitArr, 'in');
                $marketplace_currencies = $minerFeeDb->map('symbol')->ArrayBuilder()->get('xun_marketplace_currencies');
            }
    
            if($minerFeeWalletTypeArr){
                $minerFeeDb->where('currency_id', $minerFeeWalletTypeArr, 'IN');
                $marketplace_currencies2 = $minerFeeDb->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies');
            }
      
            foreach($crypto_fund_out_details as $k => $v){
    
                $miner_fee_amount = $v['pool_amount'];
                $transaction_details = json_decode($v['transaction_details'], true);
                $miner_fee_exchange_rate = $transaction_details['feeDetails']['exchangeRate']['USD'];
                $exchange_rate = $transaction_details['feeChargeDetails']['exchangeRate']['USD'];
                $feeUnit = strtolower($transaction_details['feeDetails']['unit']);
                error_log(print_r($feeUnit, true));
                if(!$feeUnit)
                {
                    continue;
                }
    
                $miner_fee_wallet_type =  $marketplace_currencies[$feeUnit]['currency_id'];
                $name = $marketplace_currencies[$feeUnit]['name'];
                
                $wallet_type = $v['wallet_type'];
    
                $miner_fee_value = bcmul($miner_fee_amount, $exchange_rate, 4);
    
                if($miner_fee_arr[$miner_fee_wallet_type]){
                    $total_miner_fee = $miner_fee_arr[$miner_fee_wallet_type]['amount'];
                    $new_miner_fee_amount = bcadd($total_miner_fee, $miner_fee_value, 4);
                    if($new_miner_fee_amount >= 0.0000000001 || $new_miner_fee_amount != null)
                    {
                        $miner_fee_arr[$miner_fee_wallet_type] = array(
                            "amount" => $new_miner_fee_amount,
                            "unit" => $feeUnit,
                            "coin_name" => $marketplace_currencies2[$wallet_type]['name']
                        );
                    }
                }
                else{
                    if($miner_fee_value >= 0.0000000001 || $miner_fee_value != null)
                    {
                        $miner_fee_arr[$miner_fee_wallet_type] = array(
                            "amount" => $miner_fee_value,
                            "unit" => $feeUnit,
                            "coin_name" => $marketplace_currencies2[$wallet_type]['name']
                        );
                    }
                }
    
                $fund_out_miner_fee = bcdiv($miner_fee_value, $miner_fee_exchange_rate, 18);
                if($miner_fee_fund_out_arr[$miner_fee_wallet_type]){
                    $total_miner_fee_fund_out = $miner_fee_fund_out_arr[$miner_fee_wallet_type]['amount'];
                    $new_miner_fee_amount = bcadd($total_miner_fee_fund_out, $fund_out_miner_fee, 18);
                    $miner_fee_fund_out_arr[$miner_fee_wallet_type] = array(
                        "amount" => $new_miner_fee_amount,
                        "unit" => $feeUnit,
                        "coin_name" => $marketplace_currencies2[$wallet_type]['name']
                    );
                }
                else{
                    if($fund_out_miner_fee >= 0.0000000001 || $fund_out_miner_fee != null)
                    {
                        $miner_fee_fund_out_arr[$miner_fee_wallet_type] = array(
                            "amount" => $fund_out_miner_fee,
                            "unit" => $feeUnit,
                            "coin_name" => $marketplace_currencies2[$wallet_type]['name']
                        );
                    }
                }
    
                if($miner_fee_collected[$wallet_type]){
                    $total_miner_fee_collected = $miner_fee_collected[$wallet_type]['amount'];
                    $new_miner_fee_collected_amount = bcadd($total_miner_fee_collected, $miner_fee_amount, 18);
                    if($new_miner_fee_collected_amount >= 0.0000000001 || $new_miner_fee_collected_amount != null)
                    {
                        $miner_fee_collected[$wallet_type] = array(
                            "amount" => $new_miner_fee_collected_amount,
                            "unit" => $marketplace_currencies2[$wallet_type]['symbol'],
                            "coin_name" => $marketplace_currencies2[$wallet_type]['name']
                        );
                    }
                }
                else{
                    if($miner_fee_amount >= 0.0000000001 || $miner_fee_amount != null)
                    {
                        $miner_fee_collected[$wallet_type] = array(
                            "amount" => $miner_fee_amount,
                            "unit" => $marketplace_currencies2[$wallet_type]['symbol'],
                            "coin_name" =>  $marketplace_currencies2[$wallet_type]['name']
                        );
                    }
                }
    
                // $data = array(
                //     "total_miner_fee_value" => $miner_fee_arr,
                //     "total_miner_fee_fund_out" => $miner_fee_fund_out_arr,
                //     "total_miner_fee_collected" => $miner_fee_collected,
                // );

            }
    
        }
        elseif ($type == 'pg_fundout'){
            $minerFeeDb->where('recipient_external', '', '!=');
            $minerFeeDb->where('status', 'success');
            $crypto_history = $minerFeeDb->get('xun_crypto_history');
            if($crypto_history)
            {
                $minerFeeWalletTypeArr = [];
                foreach($crypto_history as $k => $v){
                    $miner_fee_wallet_type = strtolower($v['miner_fee_wallet_type']);
        
                    if(!in_array($miner_fee_wallet_type, $minerFeeWalletTypeArr)){
                        array_push($minerFeeWalletTypeArr, $miner_fee_wallet_type);
                    }
                }
                $minerFeeDb->where('currency_id', $minerFeeWalletTypeArr, 'IN');
                $marketplace_currencies = $minerFeeDb->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies');
                foreach($crypto_history as $key => $value){
                    $miner_fee = $value['miner_fee'];
                    $miner_fee_wallet_type = strtolower($value['miner_fee_wallet_type']);
                    $exchange_rate = $value['exchange_rate'];
                    $miner_fee_exchange_rate = $value['miner_fee_exchange_rate'];
                    $wallet_type = strtolower($value['wallet_type']);
                    $actual_miner_fee_amount = $value['actual_miner_fee_amount'] ? $value['actual_miner_fee_amount'] : $value['miner_fee'];
                    $actual_miner_fee_wallet_type = strtolower($value['actual_miner_fee_wallet_type']) ? strtolower($value['actual_miner_fee_wallet_type']) : $miner_fee_wallet_type;
                    $actual_miner_fee_exchange_rate = $value['miner_fee_exchange_rate'] ;
                    if(!$miner_fee){
                        continue;
                    }
                    $miner_fee_value = bcmul($miner_fee, $exchange_rate, 4);
        
                    if($miner_fee_arr[$miner_fee_wallet_type]){
                        $total_miner_fee = $miner_fee_arr[$miner_fee_wallet_type]['amount'];
                        $new_miner_fee_amount = bcadd($total_miner_fee, $miner_fee_value, 4);
                        if($new_miner_fee_amount >= 0.0000000001 || $new_miner_fee_amount != null)
                        {
                            $miner_fee_arr[$miner_fee_wallet_type] = array(
                                "amount" => $new_miner_fee_amount,
                                "unit" => $marketplace_currencies[$miner_fee_wallet_type]['symbol'],
                                "coin_name" => $marketplace_currencies[$miner_fee_wallet_type]['name']
                            );
                        }
                    }
                    else{
                        if($miner_fee_value >= 0.0000000001 || $miner_fee_value != null)
                        {
                            $miner_fee_arr[$miner_fee_wallet_type] =  array(
                                "amount" => $miner_fee_value,
                                "unit" => $marketplace_currencies[$miner_fee_wallet_type]['symbol'],
                                "coin_name" => $marketplace_currencies[$miner_fee_wallet_type]['name']
                            );
                        }
                    }
                    if($actual_miner_fee_wallet_type){
                        if($miner_fee_fund_out_arr[$actual_miner_fee_wallet_type]){
                            $total_miner_fee_fund_out = $miner_fee_fund_out_arr[$actual_miner_fee_wallet_type]['amount'];
                            $new_miner_fee_amount = bcadd($total_miner_fee_fund_out, $actual_miner_fee_amount, 18);
                            if($new_miner_fee_amount >= 0.0000000001 || $new_miner_fee_amount != null)
                            {
                                $miner_fee_fund_out_arr[$actual_miner_fee_wallet_type] = array(
                                    "amount" => $new_miner_fee_amount,
                                    "unit" => $marketplace_currencies[$miner_fee_wallet_type]['symbol'],
                                    "coin_name" => $marketplace_currencies[$miner_fee_wallet_type]['name'],
                                );
                            }
                        }
                        else{
                            if($actual_miner_fee_amount >= 0.0000000001 || $actual_miner_fee_amount != null)
                            {
                                $miner_fee_fund_out_arr[$actual_miner_fee_wallet_type] = array(
                                    "amount" => $actual_miner_fee_amount,
                                    "unit" => $marketplace_currencies[$miner_fee_wallet_type]['symbol'],
                                    "coin_name" => $marketplace_currencies[$miner_fee_wallet_type]['name'],
                                );
                            }
                        }
                    }
        
                    if($miner_fee_collected[$wallet_type]){
                        $total_miner_fee_collected = $miner_fee_collected[$wallet_type]['amount'];
                        $new_miner_fee_collected_amount = bcadd($total_miner_fee_collected, $miner_fee, 18);
                        if($new_miner_fee_collected_amount >= 0.0000000001 || $new_miner_fee_collected_amount != null)
                        {
                            $miner_fee_collected[$wallet_type] = array(
                                "amount" => $new_miner_fee_collected_amount,
                                "unit" => $marketplace_currencies[$wallet_type]['symbol'],
                                "coin_name" => $marketplace_currencies[$wallet_type]['name']
                            );
                        }
                    }
                    else{
                        if($miner_fee >= 0.0000000001 || $miner_fee != null)
                        {
                            $miner_fee_collected[$wallet_type] = array(
                                "amount" => $miner_fee,
                                "unit" => $marketplace_currencies[$wallet_type]['symbol'],
                                "coin_name" => $marketplace_currencies[$wallet_type]['name'],
                            );
                        }
                    }
            }
            }
        }

        // fund in
        $returnData['transaction_summary_fund_in'] = $transaction_summary_list;
        $returnData['transaction_status_fund_in'] = $fund_in_transaction_status;

        // fund out
        $returnData["summary_listing_fund_out"] = $fundOutSummary_list;
        $returnData["total_transaction_by_status_fund_out"] = $fundOutTotal_transaction_by_status2;
        $returnData["overall_total_transaction_fund_out"] = $fundOutTotalRecord;

        // withdrawal history 2
        $returnData["transaction_summary_withdrawal_history"] = $withdrawal_history2_transaction_summary;
        $returnData["currency_summary_withdrawal_history"] = $withdrawal_history2_currency_summary;

        // miner fee report
        $returnData['total_miner_fee_value'] = $miner_fee_arr;
        $returnData['total_miner_fee_fund_out'] = $miner_fee_fund_out_arr;
        $returnData['total_miner_fee_collected'] = $miner_fee_collected;

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00274') /*Admin Nuxpay Transaction History List.*/, 'data' => $returnData);
    }

    public function admin_nuxpay_transaction_history_details($params){
        global $xunCurrency, $xunCrypto;
        $db = $this->db;
        $setting = $this->setting;

        $admin_page_limit      = $setting->getAdminPageLimit();
        $transaction_hash           = $params["transaction_hash"];

        if($transaction_hash == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00358') /*Transaction hash is required.*/, 'developer_msg' => 'Transaction hash is required.');
        }

        $db->where('a.transaction_id', $transaction_hash);
        $db->join('xun_user b', 'a.business_id = b.id', 'LEFT');
        $copyDb = $db->copy();
        $copyDb2 = $db->copy();
        $crypto_history = $db->getOne('xun_crypto_history a', 'a.business_id, a.wallet_type, a.address, a.recipient_external, a.recipient_internal, a.sender_interval, a.sender_external, a.amount_receive as amount,a.transaction_fee, a.status, a.transaction_id as transaction_hash, a.created_at, b.nickname as business_name, b.service_charge_rate, a.miner_fee, a.miner_fee_wallet_type, a.exchange_rate, a.miner_fee_exchange_rate');

        $wallet_type = $crypto_history['wallet_type'];
        $recipient_address = $crypto_history['recipient_internal'];
        $miner_fee_wallet_type = $crypto_history['miner_fee_wallet_type'];
        // $wallet_info = $xunCrypto->get_wallet_info($recipient_address, $wallet_type);
        // $miner_fee_wallet_type = $wallet_info[$wallet_type]['feeType'];


        $db->where('transaction_hash', $transaction_hash);
        $service_charge_audit = $db->getOne('xun_service_charge_audit');

        if(!$service_charge_audit){
            return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => []);
        }

        $service_charge_audit_id = $service_charge_audit['id'];
        $total_service_charge_amount = $service_charge_audit['amount'];

        $address_type_arr = ["upline", "master_upline", "company_acc", "marketer"];
        $db->where("a.reference_id", $service_charge_audit_id);
        $db->where("a.address_type", $address_type_arr, "IN");
        $db->join("xun_user b", "a.recipient_user_id=b.id", "LEFT");
        $wallet_transaction = $db->get("xun_wallet_transaction a", null, "a.id, a.amount, a.wallet_type, a.transaction_hash, a.status, a.address_type, a.created_at, a.updated_at, b.id as user_id, b.type as user_type, b.nickname, b.username, a.fee, a.exchange_rate, a.miner_fee_exchange_rate, a.fee" );
        if(!$wallet_transaction){
            return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => []);
        }

        foreach($wallet_transaction as &$data){
            $address_type = $data["address_type"];
            if($address_type == "upline"){
                $address_type = "Referrer";
            }else if($address_type == "master_upline"){
                $address_type = "Master dealer";
            }else if($address_type == "company_pool"){
                $address_type = "Company Pool";
            }else if($address_type == "company_acc"){
                $address_type = "Company Account";
            }else if($address_type == "marketer"){
                $address_type = "Marketer";
            }

            unset($data["address_type"]);
            $data["recipient_type"] = $address_type;
            $data["status"] = ucfirst($data["status"]);
            $data["service_charge_amount"] = $total_service_charge_amount;
            $data['miner_fee_wallet_type'] = $miner_fee_wallet_type;

            $miner_fee = $data['fee'];
            $miner_fee_exchange_rate = $data['miner_fee_exchange_rate'];

            $miner_fee_value = bcmul($miner_fee, $miner_fee_exchange_rate, 18);
            $data['miner_fee_value'] = $miner_fee_value;

        }
     

        $transacted_amount = $crypto_history['amount'];
        $service_charge_rate = $crypto_history['service_charge_rate'] ? $crypto_history['service_charge_rate'] : '0.2';
        $transaction_fee = $crypto_history['transaction_fee'];
        $business_name = $crypto_history['business_name'];
        $business_id = $crypto_history['business_id'];
        $status = ucfirst($crypto_history['status']);
        $wallet_type = $crypto_history['wallet_type'];
        $miner_fee = $crypto_history['miner_fee'];
        $miner_fee_wallet_type = $crypto_history['miner_fee_wallet_type'];
        $miner_fee_exchange_rate = $crypto_history['miner_fee_exchange_rate'];
        $address = $crypto_history['address'];
        $recipient_internal = $crypto_history['recipient_internal'];
        $recipient_external = $crypto_history['recipient_external'];
        $sender_internal = $crypto_history['sender_internal'];
        $sender_external = $crypto_history['sender_external'];

        $cryptocurrency_list = $xunCurrency->get_cryptocurrency_list('a.id, a.currency_id, a.symbol');
        $symbol = strtoupper($cryptocurrency_list[$wallet_type]['symbol']);

        $return_data = array(
            "transacted_amount" => $transacted_amount,
            "service_charge_rate" => $service_charge_rate,
            "service_fee" => $transaction_fee,
            "symbol" => $symbol,
            "business_name" => $business_name,
            "business_id" => $business_id,
            "status" => $status,
            "tx_hash" => $transaction_hash,
            "tx_data" => $wallet_transaction,
            "miner_fee" => $miner_fee,
            "miner_fee_wallet_type" => $miner_fee_wallet_type,
            "miner_fee_exchange_rate" => $miner_fee_exchange_rate,
            "address" => $address,
            'recipient_internal' => $recipient_internal,
            'recipient_external' => $recipient_external,
            'sender_internal' => $sender_internal,
            'sender_external' => $sender_external
           
        );

        

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00277') /*Admin Nuxpay Transaction History Details.*/, 'data' => $return_data);
  
    }

    public function admin_nuxpay_merchant_list($params, $site, $userID){
        global $xunCurrency, $xunReseller;
        $db = $this->db;
        $setting = $this->setting;

        $admin_page_limit      = $setting->getAdminPageLimit();
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $business_id           = $params["business_id"];
        $business_name         = $params["business_name"];
        // $from_datetime         = $params["date_from"];
        // $to_datetime           = $params["date_to"];
        $phone_number          = $params["phone_number"];
        $business_site         = $params["business_site"];
        $email                 = $params["email"];
        $distributor_username  = $params['distributor_username'];
        $reseller_username     = $params['reseller_username'];

        if($business_id == '' && $business_name == '' && $phone_number == '' && $business_site == '' && $distributor_username == '' && $reseller_username == '' && $email == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00504') /*Searching parameter is required.*/, 'developer_msg' => 'Searching parameter is required.', "business_name" => $business_name, "params" => $params);
        }
 
        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $arr_reseller_id = $xunReseller->get_reseller($userID);

        if($site != "Admin"){
            $db->where("id", $userID);
            $source = $db->getValue("reseller", "source");
        }
        
        if($business_id){
            $db->where('a.id',$business_id);
        }

        if($business_name){
            $db->where('a.nickname', "%$business_name%", 'LIKE');
        }

        // if($from_datetime){
        //     $from_datetime = date("Y-m-d H:i:s", $from_datetime);
        //     $db->where('a.created_at', $from_datetime, '>');
        // }

        // if($to_datetime){
        //     $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
        //     $db->where('a.created_at' , $to_datetime, '<=');
        // }

        // $db->where('a.reseller_id', $arr_reseller_id, 'IN');
        if($phone_number){
            $db->where('a.username', "%$phone_number%" , 'LIKE');
        }

        if($business_site){
            $db->where('a.register_site', $business_site);
        }

        if($email){
            $db->where("a.email_verified", 1);
            $db->where('a.email', $email);
        }

        if($distributor_username){
            $db->where('d.username', $distributor_username);
        }

        if($reseller_username){
            $db->where('c.username', $reseller_username);
        }

        if($site != "Admin"){
            $db->where("a.register_site", $source);
        }
        $db->where('a.type', 'business');
        // $db->where('a.reseller_id', $arr_reseller_id, 'IN');
        $db->join('xun_business b', 'b.user_id = a.id', 'LEFT');
        $db->join('reseller c', 'c.id = a.reseller_id', 'LEFT');
        $db->join('reseller d', 'd.id = c.distributor_id', 'LEFT');
        $db->orderBy('a.id', 'DESC');
        $copyDb = $db->copy();
        $nuxpay_user = $db->get('xun_user a', $limit, 'a.id, a.nickname, a.created_at, a.register_site, a.email, a.username as phone_number, c.username as reseller_username, d.username as distributor_username');
        // print_r($db->getLastQuery());
        if(!$nuxpay_user){
            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
        }
        //
        $totalRecord = $copyDb->getValue('xun_user a', 'count(a.id)');

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
        $business_owner_mobile_arr = [];
        
        foreach($nuxpay_user as $user_key => $user_value){
            $business_owner_mobile = $user_value['phone_number'] ? $user_value['phone_number'] : '-';
            $business_id = $user_value['id'];
            $business_created_at = $user_value['created_at'];
            $business_name = $user_value['nickname'];
            $business_register_site = $user_value['register_site'];
            $business_email = $user_value['email'] ? $user_value['email'] : '-';
            $business_country = $user_country[$business_id] ? $user_country[$business_id]['value']  : '-';
            $business_distributor_username = $user_value['distributor_username'];
            $business_reseller_username = $user_value['reseller_username'];
            // $business_service_charge = $user_value['service_charge_rate'];
            // $business_register_through = $user_value['register_through'];

            if(!in_array($business_owner_mobile, $business_owner_mobile_arr)){
                array_push($business_owner_mobile_arr, $business_owner_mobile);
            }

            if(!in_array($business_id, $business_id_arr)){
                array_push($business_id_arr, $business_id);
            }

            // // CONVERT SERVICE CHARGE TO PERCENTAGE FORMAT
            // if($business_service_charge){
            //     $business_service_charge = round((float)$business_service_charge * 100 ) . '%';
            // }else{
            //     $business_service_charge = 0;
            // }

            // if(!$business_register_through){
            //     $business_register_through = '';
            // }

            if(!$business_country){
                $business_country = '';
            }

            if(!$business_owner_mobile){
                $business_owner_mobile = '';
            }

            if(!$business_owner_mobile){
                $business_owner_mobile = '';
            }

            if(!$business_distributor_username){
                $business_distributor_username = '';
            }

            if(!$business_reseller_username){
                $business_reseller_username = '';
            }

            $merchant_arr = array(
                "business_id" => $business_id,
                "business_name" => $business_name,
                "business_created_at" => $business_created_at,
                "business_main_mobile" => $business_owner_mobile,
                // "business_service_charge" => $business_service_charge,
                // "business_register_through" => $business_register_through,
                "business_register_site" => $business_register_site,
                "business_email" => $business_email,
                "business_country" => $business_country,
                "business_distributor_username" => $business_distributor_username,
                "business_reseller_username" => $business_reseller_username,
                "total_transaction" => '0',
                "total_transaction_usd" => '0.00',
                "total_commission_usd" => '0.00',

            );

            $merchant_list[$business_id] = $merchant_arr;
        }

        $db->where('username', $business_owner_mobile_arr, 'IN');
        $db->where('type', 'user');
        $business_owner = $db->map('username')->ArrayBuilder()->get('xun_user');

        foreach($merchant_list as $merchant_key => $merchant_value){
            $business_owner_mobile = $merchant_value['business_main_mobile'];
            $business_id = $merchant_value['business_id'];

            // $business_owner_name = $business_owner[$business_owner_mobile]['nickname'];
            // $merchant_list[$business_id]['business_owner_name'] = $business_owner_name ? $business_owner_name : '';
        }
        
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
        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00275') /*Admin Nuxpay Merchant List.*/, 'data' => $returnData);

    }

    public function admin_nuxpay_merchant_details($params){
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;

        $business_id           = $params["business_id"];

        if($business_id == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/, 'developer_msg' => 'Business ID cannot be empty.');
        }

        $col = 'r.source, r.username, r.distributor_id, a.id, a.nickname, a.service_charge_rate,  a.created_at, b.main_mobile, c.email, c.website, c.phone_number, c.company_size, c.address1, c.address2, c.city, c.state, c.postal, c.country, IF(r.username is null, "-", r.username) as reseller_name, a.username as business_phone_number, a.email as business_email';
        $db->where('a.id', $business_id);
        $db->join("reseller r", "r.id=a.reseller_id", "LEFT");
        $db->join('xun_business c', 'c.user_id = a.id', 'INNER');
        $db->join('xun_business_account b', 'b.user_id = a.id', 'INNER');
        $db->orderBy('a.id', 'DESC');
        $copyDb = $db->copy();
        $nuxpay_user = $db->getOne('xun_user a', $col);
        
        $merch_id = $nuxpay_user['distributor_id'];
        
        $db->where('type', 'distributor');
        $db->where('id', $merch_id);
        $distributor_name = $db->getValue("reseller", "username");
        
        if(!$nuxpay_user){
            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
        }
        
        $business_owner_mobile = $nuxpay_user['main_mobile'];
        $business_name = $nuxpay_user['nickname'];
        $reseller = $nuxpay_user['reseller_name'];
        $site = $nuxpay_user['source'];
        $address1 = $nuxpay_user['address1'];
        $address2 = $nuxpay_user['address2'];
        $city = $nuxpay_user['city'];
        $state = $nuxpay_user['state'];
        $postal = $nuxpay_user['postal'];
        $country = $nuxpay_user['country'];
        $business_website = $nuxpay_user['website'];
        $business_phone_number = $nuxpay_user['business_phone_number'];
        $business_company_size = $nuxpay_user['company_size'];
        $business_created_at = $nuxpay_user['created_at'];
        $service_charge_rate = $nuxpay_user['service_charge_rate'];
        $business_email = $nuxpay_user['business_email'];

        // if($address1){
        //     $address_arr[] = $address1;
        // }

        // if($address2){
        //     $address_arr[] = $address2;
        // }

        // if($postal){
        //     $address_arr[] = $postal;
        // }

        // if($city){
        //     $address_arr[] = $city;
        // }

        // if($state){
        //     $address_arr[] = $state;
        // }

        // if($country){
        //     $address_arr[] = $country;
        // }

        // if($address_arr){
        //     $business_address = implode(", ", $address_arr);
        // }
        
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
            "owner_mobile" => $business_phone_number ? $business_phone_number : '',
            "owner_email" => $business_email ? $business_email : '',
            "reseller" => $reseller,
            "site" => $site,
            "distributor_username" => $distributor_name,
            "business_id" => $business_id,
            "name" => $business_name,
            "address1" => $address1 ? $address1 : '',
            "address2" => $address2 ? $address2 : '',
            "city" => $city ? $city : '',
            "state" => $state ? $state : '',
            "postal" => $postal ? $postal : '',
            "website" => $business_website ? $business_website : '',
            "phone_number" => $business_phone_number ? $business_phone_number : '',
            "email" => $business_email ? $business_email : '',
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


        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00276') /*Admin Nuxpay Merchant Details.*/, 'data' => $merchant_details);

    }

    public function admin_nuxpay_dashboard_statistics($params){
        global $xunCurrency;
        $db= $this->db;
        $setting = $this->setting;

        $to_date           = $params["date_to"];
        $from_date         = $params["date_from"];

        if($from_date){
            $from_datetime = date("Y-m-d H:i:s", $from_date);
            $db->where('created_at', $from_datetime, '>');

        }

        if($to_date){
            $to_datetime  = date("Y-m-d H:i:s", $to_date);
            $db->where('created_at' , $to_datetime, '<=');
        }

        $copyDb = $db->copy();
        $copyDb2 = $db->copy();
        // $withdrawalDb = $db->copy();
        // $withdrawalDb2 = $db->copy();
        
        //fund in commision;
        $db->orderBy('id', 'ASC');
        $crypto_history = $db->get('xun_crypto_history', null, 'business_id, wallet_type, amount_receive as amount,transaction_fee, tx_fee_wallet_type, status, created_at');

        //withdrawal commission
        // $withdrawalDb->where('status', 'confirmed');
        // $crypto_withdrawal_details = $withdrawalDb->get('xun_payment_gateway_withdrawal', null, 'business_id, wallet_type, amount, service_charge_amount as transaction_fee, service_charge_wallet_type as tx_fee_wallet_type, status, created_at');
        // $withdrawalDb2->groupBy('status');
        // $withdrawal_total_transaction = $withdrawalDb2->map('status')->ArrayBuilder()->get('xun_payment_gateway_withdrawal', null, 'CONCAT(UPPER(SUBSTRING(status,1,1)),LOWER(SUBSTRING(status,2))) as status, count(id)');

        //fundout commission
        $copyDb->where('status', 'confirmed');
        $crypto_fund_out_details = $copyDb->get('xun_crypto_fund_out_details', null, 'business_id, wallet_type, amount, service_charge_amount as transaction_fee, service_charge_wallet_type as tx_fee_wallet_type, status, created_at');
        $copyDb2->groupBy('status');
        $fund_out_total_transaction = $copyDb2->map('status')->ArrayBuilder()->get('xun_crypto_fund_out_details', null, 'CONCAT(UPPER(SUBSTRING(status,1,1)),LOWER(SUBSTRING(status,2))) as status, count(id)');
        $marketplace_currencies = $xunCurrency->get_cryptocurrency_list('a.id, a.currency_id, a.symbol');
        $crypto_rate = $xunCurrency->get_cryptocurrency_rate_with_stable_coin($marketplace_currencies);

        $total_profit = 0;
        $total_transaction = 0;
        $total_transacted_amount = 0;
        $business_id_arr = [];
        $wallet_type_arr = [];
        $len = count($crypto_history);
        $end = $len-1;
        $is_all_time = 0;//is all time or filter by days
        $total_fund_in_reseller_id = [];

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
            //$db->where('a.reseller_id', $arr_reseller_id, "IN");
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

        // withdrawal
        // foreach($crypto_withdrawal_details as $key => $value){

        //     if(!$from_datetime && !$to_datetime){
        //         $is_all_time = 1;
        //         if($key == 0){
        //             $start_created_at = $value['created_at'];
        //             $from_date = strtotime($start_created_at);
        //         }
    
        //         if($key == $end){
        //             $end_created_at = $value['created_at'];
        //             $to_date = strtotime($end_created_at);
        //         }
        //     }
          
            
        //     $wallet_type = strtolower($value['wallet_type']); //change wallet_type to tx_fee wallet_type
        //     $transaction_fee = $value['transaction_fee'];
        //     $status = $value['status'];
        //     $amount = $value['amount'];
        //     $business_id = $value['business_id'];

        //     if($status == 'success'){

        //         $amount_profit_by_coin = $total_profit_by_coin[$wallet_type]['amount'];
        //         $converted_total_profit_usd =  bcmul($transaction_fee, $crypto_rate[$wallet_type], 2);
        //         $total_profit = bcadd($total_profit, $converted_total_profit_usd, 2);
        //         $amount_usd = bcmul($amount, $crypto_rate[$wallet_type], 2);
       
        //         $total_withdrawal_transacted_amount = bcadd($total_withdrawal_transacted_amount, $amount_usd, 2);
        //         if(!$total_profit_by_coin[$wallet_type]){
        //             $total_profit_by_coin[$wallet_type]['amount'] = $converted_total_profit_usd;
        //         }
        //         else{
        //             $new_amount_profit = bcadd($amount_profit_by_coin, $converted_total_profit_usd, 2);
        //             $total_profit_by_coin[$wallet_type]['amount'] = $new_amount_profit;
        //         }

        //         if(!$total_profit_by_merchant[$business_id]){
        //             $total_profit_by_merchant[$business_id]['amount'] = $converted_total_profit_usd;
        //         }
        //         else{
        //             $total_profit_amount_by_merchant = $total_profit_by_merchant[$business_id]['amount'];
        //             $new_amount_profit = bcadd($total_profit_amount_by_merchant, $converted_total_profit_usd, 2);
        //             $total_profit_by_merchant[$business_id]['amount'] = $new_amount_profit;
        //         }
            
        //         if(!$total_withdrawal_tx_amount_by_coin[$wallet_type]){
        //             $total_withdrawal_tx_amount_by_coin[$wallet_type] = array(
        //                 "amount" => $amount_usd,
        //             );

        //         }
        //         else{
        //             $total_withdrawal_amount_usd = $total_withdrawal_tx_amount_by_coin[$wallet_type]["amount"];
    
        //             $new_total_amount_usd = bcadd($total_withdrawal_amount_usd, $amount_usd, 2);
        //             $total_withdrawal_tx_amount_by_coin_arr = array(
        //                 "amount" => $new_total_amount_usd,
        //             );
    
        //             $total_withdrawal_tx_amount_by_coin[$wallet_type] = $total_withdrawal_tx_amount_by_coin_arr;
        //         }

        //         if(!in_array($business_id, $business_id_arr)){
        //             array_push($business_id_arr, $business_id);
        //         }

        //         if(!in_array($wallet_type, $wallet_type_arr)){
        //             array_push($wallet_type_arr, $wallet_type);
        //         }
    
        //     }
        // }
        
        if($business_id_arr){
            $db->where('id', $business_id_arr, 'IN');
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

        // withdrawal
        // $total_withdrawal_transaction = 0;
        // foreach($withdrawal_total_transaction as $status_key => $status_value){
        //     $total_withdrawal_transaction = bcadd($total_withdrawal_transaction, $status_value);
        // }
        
        // foreach($withdrawal_total_transaction as $status_key => $status_value){

        //     $statusAmountOverTotalTransaction = bcdiv($status_value, $total_withdrawal_transaction, 8);
        //     $status_percentage = bcmul($statusAmountOverTotalTransaction, 100, 2);

        //     $total_withdrawal_transaction_by_status[$status_key]['amount'] = $status_value;
        //     $total_withdrawal_transaction_by_status[$status_key]['percentage'] = $status_percentage;
        //     $total_withdrawal_transaction_by_status[$status_key]['status']= $status_key;
        // }

        // $total_withdrawal_transaction_by_status = array_values($total_withdrawal_transaction_by_status);

        foreach($total_transaction_by_status as $status_key => $status_value){

            $status_amount = $status_value['amount'];
            $statusAmountOverTotalTransaction = bcdiv($status_amount, $total_fund_out_transaction, 8);
            // $statusAmountOverTotalTransaction = bcdiv($status_amount, $total_withdrawal_transaction, 8);
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

        // withdrawal
        // $total_transaction_percentage = 100;
        // foreach($total_withdrawal_tx_amount_by_coin as $tx_key => $tx_value){
        //     $tx_amount = $tx_value["amount"];
        //     $txAmountOverTotalTransacted = bcdiv($tx_amount, $total_withdrawal_transacted_amount, 4);
        //     $tx_percentage = bcmul($txAmountOverTotalTransacted, 100, 4);
        //     $symbol = strtoupper($marketplace_currencies[$tx_key]['symbol']);

        //     $total_transaction_percentage = bcsub($total_transaction_percentage, $tx_percentage, 4);
        //     $total_withdrawal_tx_amount_by_coin [$tx_key]['percentage']= $tx_percentage;
        //     $total_withdrawal_tx_amount_by_coin [$tx_key]['symbol']= $symbol;
        // }

        // usort($total_withdrawal_tx_amount_by_coin, function($a, $b) {
        //     return $a['amount'] < $b['amount'];
        // });

        // foreach($total_withdrawal_tx_amount_by_coin as $tx_key => $tx_value){

        //     if($tx_key > 4){
        //         $amount = $tx_value['amount'];
        //         $percentage = $tx_value['percentage'];
        //         $others_total_amount = bcadd($others_total_amount,$amount, 2 );
        //         // $others_total_percentage = bcadd($others_total_percentage, $percentage, 2);
        //         $remaining_percentage = $total_transaction_percentage;
        //         unset($total_withdrawal_tx_amount_by_coin[$tx_key]);
        //         $total_withdrawal_tx_amount_by_coin[5] = array(
        //             "amount" => $others_total_amount,
        //             "percentage" => $remaining_percentage,
        //             "symbol" => "Others",
        //         );
        //     }

        // }

        // if($total_withdrawal_tx_amount_by_coin[5]['amount'] <= 0){
        //     unset($total_withdrawal_tx_amount_by_coin[5]);
        // }

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

        // withdrawal
        // foreach($crypto_withdrawal_details as $crypto_key => $crypto_value){
        //     $created_at = $crypto_value['created_at'];
            
        //     $transaction_fee = $crypto_value["transaction_fee"];
        //     $wallet_type = $crypto_value['wallet_type'];
        //     $status = ucfirst($crypto_value['status']);
        //     if($is_all_time == 1){
        //         $date = date("Y-m-d 00:00:00", strtotime($created_at));
        //     }
        //     else{
        //         $date = date("Y-m-d H:00:00",strtotime($created_at));
        //     }

        //     if($status == 'Confirmed'){
        //         $converted_total_profit_usd =  bcmul($transaction_fee, $crypto_rate[$wallet_type], 2);

        //         if($profit_list[$date]){
        //             $profit_amount = $profit_list[$date]['value'];
        //             $total_amount = $profit_amount + $converted_total_profit_usd;
        //             $profit_list[$date]["value"] = strval($total_amount);
                
        //         }
        //     }
            
        // }
        // unset($crypto_withdrawal_details);

         $chart_data = array_values($profit_list);

        $total_transacted_amount_data = array(
            "total_transacted_amount"=> $total_transacted_amount,
            "piechart_data" => $total_transaction_amount_by_coin,
        );

        $total_transaction_by_status_data = array(
            "total_transaction" => $total_transaction,
            "piechart_data" => $total_transaction_by_status,
        );

       // print_r($total_fund_out_tx_amount_by_coin);
        $total_fund_out_transacted_amount_data = array(
            "total_transacted_amount"=> $total_fund_out_transacted_amount,
            "piechart_data" => $total_fund_out_tx_amount_by_coin,
        );

        $total_fund_out_tx_by_status_data = array(
            "total_transaction" => $total_fund_out_transaction,
            "piechart_data" => $total_fund_out_transaction_by_status,
        );

        // withdrawal
        // $total_withdrawal_transacted_amount_data = array(
        //     "total_transacted_amount"=> $total_withdrawal_transacted_amount,
        //     "piechart_data" => $total_withdrawal_tx_amount_by_coin,
        // );

        // $total_withdrawal_tx_by_status_data = array(
        //     "total_transaction" => $total_withdrawal_transaction,
        //     "piechart_data" => $total_withdrawal_transaction_by_status,
        // );

        $return_data = array(
            "total_fund_in_by_reseller" => $total_fund_in_by_reseller,
            "total_profit_by_reseller"  => $total_profit_by_reseller,
            "chart_data" => $chart_data,
            "amount_receive_sum" => $amount_receive_sum,
            "sum"   => $sum,
            "total_profit" => $total_profit,
            "total_profit_by_coin" => $total_profit_by_coin,
            "total_profit_by_merchant" => $total_profit_by_merchant,
            "total_transacted_amount_data" => $total_transacted_amount_data,
            "total_transaction_by_status_data" => $total_transaction_by_status_data,
            "total_fund_out_transacted_amount_data" => $total_fund_out_transacted_amount_data,
            "total_fund_out_tx_by_status_data" => $total_fund_out_tx_by_status_data,
            // "total_withdrawal_transacted_amount_data" => $total_withdrawal_transacted_amount_data,
            // "total_withdrawal_tx_by_status_data" => $total_withdrawal_tx_by_status_data,
        );

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00278') /*Admin Nuxpay Dashboard.*/, 'data' => $return_data);

    }

    public function create_admin($params){
        $db = $this->db;

        $username = $params['username'];
        $name = $params['name'];
        $email = $params['email'];
        $password = $params['password'];
        $confirm_password = $params['confirm_password'];
        $role_id = $params['role_id'];
        $status = $params['status'];

        if($username == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00404') /*Username cannot be empty.*/, 'developer_msg' => 'Username cannot be empty.');
        }

        if($name == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00506') /*Name cannot be empty.*/, 'developer_msg' => 'Name cannot be empty.');
        }

        if($password == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00014') /*Password cannot be empty.*/, 'developer_msg' => 'Password cannot be empty.');
        }

        if($confirm_password == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00068') /*Confirm password cannot be empty*/, 'developer_msg' => 'Confirm password cannot be empty');
        }

        if(!$role_id){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00507') /*Roles ID cannot be empty.*/, 'developer_msg' => 'Roles ID cannot be empty.');
        }

        if($status == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00012') /*Status cannot be empty.*/, 'developer_msg' => 'Status cannot be empty.');
        }

        if($password != $confirm_password){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00280') /*Password does not match.*/, 'developer_msg' => 'Password does not match.');
        }

        $db->where('username', $username);
        $admin = $db->getOne('admin', 'id, username');

        if($admin){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00508') /*Please enter another username.*/, 'developer_msg' => 'Please enter another username.');
        }
        
        $hash_password = password_hash($password, PASSWORD_BCRYPT);

        $date = date("Y-m-d H:i:s");
        $insert_admin = array(
            "username" => $username,
            "name" => $name,
            "email" => $email ? $email : '',
            "password" => $hash_password,
            "role_id" => $role_id,
            "disabled" => $status == 'disable' ? 1 : 0,
            "suspended" => $status == 'suspended' ? 1 : 0,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $admin_id = $db->insert('admin', $insert_admin);

        if(!$admin_id){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => 'Something went wrong. Please try again.');
        }

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00279') /*Admin Created Successfully.*/);
    }
    
    public function admin_listing($params){
        $db= $this->db;
        $setting = $this->setting;

        $admin_page_limit      = $setting->getAdminPageLimit();
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $db->orderBy('id', 'DESC');
        $db->where('deleted', 1, '!=');
        $admin = $db->get('admin', $limit, 'id, username, email, role_id, disabled, suspended, deleted, created_at, last_login');
        
        $totalRecord = $db->getValue('admin', 'count(id)');
        $roles = $db->map('id')->ArrayBuilder()->get('roles');
 
        foreach($admin as $key => $value){
            $roles_id = $value['role_id'];
            $roles_name = $roles[$roles_id]['name'];
            $status = '';
            $disabled  = $value['disabled'];
            $suspended = $value['suspended'];
            $deleted = $value['deleted'];

            if($disabled == 1){
                $status = 'disabled';
            }

            if($suspended == 1){
                $status = 'suspended';
            }

            if($deleted == 1){
                $status = 'deleted';
            }
            $admin[$key]['roles_name']= $roles_name;
            $admin[$key]['status']= $status ? $status : 'active';
            unset($admin[$key]['role_id']);
            unset($admin[$key]['disabled']);
            unset($admin[$key]['suspended']);
            unset($admin[$key]['deleted']);
        }

        $returnData['admin_listing'] = $admin;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        
        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00281') /*Admin Listing.*/, 'data' => $returnData);

    }

    public function admin_roles_listing($params){
        $db = $this->db;

        $db->orderBy('id', 'ASC');
        $roles_listing = $db->get('roles', null, 'id, name, disabled, deleted');

        foreach($roles_listing as $key => $value){
            $disabled = $value['disabled'];
            $deleted = $value['deleted'];

            if($disabled == 1){
                $status = 'disabled';
            }

            if($deleted == 1){
                $status = 'deleted';
            }

            $roles_listing[$key]['status'] = $status ? $status : 'active';
            unset($roles_listing[$key]['disabled']);
            unset($roles_listing[$key]['deleted']);
        }

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00280') /*Admin Roles Listing.*/, 'data' => $roles_listing);
    }

    public function create_role($params){
        $db= $this->db;

        $role_name = $params['name'];
        $description = $params['description'];
        $status = $params['status'];
        $role_access_arr = $params['role_access'];
        $date= date("Y-m-d H:i:s");

        if($role_name == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00509') /*Rolename cannot be empty.*/, 'developer_msg' => 'Rolename cannot be empty.');
        }

        if($description == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00318') /*Description cannot be empty.*/, 'developer_msg' => 'Description cannot be empty.');
        }

        if($status == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00012') /*Status cannot be empty.*/, 'developer_msg' => 'Status cannot be empty.');
        }

        if(!$role_access_arr){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00510') /*Role Access Array cannot be empty.*/, 'developer_msg' => 'Role Access Array cannot be empty.');
        }

        $insert_role = array(
            "name" => $role_name,
            "description" => $description,
            "site" => 'Admin',
            "created_at" => $date,
            "updated_at" => $date,
        );

        $role_id = $db->insert('roles', $insert_role);

        foreach($role_access_arr  as $permission_id){
            if($permission_id == 0){
                $db->where('site', 'Admin');
                $permission = $db->get('permissions');
   
                foreach($permission as $key => $value){
                    $id = $value['id'];
                    $insert_roles_permission = array(
                        'role_id' => $role_id,
                        "permission_id" => $id,
                        "created_at" => $date,
                        "updated_at" => $date,
                    );
        
                    $db->insert('roles_permission', $insert_roles_permission);
                }

            }
            else{
                $insert_roles_permission = array(
                    'role_id' => $role_id,
                    "permission_id" => $permission_id,
                    "created_at" => $date,
                    "updated_at" => $date,
                );
    
                $db->insert('roles_permission', $insert_roles_permission);
            }
           
          
        }
    
        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00283') /*Admin Create Roles Successfully.*/);
    }

    public function admin_permission_listing($params){
        $db= $this->db;
        
        $db->where('site', 'Admin');
        $db->where('disabled', 0);
        $permissions_listing= $db->get('permissions');

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00282') /*Admin Permission Listing.*/, 'data' => $permissions_listing);

    }

    public function admin_change_password($params){
        $db = $this->db;

        $username = $params['username'];
        $current_password = $params['current_password'];
        $new_password = $params['new_password'];
        $confirm_password = $params['confirm_password'];

        if($username == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00404') /*Username cannot be empty.*/, 'developer_msg' => 'Username cannot be empty.');
        }

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
        $admin = $db->getOne('admin');

        if(!$admin){
            return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/);
        }

        $db_password = $admin['password'];
    
        if (!password_verify($current_password, $db_password)) {
            return array("code" => 1, "status" => "error", "statusMsg" =>  $this->get_translation_message('E00070') /*Your password is incorrect. Please try again.*/);
        } 

        $update_new_password = array(
            "password" => $hash_password,
            "updated_at" => date("Y-m-d H:i:s")
        );
  
        $db->where('username', $username);
        $updated = $db->update('admin', $update_new_password);

        if(!$updated){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
        }

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00288') /*Admin Password Successfully Changed.*/);
    }

    public function create_nuxpay_user($params){
        global $post;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        // $post = $this->post;

        $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);
        global $config, $xunBusiness, $post, $xunUser, $xunXmpp, $xunPaymentGateway, $xunPay;

        $country_code = trim($params["country_code"]);
        $mobile = trim($params["mobile"]);
        $password = trim($params["pay_password"]);
        $retype_password = trim($params["pay_retype_password"]);
        $nickname = trim($params["nickname"]);
        $referral_code = trim($params["referral_code"]);
        //$type = trim($params["type"]);
        $content = trim($params["content"]);
        $country = trim($params["country"]);
        $distributor = trim($params["distributor"]);
        $reseller = trim($params["reseller"]);
        $site = trim($params["site"]);
        $email = trim($params["email"]);

        // Param validations
        if($country_code == $mobile) {
            $mobile = "";
        }

        if($reseller == "")
        {
            $reseller = $distributor;
        }

        if($email=="" && $mobile=="") {
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00545') /*Email or mobile cannot be empty.*/, 'developer_msg' => 'Email or mobile cannot be empty.');
        }
        
        if ($password == '') {
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00232') /*Password cannot be empty*/, 'developer_msg' => 'Password cannot be empty');
        }
        if ($retype_password == '') {
             return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00236') /*Retype Password cannot be empty*/, 'developer_msg' => 'Retype Password cannot be empty');
        }
        if ($nickname == '') {
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00237') /*Nickname cannot be empty*/, 'developer_msg' => 'Nickname cannot be empty');
        }
         // if ($type == '') {
        //     return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00238') /*Type cannot be empty*/, 'developer_msg' => 'Type cannot be empty');
        // }
        if ($country == '') {
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00532') /*Country cannot be empty*/, 'developer_msg' => 'Country cannot be empty');
        }

        if ($site == '') {
            return array('code' => 1, 'status' => "error", 'statusMsg' => 'Site cannot be empty', 'developer_msg' => 'Site cannot be empty');
        }


        if($mobile!="") {
            $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);
            $mobile = str_replace("-", "", $mobileNumberInfo["phone"]);

            if ($mobileNumberInfo["isValid"] == 0) {
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00046') /*Please enter a valid mobile number.*/);
            }
        }
        
        if($email!="") {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00041') /* Please enter a valid email address. */);
            }
        }

        if($reseller != ""){
            $db->where("source", $site);
            $db->where("deleted", 0);
            $db->where("type", "reseller");
            $db->where("status", "approved", "<>");
            $db->where("username", $reseller);
            $resellerDetail = $db->getOne("reseller");

            if ($resellerDetail){
                return array('code' => 1, 'status' => "error", 'statusMsg' => "Reseller is under status pending.");
            }
        }

        if ($password != $retype_password)
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00280') /*Password and Retype Password doesn't match.*/);

        if($reseller=="" && $distributor=="") {
            $reseller_id = 0;
        } else {

            if($reseller=="") {
                return array('code' => 1, 'status' => "error", 'statusMsg' => 'Reseller username cannot be empty', 'developer_msg' => 'Reseller username cannot be empty');
            } else {

                $db->where("source", $site);
                $db->where("deleted", 0);
                $db->where("type", "distributor");
                $db->where("username", $distributor);
                $distributorDetail = $db->getOne("reseller");

                if($distributorDetail) {
                    $distributor_id = $distributorDetail['id'];
                } else {
                    $distributor_id = 0;
                }

                $db->where("distributor_id", $distributor_id);
                $db->where("source", $site);
                $db->where("deleted", 0);
                $db->where("type", "reseller");
                $db->where("username", $reseller);
                $resellerDetail = $db->getOne("reseller");
		
		        if(!$resellerDetail)
                {
                    $db->where("source", $site);
                    $db->where("deleted", 0);
                    $db->where("type", "distributor");
                    $db-> where("username", $reseller);
                    $resellerDetail = $db->getOne("reseller");
                }

                if($resellerDetail) {
                    $reseller_id = $resellerDetail['id'];
                } else {
                    return array('code' => 1, 'status' => "error", 'statusMsg' => 'Reseller username not exist', 'developer_msg' => 'Reseller username not exist');
                }

            }
        }


        $companyName = $setting->systemSetting['payCompanyName'];
        $new_params["companyName"] = $companyName;
        $new_params["mobile"] = $mobile;
        //$new_params["verify_code"] = $verify_code;
        $new_params["nickname"] = $nickname;
        $new_params["user_check"] = 0;
        $new_params["content"] = $content;
        $new_params["from_nuxpay_admin"] = 1;

        $mobile_verified = 0;
        $email_verified = 0;

        if($mobile!="") {

            $db->where('u.register_site', $site);
            $db->where('u.type', 'business');
            $db->where("u.username", $mobile);
            $db->where("a.main_mobile_verified", 1);
            $db->join("xun_business_account a", "a.user_id=u.id", "INNER");
            $result = $db->getOne("xun_user u");

            if ($result) {
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00239') /*An account already exists with this phone number. Please select another phone number.*/, 'developer_msg' => 'An account already exists with this phone number. Please select another phone number.');
            }

            $mobile_verified = 1;
        }
        
        if($email!="") {

            $db->where('u.register_site', $site);
            $db->where('u.type', 'business');
            $db->where("u.email", $email);
            $db->where("a.email_verified", 1);
            $db->join("xun_business_account a", "a.user_id=u.id", "INNER");
            $result = $db->getOne("xun_user u");

            if ($result) {
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00043') /* An account already exists with this email. Please select another email address. */, 'developer_msg' => 'An account already exists with this email. Please select another email address.');
            }
            
            $email_verified = 1;
        }

        // $db->where('type', 'user');
        // $db->where('username', $mobile);
        // $xun_user = $db->getOne('xun_user');

        // if(!$result){
        //     $user_result = $xunUser->register_verifycode_verify($new_params);
        //     if ($user_result['code'] != 1)
        //         return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00230') /*SMS Code Verify Failed.*/, 'developer_msg' => 'SMS Code Verify Failed.');
        //     if(!$xun_user){
        //         $erlang_params['username'] = $mobile;
        //         $erlang_post = $post->curl_post("user/register", $erlang_params);

        //         if ($erlang_post["code"] == 0)
        //          return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00231') /*Register New User Failed*/, 'developer_msg' => 'Register New User Failed');
        //     }
           
        //  }
        //else{
        //     $verify_code_return = $xunUser->verify_code($mobile, $verify_code, $ip, $user_agent, "New", "NuxPay");
        //     if ($verify_code_return["code"] === 0) {
        //         return $verify_code_return;
        //     }
        // }

        // Password validation
        $validate_password = $xunPay->validate_password($password, $retype_password);

        if ($validate_password['code'] == 0) {
            $error_message = $validate_password['error_message'];
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00240') /*Invalid password combination.*/, 'developer_msg' => 'Invalid password combination.');

        }
        $password = password_hash($password, PASSWORD_BCRYPT);

        $created_at = date("Y-m-d H:i:s");
        $server = $config["server"];

        $service_charge_rate = $setting->systemSetting['theNuxCommissionFeePct'];
        $insertUserData = array(
            "username" => $mobile,
            "email" => $email,
            "email_verified" => $email_verified,
            "server_host" => $server,
            "type" => "business",
            "register_site" => $site,
            "register_through" => $content,
            "nickname" => $nickname,
            "reseller_id" => $reseller_id,
            "web_password" => $password,
            "service_charge_rate" => $service_charge_rate,
            "created_at" => $created_at,
            "updated_at" => $created_at,
        );

        // create nuxpay user
        $user_id = $db->insert("xun_user", $insertUserData);
        if(!$user_id) {
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00241') /*Failed to create account*/, 'developer_msg' => $db->getLastError());
        }

        // Insert user setting - changed password
        $insertData = array(
            'user_id' => $user_id,
            'name' => 'hasChangedPassword',
            'value' => ($signup_type == 'requestFund' || $signup_type == 'landingPage' || $signup_type == 'sendFund') ? '0' : (($signup_type == 'newSignup') ? '2' : '1'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        $db->insert('xun_user_setting', $insertData);

        $fields = array("user_id", "email" , "email_verified", "password", "main_mobile", "main_mobile_verified", "referral_code", "created_at", "updated_at");
        $values = array($user_id, $email, $email_verified, $password, $mobile, $mobile_verified, $referral_code, $created_at, $created_at);
        $arrayData = array_combine($fields, $values);
        $db->insert("xun_business_account", $arrayData);
        
        // // Insert User setting - showWallet
        $db->where("is_payment_gateway", 1);
        $xun_coins = $db->get('xun_coins', null, 'currency_id');
        $coin_list = array_column($xun_coins,'currency_id');

        $insertArray = array(
            'user_id' => $user_id,
            'name' => 'showWallet',
            'value' => json_encode($coin_list),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
            
        );
        $db->insert('xun_user_setting', $insertArray); //update user setting

        // // Insert User setting - showWallet
        $db->where("is_payment_gateway", 1);
        $xun_coins = $db->get('xun_coins', null, 'currency_id');
        $coin_list = array_column($xun_coins,'currency_id');

        $insertWalletArray = array(
            'user_id' => $user_id,
            'name' => 'showNuxpayWallet',
            'value' => json_encode($coin_list),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
            
        );
        $db->insert('xun_user_setting', $insertWalletArray); //update user setting

        // Insert user setting - changed password
        $insertArray = array(
            'user_id' => $user_id,
            'name' => 'allowSwitchCurrency',
            'value' => '0',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
            
        );
        $db->insert('xun_user_setting', $insertArray); //update user setting

        // // create business
        $insertBusinessData = array(
            "user_id" => $user_id,
            "name" => $nickname,
            "country" => $country,
            "created_at" => $created_at,
            "updated_at" => $created_at
        );

        $business_details_id = $db->insert("xun_business", $insertBusinessData);
        if (!$business_details_id)
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00242') /*Something went wrong.*/, 'developer_msg' => $db->getLastError());
        

        $wallet_return = $xunCompanyWallet->createUserServerWallet($user_id, 'nuxpay_wallet', '');

        $internal_address = $wallet_return['data']['address'];
        
        $insertAddress = array(
            "address" => $internal_address,
            "user_id" => $user_id,
            "address_type" => 'nuxpay_wallet',
            "active" => '1',
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        );

        $inserted = $db->insert('xun_crypto_user_address', $insertAddress);

        if(!$inserted){
            return array('code' => 0, 'status' => 'error', 'statusMsg' => $this->get_translation_message('E00242')/*Something went wrong.*/, "developer_msg" => $db->getLastQuery());
        }
        //$business_verify =  $xunBusiness->business_mobile_verifycode_verify($new_params, "nuxpay");

        // $access_token = $general->generateAlpaNumeric(32);

        // $access_token_expires_at = date("Y-m-d H:i:s", strtotime('+12 hours', strtotime(date("Y-m-d H:i:s"))));

        // $fields = array("business_email", "business_id", "access_token", "expired_at");
        // $values = array('', $business_id, $access_token, $access_token_expires_at);

        // $insertData = array_combine($fields, $values);

        // $row_id = $db->insert("xun_access_token", $insertData);

        // $returnData = array(
        //     "business_id" => $user_id,
        //     "mobile" => $mobile, 
        //     "name" => $nickname, 
        //     "access_token" => $access_token
        // );

        // $xunPaymentGateway->update_user_setting($user_id, $ip, $user_agent);

        // $user_country_info_arr = $xunUser->get_user_country_info([$mobile]);
        // $user_country_info = $user_country_info_arr[$mobile];
        // $user_country = $user_country_info["name"];

        // $message = "Username: " .$nickname. "\n";
        // $message .= "Phone number: " .$mobile. "\n";
        // $message .= "IP: " . $ip . "\n";
        // $message .= "Country: " . $user_country . "\n";
        // $message .= "Device: " . $user_agent . "\n";
        // $message .= "Type of user: " .$insertUserData["type"] . "\n";
        // $message .= "Time: " . date("Y-m-d H:i:s");

        // $erlang_params["tag"] = "Login";
        // $erlang_params["message"] = $message;
        // $erlang_params["mobile_list"] = array();
        // $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_pay");
        
        //print_r($curl_return);
        $message_d = $this->get_translation_message('B00115'); /*NuxPay Account successfully registered.*/
        $message_d = str_replace("%%companyName%%", $companyName, $message_d);
        return array("code" => 0, "status" => "ok", "statusMsg" => $message_d);
    }

    
    public function edit_admin_details($params){
        $db= $this->db;

        $id = $params['id'];
        $name = $params['name'];
        $role_id = $params['role_id'];
        $status = $params['status'];
        $disabled = 0;
        $suspended = 0;
        $deleted = 0;

        if($id == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00006') /*ID cannot be empty.*/, 'developer_msg' => 'ID cannot be empty.');
        }

        if($name == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00506') /*Name cannot be empty.*/, 'developer_msg' => 'Name cannot be empty.');
        }

        if($role_id == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00507') /*Roles ID cannot be empty.*/, 'developer_msg' => 'Roles ID cannot be empty.');
        }

        if($status == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00012') /*Status cannot be empty.*/, 'developer_msg' => 'Status cannot be empty.');
        }

        if($status == "disable"){
            $disabled = 1;
        }
        elseif($status == "suspend"){
            $suspended = 1;
        }
        
        $db->where('id', $id);
        $admin = $db->getOne('admin');

        if(!$admin){
            return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/);
        }

        $update_admin_details = array(
            "name" => $name,
            "role_id" => $role_id,
            "disabled" => $disabled,
            "suspended" => $suspended,
            "deleted" => $deleted,
            "updated_at" => date("Y-m-d H:i:s"),
            
        );

        $db->where('id', $id);
        $update_admin = $db->update('admin', $update_admin_details);

        if(!$update_admin){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
        }

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00289') /*Edit Admin Details Successful.*/);
    }

    public function get_admin_details($params){
        $db =$this->db;
    
        $id = $params['id'];
        
        $db->where('id', $id);
        $db->where('deleted', 0);
        $admin = $db->getOne('admin');

        if(!$admin){
            return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/);
        }

        $disabled = $admin['disabled'];
        $suspended = $admin['suspended'];

        if($disabled == 1){
            $status = 'disable';
        }
        elseif($suspended == 1){
            $status = 'suspend';
        }
        else{
            $status = 'active';
        }
        $admin['status'] = $status;
        
        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00290') /*Admin Details*/, 'data' => $admin);
    }

    public function admin_nuxpay_get_miner_fee_report($params){
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
       
        if($from_datetime){
            $from_datetime = date("Y-m-d H:i:s", $from_datetime);
            $db->where('created_at', $from_datetime, '>');
        }
    
        if($to_datetime){
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $db->where('created_at' , $to_datetime, '<=');
        }
    
        $miner_fee_arr = []; //miner fee in usd
        $miner_fee_fund_out_arr = []; //miner fee that actually company spent
        $miner_fee_collected = []; //miner fee that actually collected from transaction
        if($type == "marketer_fundout"){
    
            $db->where('address_type', 'marketer');
            $db->where('status', 'completed');
            $xun_wallet_transaction = $db->get('xun_wallet_transaction');

            if(!$xun_wallet_transaction) {
                return array('status' => 'ok', 'code' => "0", 'statusMsg' => "No Results Found", 'data' => "");
            }
    
            $feeUnitArr = [];
            $feeWalletTypeArr = [];
            foreach($xun_wallet_transaction as $key => $value){
                $fee = $value['fee'];
                $fee_unit = strtolower($value['fee_unit']);
                $fee_wallet_type = strtolower($value['wallet_type']);
    
                if(!in_array($fee_unit, $feeUnitArr)){
                    array_push($feeUnitArr, $fee_unit);
                }
    
                if(!in_array($fee_wallet_type, $feeWalletTypeArr)){
                    array_push($feeWalletTypeArr, $fee_wallet_type);
                }
            }
            
            $db->where('symbol', $feeUnitArr, 'in');
            $marketplace_currencies = $db->map('symbol')->ArrayBuilder()->get('xun_marketplace_currencies');
            
            $db->where('currency_id', $feeWalletTypeArr, 'IN');
            $marketplace_currencies1 = $db->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies');
            
            foreach($xun_wallet_transaction as $key => $value){
                $fee = $value['fee'];
                $fee_unit = strtolower($value['fee_unit']);
                $miner_fee_wallet_type = $marketplace_currencies[$fee_unit]['currency_id'];
                $miner_fee_exchange_rate = $value['miner_fee_exchange_rate'];
    
                $exchange_rate = $value['exchange_rate'];
                $wallet_type = $value['wallet_type'];
                $amount = $value['amount'];
                $created_at = $value['created_at'];
                $name = $marketplace_currencies1[$wallet_type]['name'];
                $unit = $marketplace_currencies1[$wallet_type]['symbol'];
    
                if($miner_fee_wallet_type == 'ethereum'){
                    $decimal_places = 18;
                }
                else{
                    $decimal_places = $xunCurrency->get_currency_decimal_places($miner_fee_wallet_type);
                }

                $miner_fee_value = bcmul($fee, $miner_fee_exchange_rate, 4);
                if($miner_fee_arr[$miner_fee_wallet_type]){
                    $total_miner_fee = $miner_fee_arr[$miner_fee_wallet_type]['amount'];
                    $new_miner_fee_amount = bcadd($total_miner_fee, $miner_fee_value, 4);
                    $miner_fee_arr[$miner_fee_wallet_type] = array(
                        "amount" => $new_miner_fee_amount,
                        "unit" => $fee_unit,
                        "coin_name" => $name
                    );
                }
                else{
                    $miner_fee_arr[$miner_fee_wallet_type] = array(
                        "amount" => $miner_fee_value,
                        "unit" => $fee_unit,
                        "coin_name" => $coin_name
                    );
                }
    
                if($miner_fee_fund_out_arr[$miner_fee_wallet_type]){
                    $total_miner_fee_fund_out = $miner_fee_fund_out_arr[$miner_fee_wallet_type]['amount'];
                    $new_miner_fee_amount = bcadd($total_miner_fee_fund_out, $fee, $decimal_places);
                    $miner_fee_fund_out_arr[$miner_fee_wallet_type] = array(
                        "amount" => $new_miner_fee_amount,
                        "unit" => $fee_unit,
                        "coin_name" => $name
                    );
                }
                else{
                    $miner_fee_fund_out_arr[$miner_fee_wallet_type] = array(
                        "amount" => $fee,
                        "unit" => $fee_unit,
                        "coin_name" => $coin_name
                    );
                }
               
                if($miner_fee_collected[$wallet_type]){
                    if($miner_fee_wallet_type == $wallet_type){
                        $total_miner_fee_collected = $miner_fee_collected[$wallet_type]['amount'];
                        $new_miner_fee_collected_amount = bcadd($total_miner_fee_collected, $fee, $decimal_places);
                        $miner_fee_collected[$wallet_type] = array(
                            "amount" => $new_miner_fee_collected_amount,
                            "unit" => $unit,
                            "coin_name" => $name,
                        );
                    }
                    else{
                        $miner_fee_native_value = bcdiv($miner_fee_value, $exchange_rate, $decimal_places); 
                        $total_miner_fee_collected = $miner_fee_collected[$wallet_type]['amount'];
                        $new_miner_fee_collected_amount = bcadd($total_miner_fee_collected, $miner_fee_native_value, $decimal_places);
                    
                        $miner_fee_collected[$wallet_type] = array(
                            "amount" => $new_miner_fee_collected_amount,
                            "unit" => $unit,
                            "coin_name" => $name,
                        );
                    }
                    
                }
                else{
          
                    if($miner_fee_wallet_type == $wallet_type){
                        $miner_fee_collected[$wallet_type] = array(
                            "amount" =>  $fee,
                            "unit"=> $unit,
                            "coin_name" => $name                        
                        );
                    }
                    else{
                        $miner_fee_native_value = bcdiv($miner_fee_value, $exchange_rate, 18); 
                        $miner_fee_collected[$wallet_type] = array(
                            "amount" => $miner_fee_native_value,
                            "unit" => $unit,
                            "coin_name"  => $name,
                        );
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
                "listing" => $miner_fee_listing, 
                "total_miner_fee_value" => $miner_fee_arr,
                "total_miner_fee_fund_out" => $miner_fee_fund_out_arr,
                "total_miner_fee_collected" => $miner_fee_collected,
                
            );
            
        } elseif($type == 'blockchain_fundout'){
    
            $db->where('status', 'confirmed');
            $crypto_fund_out_details = $db->get('xun_crypto_fund_out_details');
    
            $feeUnitArr = [];
            $minerFeeWalletTypeArr = [];
            foreach($crypto_fund_out_details as $key => $value){
                $miner_fee_amount = $value['pool_amount'];
                $transaction_details = json_decode($value['transaction_details'], true);
                $miner_fee_exchange_rate = $transaction_details['feeDetails']['exchangeRate']['USD'];
                $exchange_rate = $transaction_details['feeChargeDetails']['exchangeRate']['USD'];
                $feeUnit = $transaction_details['feeDetails']['unit'];
                $miner_fee_wallet_type = $value['pool_wallet_type'] ? $value['pool_wallet_type'] : $value['wallet_type'];
    
                if(!in_array($feeUnit, $feeUnitArr)){
                    array_push($feeUnitArr, $feeUnit);
                }
    
                if(!in_array($miner_fee_wallet_type, $minerFeeWalletTypeArr)){
                    array_push($minerFeeWalletTypeArr, $miner_fee_wallet_type);
                }
    
            }
    
            if($feeUnitArr){
                $db->where('symbol', $feeUnitArr, 'in');
                $marketplace_currencies = $db->map('symbol')->ArrayBuilder()->get('xun_marketplace_currencies');   
            }
    
            if($minerFeeWalletTypeArr){
                $db->where('currency_id', $minerFeeWalletTypeArr, 'IN');
                $marketplace_currencies2 = $db->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies');
            }
      
            foreach($crypto_fund_out_details as $k => $v){
    
                $miner_fee_amount = $v['pool_amount'];
                $transaction_details = json_decode($v['transaction_details'], true);
                $miner_fee_exchange_rate = $transaction_details['feeDetails']['exchangeRate']['USD'];
                $exchange_rate = $transaction_details['feeChargeDetails']['exchangeRate']['USD'];
                $feeUnit = strtolower($transaction_details['feeDetails']['unit']);
                if(!$feeUnit)
                {
                    continue;
                }
    
                $miner_fee_wallet_type =  $marketplace_currencies[$feeUnit]['currency_id'];
                $name = $marketplace_currencies[$feeUnit]['name'];
                
                $wallet_type = $v['wallet_type'];
    
                $miner_fee_value = bcmul($miner_fee_amount, $exchange_rate, 4);
    
                if($miner_fee_arr[$miner_fee_wallet_type]){
                    $total_miner_fee = $miner_fee_arr[$miner_fee_wallet_type]['amount'];
                    $new_miner_fee_amount = bcadd($total_miner_fee, $miner_fee_value, 4);
                    
                    $miner_fee_arr[$miner_fee_wallet_type] = array(
                        "amount" => $new_miner_fee_amount,
                        "unit" => $feeUnit,
                        "coin_name" => $name
                    );
                }
                else{
                    $miner_fee_arr[$miner_fee_wallet_type] = array(
                        "amount" => $miner_fee_value,
                        "unit" => $feeUnit,
                        "coin_name" => $name
                    );
                }
    
                $fund_out_miner_fee = bcdiv($miner_fee_value, $miner_fee_exchange_rate, 18);
                if($miner_fee_fund_out_arr[$miner_fee_wallet_type]){
                    $total_miner_fee_fund_out = $miner_fee_fund_out_arr[$miner_fee_wallet_type]['amount'];
                    $new_miner_fee_amount = bcadd($total_miner_fee_fund_out, $fund_out_miner_fee, 18);
                    $miner_fee_fund_out_arr[$miner_fee_wallet_type] = array(
                        "amount" => $new_miner_fee_amount,
                        "unit" => $feeUnit,
                        "coin_name" => $name
                    );
                }
                else{
                    $miner_fee_fund_out_arr[$miner_fee_wallet_type] = array(
                        "amount" => $fund_out_miner_fee,
                        "unit" => $feeUnit,
                        "coin_name" => $name
                    );
                }
    
                if($miner_fee_collected[$wallet_type]){
                    $total_miner_fee_collected = $miner_fee_collected[$wallet_type]['amount'];
                    $new_miner_fee_collected_amount = bcadd($total_miner_fee_collected, $miner_fee_amount, 18);
                    $miner_fee_collected[$wallet_type] = array(
                        "amount" => $new_miner_fee_collected_amount,
                        "unit" => $marketplace_currencies2[$wallet_type]['symbol'],
                        "coin_name" => $marketplace_currencies2[$wallet_type]['name']
                    );
                    
                }
                else{
                    $miner_fee_collected[$wallet_type] = array(
                        "amount" => $miner_fee_amount,
                        "unit" => $marketplace_currencies2[$wallet_type]['symbol'],
                        "coin_name" =>  $marketplace_currencies2[$wallet_type]['name']
                        
                    );
                
                }
    
                $data = array(
                    "total_miner_fee_value" => $miner_fee_arr,
                    "total_miner_fee_fund_out" => $miner_fee_fund_out_arr,
                    "total_miner_fee_collected" => $miner_fee_collected,
                );
            }
    
        }
        elseif ($type == 'pg_fundout'){
           
            $db->where('recipient_external', '', '!=');
            $db->where('status', 'success');
            $crypto_history = $db->get('xun_crypto_history');
            
            if($crypto_history)
            {
                $minerFeeWalletTypeArr = [];
                foreach($crypto_history as $k => $v){
                    $miner_fee_wallet_type = strtolower($v['miner_fee_wallet_type']);
        
                    if(!in_array($miner_fee_wallet_type, $minerFeeWalletTypeArr)){
                        array_push($minerFeeWalletTypeArr, $miner_fee_wallet_type);
                    }
                }
        
                $db->where('currency_id', $minerFeeWalletTypeArr, 'IN');
                $marketplace_currencies = $db->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies');

                foreach($crypto_history as $key => $value){
                    $miner_fee = $value['miner_fee'];
                    $miner_fee_wallet_type = strtolower($value['miner_fee_wallet_type']);
                    $exchange_rate = $value['exchange_rate'];
                    $miner_fee_exchange_rate = $value['miner_fee_exchange_rate'];
                    $wallet_type = strtolower($value['wallet_type']);
                    $actual_miner_fee_amount = $value['actual_miner_fee_amount'] ? $value['actual_miner_fee_amount'] : $value['miner_fee'];
                    $actual_miner_fee_wallet_type = strtolower($value['actual_miner_fee_wallet_type']) ? strtolower($value['actual_miner_fee_wallet_type']) : $miner_fee_wallet_type;
                    $actual_miner_fee_exchange_rate = $value['miner_fee_exchange_rate'] ;
                    
                    if(!$miner_fee){
                        continue;
                    }
                    $miner_fee_value = bcmul($miner_fee, $exchange_rate, 4);
        
                    if($miner_fee_arr[$miner_fee_wallet_type]){
                        $total_miner_fee = $miner_fee_arr[$miner_fee_wallet_type]['amount'];
                        $new_miner_fee_amount = bcadd($total_miner_fee, $miner_fee_value, 4);
                        $miner_fee_arr[$miner_fee_wallet_type] = array(
                            "amount" => $new_miner_fee_amount,
                            "unit" => $marketplace_currencies[$miner_fee_wallet_type]['symbol'],
                            "coin_name" => $marketplace_currencies[$miner_fee_wallet_type]['name']
                        );
                    }
                    else{
                        $miner_fee_arr[$miner_fee_wallet_type] =  array(
                            "amount" => $miner_fee_value,
                            "unit" => $marketplace_currencies[$miner_fee_wallet_type]['symbol'],
                            "coin_name" => $marketplace_currencies[$miner_fee_wallet_type]['name']
                        );
                    }
        
                    // $fund_out_miner_fee = bcdiv($miner_fee_value, $miner_fee_exchange_rate, 18);
        
                    if($actual_miner_fee_wallet_type){
                        if($miner_fee_fund_out_arr[$actual_miner_fee_wallet_type]){
                            $total_miner_fee_fund_out = $miner_fee_fund_out_arr[$actual_miner_fee_wallet_type]['amount'];
                            $new_miner_fee_amount = bcadd($total_miner_fee_fund_out, $actual_miner_fee_amount, 18);
                            $miner_fee_fund_out_arr[$actual_miner_fee_wallet_type] = array(
                                "amount" => $new_miner_fee_amount,
                                "unit" => $marketplace_currencies[$miner_fee_wallet_type]['symbol'],
                                "coin_name" => $marketplace_currencies[$miner_fee_wallet_type]['name'],
                            );
                        }
                        else{
                            $miner_fee_fund_out_arr[$actual_miner_fee_wallet_type] = array(
                                "amount" => $actual_miner_fee_amount,
                                "unit" => $marketplace_currencies[$miner_fee_wallet_type]['symbol'],
                                "coin_name" => $marketplace_currencies[$miner_fee_wallet_type]['name'],
                            );
                        }
                    }
                    
        
                    if($miner_fee_collected[$wallet_type]){
                        $total_miner_fee_collected = $miner_fee_collected[$wallet_type]['amount'];
                        $new_miner_fee_collected_amount = bcadd($total_miner_fee_collected, $miner_fee, 18);
                        $miner_fee_collected[$wallet_type] = array(
                            "amount" => $new_miner_fee_collected_amount,
                            "unit" => $marketplace_currencies[$wallet_type]['symbol'],
                            "coin_name" => $marketplace_currencies[$wallet_type]['name']
                        );
                        
                    }
                    else{
                        $miner_fee_collected[$wallet_type] = array(
                            "amount" => $miner_fee,
                            "unit" => $marketplace_currencies[$wallet_type]['symbol'],
                            "coin_name" => $marketplace_currencies[$wallet_type]['name'],
                        );
                    
                    }
        
                    $data = array(
                        "total_miner_fee_value" => $miner_fee_arr,
                        "total_miner_fee_fund_out" => $miner_fee_fund_out_arr,
                        "total_miner_fee_collected" => $miner_fee_collected,
                    );
        
                }
            }
            else
            {
                $data = array(
                    "total_miner_fee_value" => $miner_fee_arr,
                    "total_miner_fee_fund_out" => $miner_fee_fund_out_arr,
                    "total_miner_fee_collected" => $miner_fee_collected,
                );
            }
            
        }  
    
        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00291') /*Admin Miner Fee Report.*/, 'data' => $data);
    
    }

    public function nuxpay_get_miner_fee_details($params){
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;

        $admin_page_limit      = $setting->getAdminPageLimit();
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $to_datetime           = $params["date_to"];
        $from_datetime         = $params["date_from"];
        $business_id           = $params["business_id"];
        $type                  = $params['type'];
        $reseller              = $params['reseller'];
        $distributor              = $params['distributor'];
        $site              = $params['site'];

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        if($from_datetime){
            $from_datetime = date("Y-m-d H:i:s", $from_datetime);
            $db->where('d.created_at', $from_datetime, '>');
        }

        if($to_datetime){
            $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
            $db->where('d.created_at' , $to_datetime, '<=');
        }

        if($type == "marketer_fundout"){

            //if($reseller) {
            //    $db->where("r.username", $reseller);
            //}

            $db->where('d.address_type', 'marketer');
            $db->where('d.status', 'completed');
            //$db->join("xun_user u", "d.user_id=u.id", "INNER");
            //$db->join("reseller r", "r.id=u.reseller_id", "INNER");
            //$db->join("reseller r2", "r2.id=r.distributor_id", "LEFT");

            //if($distributor) {
            //    $db->where("r2.username", $distributor);
            //}

            //if($site) {
            //    $db->where("r.source", $site);
            //}

            $copyDb= $db->copy();
            $db->orderBy('d.id' , "DESC");
            //$xun_wallet_transaction = $db->get('xun_wallet_transaction d', $limit, "d.*, r.username as reseller_name, IF(r2.username is null, '-', r2.username) as distributor_name, IF(r.source is null, '-', r.source) as site_name");
            $xun_wallet_transaction = $db->get('xun_wallet_transaction d', $limit, "d.*");

            $totalRecord = $copyDb->getValue('xun_wallet_transaction d', 'count(d.id)');
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
                    "created_at" => $created_at//,
                    //"reseller" => $value['reseller_name'],
                    //"distributor" => $value['distributor_name'],
                    //"site" => $value['site_name']
                );
                $miner_fee_listing[] = $miner_fee_array;

            }
        
    }
    elseif($type == 'blockchain_fundout'){

        $db->where('d.status', 'confirmed');

        //if($reseller) {
        //    $db->where("r.username", $reseller);
        //}

        
        //$db->join("xun_user u", "d.business_id=u.id", "INNER");
        //$db->join("reseller r", "r.id=u.reseller_id", "INNER");
        //$db->join("reseller r2", "r2.id=r.distributor_id", "LEFT");

        //if($distributor) {
        //    $db->where("r2.username", $distributor);
        //}

        //if($site) {
        //    $db->where("r.source", $site);
        //}


        $db->orderBy('d.id', 'desc');

        $copyDb = $db->copy();
        
        //$crypto_fund_out_details = $db->get('xun_crypto_fund_out_details d', $limit, "d.*, r.username as reseller_name, IF(r2.username is null, '-', r2.username) as distributor_name, IF(r.source is null, '-', r.source) as site_name");
        $crypto_fund_out_details = $db->get('xun_crypto_fund_out_details d', $limit, "d.*");

        $totalRecord = $copyDb->getValue('xun_crypto_fund_out_details d', 'count(d.id)');

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

        if($feeUnitArr) {
            $db->where('symbol', $feeUnitArr, 'in');
            $marketplace_currencies = $db->map('symbol')->ArrayBuilder()->get('xun_marketplace_currencies'); 
        } 
        
        
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
                "created_at" => $created_at//,
                //"reseller" => $value['reseller_name'],
                //"distributor" => $value['distributor_name'],
                //"site" => $value['site_name']

            );
            $miner_fee_listing[] = $miner_fee_array;

        }  

    } elseif($type == 'pg_fundout'){

        //if($reseller) {
        //    $db->where("r.username", $reseller);
        //}

        $db->where('d.recipient_external', '', '!=');
        $db->where('d.status', 'success');
        
        //$db->join("xun_user u", "d.business_id=u.id", "INNER");
        //$db->join("reseller r", "r.id=u.reseller_id", "INNER");
        //$db->join("reseller r2", "r2.id=r.distributor_id", "LEFT");

        //if($distributor) {
        //    $db->where("r2.username", $distributor);
        //}

        //if($site) {
        //    $db->where("r.source", $site);
        //}

        $copyDb= $db->copy();
        $db->orderBy('d.id', 'DESC');

        //$crypto_history = $db->get('xun_crypto_history d', $limit, "d.*, r.username as reseller_name, IF(r2.username is null, '-', r2.username) as distributor_name, IF(r.source is null, '-', r.source) as site_name");
        $crypto_history = $db->get('xun_crypto_history d', $limit, "d.*");
        $totalRecord = $copyDb->getValue('xun_crypto_history d', 'count(d.id)');

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
                "created_at" => $created_at//,
                //"reseller" => $value['reseller_name'],
                //"distributor" => $value['distributor_name'],
                //"site" => $value['site_name']
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

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00292') /*Admin Miner Fee Details.*/, 'data' => $data);
        
    }


    public function admin_get_withdrawal_history($params, $userID, $from_site) {

        $db = $this->db;
        $setting = $this->setting;

        $admin_page_limit       = $setting->getAdminPageLimit(); 
        $page_number            = $params["page"];
        $page_size              = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        
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
            $db->where("(h.transaction_id LIKE '%$tx_hash%' OR h.received_transaction_id LIKE '%$tx_hash%')");
        }


        if($sender_address) {
            $db->where("(h.sender_external LIKE '%$sender_address%' OR h.sender_internal LIKE '%$sender_address%')");
        }
        if($recipient_address) {
            $db->where("(h.recipient_external LIKE '%$recipient_address%' OR h.address LIKE '%$recipient_address%')");
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
        if($site) {
            $db->where("u.register_site", "%$site%", 'LIKE');
        }


        if($currency) {
            $db->where("h.wallet_type", "%$currency%", 'LIKE');
        }

        $db->where("u.type", "business");
        $db->join("xun_user u", "u.id=h.business_id", "INNER");

        $db->join("reseller r", "r.id=u.reseller_id", "LEFT");
        $db->join("reseller r2", "r2.id=r.distributor_id", "LEFT");

        $copyDb = $db->copy();
        $copyDb2 = $db->copy();
        $copyDb3 = $db->copy();


        $db->groupBy("h.status");
        $result = $db->get("xun_crypto_history h", null, "h.status, COUNT(*) as total");

        $transaction_summary = array("total"=>0, "success"=>0, "failed"=>0, "pending"=>0);
        foreach($result as $key => $value){
            $status = strtolower($value['status']);
            if($status=="success") {
                $transaction_summary['success'] = $value['total'];
                $transaction_summary['total'] += $value['total'];
            } else if($status=="failed") {
                $transaction_summary['failed'] = $value['total'];
                $transaction_summary['total'] += $value['total'];
            } else {
                $transaction_summary['pending'] = $value['total'];
                $transaction_summary['total'] += $value['total'];
            }
        
        }

        

        $db->groupBy("h.wallet_type");
        $result2 = $copyDb->get("xun_crypto_history h", null, "h.wallet_type, SUM(h.amount_receive) as total_amount_receive, SUM(h.transaction_fee) as total_transaction_fee, SUM(h.miner_fee) as total_miner_fee, SUM(h.amount) as total_net_amount");

        $currency_summary = array();
        foreach($result2 as $key => $value) {

            $currency_summary[] = array("currency"=>$value['wallet_type'], "total_amount"=>$value['total_amount_receive'], "total_processing_fee"=>$value['total_transaction_fee'], "total_miner_fee"=>$value['total_miner_fee'], "total_net_amount"=>$value['total_net_amount']);

        }

        
        $totalRecord = $copyDb2->getValue("xun_crypto_history h", "COUNT(*)");


        $result3 = $copyDb3->get("xun_crypto_history h", $limit, "h.created_at, u.register_site, u.reseller_id, u.id, u.nickname, h.wallet_type, h.amount_receive, h.transaction_fee, h.miner_fee, IF(h.sender_external='', h.sender_internal, h.sender_external) as sender_address, IF(h.recipient_external='', h.address, h.recipient_external) as recipient_address, h.transaction_id, h.received_transaction_id, h.status, IF(r.username is null, '-', r.username) as reseller_username, IF(r2.username is null, '-', r2.username) as distributor_username");

        $arr_transaction = array();

        foreach($result3 as $key => $value) {

            $history['date'] = $value['created_at'];
            $history['site'] = $value['register_site'];
            $history['distributor'] = $value['distributor_username'];
            $history['reseller'] = $value['reseller_username'];
            $history['merchant_id'] = $value['id'];
            $history['merchant_name'] = $value['nickname'];
            $history['currency'] = $value['wallet_type'];
            $history['actual_amount'] = $value['amount_receive'];
            $history['processing_fee'] = $value['transaction_fee'];
            $history['miner_fee'] = $value['miner_fee'];
            $history['recipient_address'] = $value['recipient_address'];
            $history['tx_hash'] = $value['transaction_id'];
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

    public function admin_get_withdrawal_history2($params, $userID, $from_site) {

        $db = $this->db;
        $setting = $this->setting;

        $admin_page_limit       = $setting->getAdminPageLimit(); 
        $page_number            = $params["page"];
        $page_size              = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        
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
        if($site) {
            $db->where("u.register_site", "%$site%", 'LIKE');
        }


        if($currency) {
            $db->where("h.wallet_type", "%$currency%", 'LIKE');
        }

        $db->where("u.type", "business");
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

            $currency_summary[] = array("currency"=>$value['wallet_type'], "total_amount"=>$value['total_amount_receive'], "total_processing_fee"=>$value['total_processing_fee'], "total_miner_fee"=>$value['total_miner_fee'], "total_net_amount"=>($value['total_amount_receive'] - $value['total_miner_fee'] - $value['total_processing_fee']));

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

    public function admin_get_fund_out_listing($params){
        $db = $this->db;
        $setting = $this->setting;

        $admin_page_limit       = $setting->getAdminPageLimit(); 
        $page_number            = $params["page"];
        $page_size              = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $to_datetime            = $params["date_to"]; 
        $from_datetime          = $params["date_from"]; 
        $business_id            = $params['business_id'];    
        $status                 = $params['status']; 
        $business_name          = $params['business_name']; 
        $reseller               = $params["reseller"]; 
        $distributor            = $params["distributor"]; 
        $site                   = $params["site"]; 
        $tx_hash                = $params["tx_hash"]; 
        $recipient_address      = $params["recipient_address"]; 
        $currency               = $params["currency"];
        $status                 = $params["status"];

        // if($business_id == ''){
        //     return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/, 'developer_msg' => 'Business ID cannot be empty.');
        // }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

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
            $db->where('a.business_id', "%$business_id%", 'LIKE');
        }
        
        if($reseller) {
            $db->where("r.username", "%$reseller%", 'LIKE');
        }

        $db->orderBy('a.created_at', 'DESC');
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
        $fund_out_details = $db->get('xun_crypto_fund_out_details a', $limit, 'a.business_id, a.sender_address, a.recipient_address, a.amount, a.wallet_type, a.service_charge_amount, a.pool_amount, a.status, a.tx_hash, a.created_at, b.nickname, IF(r.username is null, "-", r.username) as reseller_name, IF(r2.username is null, "-", r2.username) as distributor_name, b.register_site as site');

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


        // $test = array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00307') /*Admin Fund Out Listing*/, 'data' => $data);
        //         echo json_encode($test);
                
        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00293') /*Admin Fund Out Listing.*/, 'data' => $data);

    }

    public function admin_buy_sell_crypto_listing($params){
        $db = $this->db;
        $setting = $this->setting;

        $admin_page_limit       = $setting->getAdminPageLimit(); 
        $page_number            = $params["page"];
        $page_size              = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $to_datetime            = $params["date_to"]; 
        $from_datetime          = $params["date_from"]; 
        $type                   = $params["type"];
        $providerId             = $params["provider_id"];
        $status                 = $params['status']; 



        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

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
        
        if($type){
            $db->where('a.type', "%$type%" , 'LIKE');
        }

        if($providerId != ""){
            if($providerId == 'simplex'){
                $db->where('a.provider_id', "26" , 'LIKE');
            }
            if($providerId == 'xanpool'){
                $db->where('a.provider_id', "27" , 'LIKE');
            }
        }

        $copyDb = $db->copy();
        $buy_sell_crypto_listing = $db->get('xun_crypto_payment_transaction a', $limit, 'a.id, a.business_id, a.fiat_amount, a.fiat_currency, a.fee_amount, a.fee_currency, a.crypto_amount, a.wallet_type, a.quote_id, a.payment_id, a.reference_id, a.type, a.status, a.destination_address, a.provider_id, a.created_at, 
        CASE WHEN a.auto_selling = "1" THEN "Auto Sell"
        ELSE "Manual Sell"
        END AS auto_selling');

        if(!$buy_sell_crypto_listing){
            return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/, 'data' => '');
        }

        $totalRecord = $copyDb->getValue('xun_crypto_payment_transaction a', 'count(a.id)');
       

        $data = array(
            "buy_sell_crypto_listing" => $buy_sell_crypto_listing,
            "totalRecord" => $totalRecord,
            "numRecord" => $page_size,
            "totalPage" => ceil($totalRecord/$page_size),
            "pageNumber" => $page_number,
        );

                
        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00293') /*Admin Fund Out Listing.*/, 'data' => $data);

    }

    public function sell_crypto_confirmation($params){
        global $xanpool;
       
        $db = $this->db;
        $post = $this->post;
        $setting = $this->setting;
        $general = $this->general;
        $transactionID = $params["id"];

        $db->where("id", $transactionID);
        $sellCrypto = $db->getOne('xun_crypto_payment_transaction');

        $providerString = $sellCrypto['provider_response_string'];
        $status = $sellCrypto['status'];
        $providerId = $sellCrypto['provider_id'];
        $wallettype = $sellCrypto['wallet_type'];
        //$destinationAddress = $sellCrypto['destination_address'];
        // $test = $providerString['bitcoin'];

        $db->where("id", $params['id']);
        $destinationAddress = $db->getValue('xun_crypto_payment_transaction', 
        "JSON_UNQUOTE(
             JSON_EXTRACT(
                provider_response_string, 
                '$.payload.depositWallets.$wallettype'
            )
        )"
        );

        // $db->where('unit', strtolower('BTC'));
        // $cryptoValue = $db->get('xun_cryptocurrency_rate');
        // $cryptoValue = $cryptoValue['value'];

        if($status != "pending"){// check if status is pending
            return array("code" => 1, "status" => "failed", "statusMsg" => "status is incorrect");
        }

        if($providerId != "27"){ // check if provider is xanpool(xanpool id =27)
            return array("code" => 1, "status" => "failed", "statusMsg" => "provider_id is incorrect");
        }

        if($providerString != "" && $providerString != NULL){ //check if provide string is empty
            $data = array(
                // "sell_transaction_details" => $sellCrypto,
                "payment_id" => $sellCrypto['payment_id'],
                "reference_id" => $sellCrypto['reference_id'],
                "destination_address" => $destinationAddress,
                "crypto_amount" => $sellCrypto['crypto_amount'],
                "symbol" => $sellCrypto['wallet_type'],
                "fiat_amount" => $sellCrypto['fiat_amount'],
                "fiat_currency" => $sellCrypto['fiat_currency'],
                "transaction_token" => $sellCrypto['transaction_token'],//???
            );

            $sellCryptoTransactionDataReturn = $xanpool->transfer_sell_crypto($data);

            if($sellCryptoTransactionDataReturn['message'] == "SUCCESS"){
                return array("code" => 1, "status" => "ok", "statusMsg" => $data);
            }else{
                return array("code" => 0, "status" => "failed", "statusMsg" => "data not send");
            }

            return array("code" => 0, "status" => "ok", "statusMsg" => "data sent", "data" => $params);
        }else{
            return array("code" => 1, "status" => "failed", "statusMsg" => "provider_string is empty");
        }

    }

    public function get_sites($params){
        $db = $this->db;

        if($params['site']!="") {
            $db->where("source", $params['site']);
        }

        $data = $db->get("site", null, "source");
        $return = array();
        foreach ($data as $key => $value){
            foreach ($value as $key2 => $value2){
                $return[] = $value2;
            }
        }

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00316') /*Sites list.*/,'data' => $return);
    }

    public function admin_address_listing($params){
        $db = $this->db;
        $setting = $this->setting;

        $admin_page_limit      = $setting->getAdminPageLimit();
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $created_datetime      = $params["date_created"];
        $business_id           = $params['business_id']; 
        $business_name         = $params['business_name'];
        $phone_number          = $params['phone_number'];
        $wallet_type           = $params['coin_type'];
        $wallet_address        = $params['wallet_address']; 
        // RECEIVED FUND IN 
        $status                = $params['status'];

        // if($business_id == ''){
        //     return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/, 'developer_msg' => 'Business ID cannot be empty.');
        // }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        // if($from_datetime){
        //     $from_datetime = date("Y-m-d H:i:s", $from_datetime);
        //     $db->where('a.created_at', $from_datetime, '>');
        // }

        // if($to_datetime){
        //     $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
        //     $db->where('a.created_at' , $to_datetime, '<=');
        // }

        if($created_datetime){
            $created_datetime = date("Y-m-d H:i:s", $created_datetime);
            $db->where('a.created_at', $created_datetime);
        }

        // XUN_CRYPTO_HISTORY
        if($business_id){
            $db->where('a.business_id', $business_id);
        }

        // XUN_BUSINESS
        if($business_name){
            $db->where('b.name', "%$business_name%" , 'LIKE');
        }

        // RECEIVED FUND IN
        if($status == 'Yes'){
            $db->where('a.status', "success");
        }else{
            $db->where('a.status', Array("received, failed, pending"), 'IN');
        }

        if($phone_number){
            $db->where('b.phone_number', $phone_number);
        }

        if($wallet_type){
            $db->where('a.wallet_type', $wallet_type);
        }

        if($wallet_address){
            $db->where('a.address', $wallet_address);
        }

        $db->orderBy('a.created_at', 'DESC');
        $db->join('xun_business b', 'a.business_id = b.user_id', 'LEFT');
        $copyDb = $db->copy();
        // $copyDb2 = $db->copy();
        // $copyDb3 = $db->copy();
        $address_listing = $db->get('xun_crypto_history a', $limit, 'a.created_at, a.business_id, b.name, b.phone_number, a.wallet_type, a.address, a.status');

        if(!$address_listing){
            return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('B00246') /*No Results Found*/, 'data' => '');
        }

        $totalRecord = $copyDb->getValue('xun_crypto_history a', 'count(a.id)');

        // $copyDb3->groupBy('a.status');
       
        // $total_transaction_by_status = $copyDb3->map('status')->ArrayBuilder()->get('xun_crypto_history a', null, 'a.status, count(a.id)');

        // $wallet_type_arr = [];
        // foreach($summary_list as $key => $value){
        //     $wallet_type = strtolower($value['wallet_type']);
        //     array_push($wallet_type_arr, $wallet_type);
            
        // }

        // $db->where('currency_id', $wallet_type_arr, 'IN');
        // $marketplace_currencies = $db->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies');

        // foreach($summary_list as $key => $value){
        //     $wallet_type = strtolower($value['wallet_type']);

        //     $summary_list[$wallet_type]['coin_name']= $marketplace_currencies[$wallet_type]['name'];
        //     $summary_list[$wallet_type]['tx_fee_symbol']= strtoupper($marketplace_currencies[$wallet_type]['symbol']);
        // }

        // foreach($total_transaction_by_status as $key=> $value){
        //     $status = ucfirst($key);
        //     $total_transaction_by_status[$status] = $value;
        //     unset($total_transaction_by_status[$key]);
        // }

        $data = array(
            "address_listing" => $address_listing,
            "totalRecord" => $totalRecord,
            "numRecord" => $page_size,
            "totalPage" => ceil($totalRecord/$page_size),
            "pageNumber" => $page_number
            // "summary_listing" => $summary_list,
            // "total_transaction_by_status" => $total_transaction_by_status,
            // "overall_total_transaction" => $totalRecord,
        );

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00315') /*Admin Address Listing.*/, 'data' => $data);
    }

    public function admin_change_merchant_password($params) {

        $db = $this->db;
        $general = $this->general;

        $business_id = $params['business_id'];

        $db->where("user_id", $business_id);
        $business_account = $db->getOne("xun_business_account", "user_id");
            
        $new_password = $params["new_password"];
        $hash_password = password_hash($new_password, PASSWORD_BCRYPT);

        $update_pass = array(
        "password" => $hash_password,
        "updated_at" => date("Y-m-d H:i:s")
        );
        $db->where("user_id", $business_id);
        $updated = $db->update("xun_business_account", $update_pass);

        if ($business_id == '') {
            return array("code" => 1, "status" => "error", "statusMsg" =>  $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);

        }

        if ($new_password == ''){
            return array("code" => 1, "status" => "error", "statusMsg" =>  $this->get_translation_message('E00067') /*New password cannot be empty*/);

        }

        if(!$updated){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            
        }

        $data = array(
            "updated" => $updated,
            
        );

        return array('code' => 0, 'status' => "ok", 'statusMsg' => $this->get_translation_message('E00397') /*Success.*/, 'data' => $data);

    }
    public function admin_change_merchant_details($params) {

        $db = $this->db;
        $general = $this->general;

        $business_id = $params['business_id'];
        $new_distributor = $params["new_distributor"];
        $new_reseller = $params["new_reseller"];
        $new_merchant_name = $params["new_merchant_name"];
        $new_mobile_number = $params["new_mobile_number"];
        $new_email = $params["new_email"];
        $new_country = $params["new_country"];
        $new_website = $params["new_website"];
        $new_company_size = $params["new_company_size"];
        $new_address1 = $params["new_address1"];
        $new_address2 = $params["new_address2"];
        $new_city = $params["new_city"];
        $new_state = $params["new_state"];
        $new_postal = $params["new_postal"];
        
        // CHECK PARAMS VALIDATION

        if ($new_merchant_name == '') {
        return array("code" => 1, "status" => "error", "statusMsg" =>  $this->get_translation_message('E00015') /*Business name cannot be empty.*/);

        }
        if ($new_mobile_number == '') {
            return array("code" => 1, "status" => "error", "statusMsg" =>  $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);

        }
        
        if ($new_reseller == '' && $new_distributor != '') {
            return array("code" => 1, "status" => "error", "statusMsg" =>  $this->get_translation_message('E00533') /*Reseller cannot be empty.*/);
        
        }
        if ($business_id == '') {
            return array("code" => 1, "status" => "error", "statusMsg" =>  $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        $mobileNumberInfo = $general->mobileNumberInfo($new_mobile_number, null);
        $mobile = str_replace("-", "", $mobileNumberInfo["phone"]);

        if ($mobileNumberInfo["isValid"] == 0) {
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00046') /*Please enter a valid mobile number.*/);
        }
        //GET MERCHANT SITE
        $db->where('id', $business_id);
        $business_site = $db->getOne("xun_user", "register_site");
        $business_user_site = $business_site["register_site"];

        if(!$business_site) {
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00028') /*Business does not exist.*/);
        }

        // CHECK DISTRIBUTOR
        if($new_distributor != ''){
            $db->where('username', $new_distributor);
            $get_distributor_username = $db->getOne('reseller', 'id');
            
            if(!$get_distributor_username){
                return array("code" => 1, "status" => "error", "statusMsg" =>  $this->get_translation_message('E00546') /*Distributor does not exist.*/);
            }

            $distributor_id = $get_distributor_username['id'];
        }

        // CHECK RESELLER
        if($new_reseller != '' && $new_distributor != ''){
            $db->where('username', $new_reseller);
            $db->where('distributor_id', $distributor_id);
            $db->where('source', $business_user_site);
            $get_reseller_username = $db->getOne('reseller', 'id');
            
            if(!$get_reseller_username){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00542') /*Reseller does not exist.*/);   
            }
        }

        if($new_reseller != '' && $new_distributor == ''){
            $db->where('username', $new_reseller);
            $db->where('source', $business_user_site);
            $get_reseller_username = $db->getOne('reseller', 'id');
            
            if(!$get_reseller_username){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00542') /*Reseller does not exist.*/);   
            }
        }
    
        // UPDATE MERCHANT ARRAY
        $update_merchant_details = array(
            "name"          => $new_merchant_name,
            "phone_number"  => $new_mobile_number,
            "email"         => $new_email,
            "country"       => $new_country,
            "website"       => $new_website,
            "company_size"  => $new_company_size,
            "address1"      => $new_address1,
            "address2"      => $new_address2,
            "city"          => $new_city,
            "state"         => $new_state,
            "postal"        => $new_postal
        );

        // UPDATE MERCHANT DETAILS
        $db->where('user_id', $business_id);
        $updated = $db->update("xun_business", $update_merchant_details);
      
        if(!$updated){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            
        }

        // UPDATE ARRAY in xun_user 
        $update_merchant_reseller_id = array(
            'reseller_id'   => $get_reseller_username['id'],
            'username'      => $new_mobile_number,
            'nickname'      => $new_merchant_name,
        );

        // UPDATE RESELLER ID IN xun_user
        $db->where('id', $business_id);
        $update_reseller_id = $db->update('xun_user', $update_merchant_reseller_id);

       
        if($new_reseller != ''){
            if(!$update_reseller_id){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
                
            }
        }

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('E00397') /*Success.*/);

    }
    
    public function admin_withdrawal_address($params){

        $db = $this->db;
        $general = $this->general;

        $business_id = $params['business_id'];


        if ($business_id == '') {
        return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        $db->where("a.business_id", $business_id);
        $db->join("xun_coins b", "a.type=b.currency_id", "LEFT");
        $db->join("xun_marketplace_currencies d", "b.currency_id=d.currency_id", "LEFT");
        $db->where("b.type", "cryptocurrency");
        $db->where("b.is_payment_gateway", 1);
        $db->join("xun_crypto_destination_address c", "a.id=c.wallet_id", "LEFT");
        $merchantCoin = $db->get("xun_crypto_wallet a", null, "a.id, a.business_id, c.destination_address, d.symbol");


        foreach($merchantCoin as $mercData){
            
            $mercID = $mercData["id"];
            $mercBusinessID = $mercData["business_id"];
            $mercSymbol = $mercData["symbol"];
            $mercSymbol = strtoupper($mercSymbol);
            
            $db->where("wallet_id", $mercID);
            $destResult = $db->get("xun_crypto_destination_address", null, "destination_address");

            $address_list = [];
            foreach($destResult as $key => $value){
                $address_list[] = $value['destination_address'];
            }

            $totalMerch_arr = array(
                "mercID" => $mercID,
                "mercBusinessID" => $mercBusinessID,
                "mercSymbol" => $mercSymbol,
                "addressList" => $address_list
            );
            $withdrawalAddress[$mercID] = $totalMerch_arr;
            
        }
        $returnData["withdrawalAddress"]   = $withdrawalAddress;

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('E00397') /*Success.*/, 'data' => $returnData);

    }

    public function admin_get_api_key($params){

        $db = $this->db;
        $setting = $this->setting;
        $admin_page_limit= $setting->getAdminPageLimit();
        $page_number        = $params["page"];
        $page_size          = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $business_id = $params['business_id'];

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);
        
        if ($business_id == '') {
        return array("code" => 0, "status" => "ok", "statusMsg" =>  $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }    

        $db->where("business_id", $business_id);
        $copyDb = $db->copy();
        $apiKeyDetails = $db->get("xun_crypto_apikey", $limit, "id, business_id, apikey, expired_at as apikey_expire_datetime, created_at");
        //$totalRecord = count($apiKeyDetails);
        $totalRecord = $copyDb->getValue('xun_crypto_apikey', 'count(id)');

        $returnData = array (
            "apiKeyDetails"    => $apiKeyDetails,
            "totalRecord"      => $totalRecord,
            "numRecord"        => $page_size,
            "totalPage"        => ceil($totalRecord/$page_size),
            "pageNumber"       => $page_number
        );
        // $returnData["apiKeyDetails"] = $apiKeyDetails;
        // $returnData["totalRecord"]      = $totalRecord;
        // $returnData["numRecord"]        = $page_size;
        // $returnData["totalPage"]        = ceil($totalRecord/$page_size);
        // $returnData["pageNumber"]       = $page_number;
        //print_r($returnData);
        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('E00397') /*Success.*/, 'data' => $returnData);
        
    } 
    
    public function admin_display_merchant_callback_url($params){
        $db = $this->db;
        $business_id = $params['business_id'];
        
        if ($business_id == '') {
            return array("code" => 1, "status" => "error", "statusMsg" =>  $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        $db->where("user_id", $business_id);
        $business_data = $db->getOne("xun_business", "user_id, pg_callback_url");

        if(!$business_data){
            return array('code' => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00028') /*Business does not exist.*/);
        }

        $business_data_id = $business_data['user_id'];
        $business_data_callback = $business_data['pg_callback_url'];        
        $business_data_arr = array(
            "business_id" => $business_data_id,
            "business_callback_url" => $business_data_callback
        );
        
        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('E00397') /*Success.*/, 'data' => $business_data_arr);
        

    }

    public function admin_fund_out_details($params) {
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;

        $admin_page_limit      = $setting->getAdminPageLimit();
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
        
        
        
        
        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('E00397') /*Success.*/, 'data' => $data);
        
        
    }

    public function admin_withdrawal_history_details($params){
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;

        $admin_page_limit      = $setting->getAdminPageLimit();
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
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

    public function admin_reseller_application_listing($params){
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
        
        if($site) {
            $db->where("r.source", "%$site%", "LIKE");
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);
        
        $db->where('r.status', 'pending');
        $db->join("reseller r2", "r.distributor_id=r2.id", "left");
        $db->join('xun_user b', 'r.user_id= b.id' , 'LEFT');
        $reseller_listing = $db->get('reseller r', $limit, "r.source, r.username as reseller_username, r.name, b.username as phone_number, r.email, IF(r2.username is null, '-', r2.username) as distributor_username");
        
        if(!$reseller_listing){
            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $this->get_translation_message('B00246') /*No Results Found*/, 'data' => "");
        }

        $data['reseller_application_listing'] = $reseller_listing;
        
        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00326')/*Reseller Application Listing*/, 'data' => $data, 'lq'=> $db->getLastQuery());
    }

    public function admin_reseller_approve($params, $user_id){
        global $xunSms, $xunEmail;
        $db= $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $post = $this->post;
        $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);

        $reseller_email = $params['reseller_email'];
        $source = $params['source'];

        if($reseller_email == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00211') /*Email cannot be empty*/, 'developer_msg' => 'Email cannot be empty.');
        }

        $db->where('email', $reseller_email);
        $db->where('source', $source);
        $reseller = $db->getOne('reseller');

        if(!$reseller){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00542') /*Username does not exist.*/, 'developer_msg' => 'Username does not exist.');
        }

        $resellerID = $reseller['id'];
        $resellerStatus = $reseller['status'];
        $resellerEmail = $reseller['email'];
        $resellerUserID = $reseller['user_id'];

        $db->where('id', $resellerUserID);
        $xun_user = $db->getOne('xun_user');

        $resellerPhoneNumber = $xun_user['username'];
        $resellerSource = $xun_user['register_site'];

        if($resellerStatus == 'approved'){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00549') /*This reseller has already been approved.*/, 'developer_msg' => 'This reseller has already been approved.');
        }
        $new_password = $general->generateRandomNumber(5);
        $hash_password = password_hash($new_password, PASSWORD_BCRYPT);

        while (1) {
            $referral_code = $general->generateAlpaNumeric(6, 'referral_code');

            $db->where('referral_code', $referral_code);
            $result = $db->get('reseller');

            if (!$result) {
                break;
            }
        }

        $updateReseller = array(
            "password" => $hash_password,
            "referral_code" => $referral_code,
            "distributor_id" => $user_id,
            "status" => "approved",
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where('id', $resellerID);
        $updated = $db->update('reseller', $updateReseller);

        if(!$updated){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
        }

        $wallet_return = $xunCompanyWallet->createUserServerWallet($resellerUserID, 'nuxpay_wallet', '');

        $internal_address = $wallet_return['data']['address'];
        
        $insertAddress = array(
            "address" => $internal_address,
            "user_id" => $resellerUserID,
            "address_type" => 'nuxpay_wallet',
            "active" => '1',
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        );

        $inserted = $db->insert('xun_crypto_user_address', $insertAddress);

        if(!$inserted){
            return array('code' => 0, 'status' => 'error', 'statusMsg' => $this->get_translation_message('E00242')/*Something went wrong.*/, "developer_msg" => $db->getLastQuery());
        }

        //SEND Email
        $emailDetail = $xunEmail->getResellerApproveEmail($resellerSource, $new_password);
        $emailParams["subject"] = $emailDetail['emailSubject'];
        $emailParams["body"] = $emailDetail['html'];
        $emailParams["recipients"] = array($resellerEmail);
        $emailParams["emailFromName"] = $emailDetail['emailFromName'];
        $emailParams["emailAddress"] = $emailDetail['emailAddress'];
        $emailParams["emailPassword"] = $emailDetail['emailPassword'];
        $msg = $general->sendEmail($emailParams);

        //SEND SMS
        $translations_message = $this->get_translation_message('B00312') /*%%companyName%%: Your temporary password is %%newPassword%% */;

        if(strtolower($resellerSource)=="ppay") {
            $resellerSource = "PPAY";
        }

        $db->where('source', $resellerSource);
        $site = $db->getOne('site');
        $resellerPrefix = $site['otp_prefix'];

        if ($resellerPrefix != ""){
            $resellerSource = $resellerPrefix;
        }
        
        $return_message = str_replace("%%companyName%%", $resellerSource, $translations_message);
        $return_message2 = str_replace("%%newPassword%%", $new_password, $return_message);
        $newParams["message"] = $return_message2;
        $newParams["recipients"] = $resellerPhoneNumber;
        $newParams["ip"] = $ip; 
        $newParams["companyName"] = $resellerSource;
        $xunSms->send_sms($newParams);

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00325') /*Reseller Successfully Approved.*/);

    }

    public function sendErrorNotification($params) {
        $general = $this->general;                

        $tag  = trim($params["tag"]);        
        $message  = trim($params["message"]);        
        
        if(empty($tag) || empty($message)) {
            return array('code' => 0, 'status' => 'error', 'statusMsg' => "Something went wrong");
        }

        //error access token
        $notification_message = $message;        
        $general->send_thenux_notification(array('tag'=>$tag, 'message'=>$notification_message), "nuxpay");
        return array("code" => 0, "status" => "ok", "statusMsg" => "Message sent!");
    }    


}
