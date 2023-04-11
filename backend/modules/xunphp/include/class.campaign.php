<?php 

class Campaign{

    public function __construct($db, $setting, $general, $post, $whoisserver)
    {
        $this->db      = $db;
        $this->setting = $setting;
        $this->general = $general;
        $this->post    = $post;
        $this->whoisserver = $whoisserver;
    }

    public function create_campaign($params, $userID){
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $post = $this->post;

        $campaign_name  = $params["campaign_name"];
        $long_url       = $params["long_url"];

        if ($campaign_name == '') {
            return array('code' => 1, 'status' => "error", 'statusMsg' => "Campaign name cannot be empty.");

        }

        if ($long_url == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => "Long URL cannot be empty.");

        }

        if ($this->validateUrlFormat($long_url) == false ){
            return array('code' => 1, 'status' => "error", 'statusMsg' => "Invalid Long URL");
        }

        if (!$this->verifyUrlExists($long_url)){
            return array('code' => 1, 'status' => "error", 'statusMsg' => "URL does not exist");
        } 


        $created_at = date("Y-m-d H:i:s");

        // Replace ".com" with ".io"
        $long_url = str_replace(".com", ".io", $long_url);
        $long_url = $this->validateUrlFormat($long_url);

        $createCampaign = array(
            "campaign_name" => $campaign_name,
            "long_url"      => $long_url,
            "user_id"       => $userID,
            "created_at"    => $created_at,
            "updated_at"    => $created_at,
        );
        $new_campaign = $db->insert("campaign", $createCampaign);

        if(!$new_campaign) {
            return array('code' => 1, 'status' => "error", 'statusMsg' => "Failed to create campaign.");

        }
        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00328') /*Campaign Successfully Created.*/);
    }
    
    // public function get_translation_message($message_code)
    // {
    //     // Language Translations.
    //     $language = $this->general->getCurrentLanguage();
    //     $translations = $this->general->getTranslations();

    //     $message = $translations[$message_code][$language];
    //     return $message;
    // }

    public function campaign_listing($params, $userID){
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $post = $this->post;

        $user_id        = $params["user_id"];
        $campaign_name  = $params["campaign_name"];
        $long_url       = $params["long_url"];

        if($campaign_name){
            $db->where('campaign_name', "%$campaign_name%" , 'LIKE');
        }

        if($long_url){
            $db->where('long_url', "%$long_url%" , 'LIKE');
        }

        $db->where("a.user_id", $userID);
        $db->join("short_url b", "b.campaign_id=a.id", "LEFT");
        $db->groupBy("a.id");
        $campaignListing = $db->get("campaign a", null,"a.id, a.campaign_name, a.long_url, a.created_at, b.campaign_id, sum(b.total_clicks) as totalClicks, count(b.id) as numbOfUrl");
        
        foreach($campaignListing as $key => $value){

            $id      = $value['id'];
            $campaign_name      = $value['campaign_name'];
            $long_url      = $value['long_url'];
            $created_at      = $value['created_at'];
            $campaign_id      = $value['campaign_id'];
            $totalClicks      = $value['totalClicks'];
            $numbOfUrl      = $value['numbOfUrl'];

            if($totalClicks == ""){
                $totalClicks = 0;
            }

            $campaign_listing_arr = array(
                "campaign_id"   => $id,
                "campaign_name"  => $campaign_name,
                "long_url"  => $long_url,
                "totalClicks" => $totalClicks,
                "created_at" => $created_at,
                "numbOfUrl" => $numbOfUrl,
            );
            $campaign_listing_array[$id] = $campaign_listing_arr;
        }
        $data["campaignListing"] = $campaign_listing_array;
        

        return array('status' => 'ok', 'code' => 0, 'statusMsg' => 'Campaign Listing', 'data' => $data);
        // return array('status' => 'ok', 'code' => 0, 'statusMsg' => 'Campaign Listing', 'data' => $campaignListing);
    }

    public function campaign_listing_details($params, $userID){
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $post = $this->post;
        $summary_arr = [];

        $campaign_id = $params["campaign_id"];

        if($campaign_id == ""){
            return array('code' => 1, 'status' => "error", 'statusMsg' => "No Result");
        }

        $db->where("campaign_id", $campaign_id);
        $totalUrlClicks = $db->get("short_url", null, "count(id) as totalUrl, sum(total_clicks) as totalClicks");

        $db->where('campaign_id', $campaign_id);
        $shortUrlDetails = $db->get('short_url');

        foreach($shortUrlDetails as $key=> $value){
            $short_url_id  = $value['id'];
            $short_url_created_at = $value['created_at'];
            $total_clicks = $value['total_clicks'];

    
            $shortUrlStartDate = strtotime(date("Y-m-d",strtotime($short_url_created_at)));
            $today = strtotime(date("Y-m-d"));
            $counter = 0;
            $totalRecords = 0;

            while($shortUrlStartDate <= $today) {
                  
                $tblName = "short_url_details_".date("Ymd", $shortUrlStartDate);
                $checkTable = $db->tableExists($tblName);
    
                if($checkTable){
              
                    $db->where('short_url_id',$short_url_id);
                    // $db->orderBy('id', 'DESC');
                    $result = $db->get($tblName, null, 'ip_address, country, browser, os, device, telco, created_at');
                    
                    foreach($result as $key => $value) {

                        $detailsList[] = $value;
    
                        $countryList[$value['country']]++;
                        $browserList[$value['browser']]++;
                        $osList[$value['os']]++;
                        $deviceList[$value['device']]++;
                        $telcoList[$value['telco']]++;
                        
                    }
                }
                $shortUrlStartDate = strtotime(date('Y-m-d H:i:s', strtotime("+1 days", $shortUrlStartDate)));
    
            }

        }


         //Sort Array Descending Order
         arsort($countryList);
         arsort($browserList);
         arsort($osList);
         arsort($deviceList);
         arsort($telcoList);
 
        //Get Top Data
        $topCountry = key($countryList);
        $topBrowser = key($browserList);
        $topOS = key($osList);
        $topDevice = key($deviceList);
        $topTelco = key($telcoList);
        
        
        $totalUrl   = $totalUrlClicks[0]{"totalUrl"};
        $totalClicks = $totalUrlClicks[0]{"totalClicks"};
        
        $summary_arr[] = array(
            "totalUrl"  => $totalUrl ? $totalUrl : 0,
            "totalClicks"  => $totalClicks ? $totalClicks : 0,
            "topCountry" => $topCountry ? $topCountry : '-',
            "topBrowser" => $topBrowser ? $topBrowser : '-',
            "topOS" => $topOS ? $topOS : '-',
            "topDevice" => $topDevice ? $topDevice : '-',
            "topTelco"  => $topTelco ? $topTelco : '-',
            
        );
        $data["summary_arr"] = $summary_arr;
        
        
        foreach($shortUrlDetails as $key => $value){
            $id                     = $value["id"];
            $short_url              = $value['short_url'];
            $url_reference_name     = $value['url_reference_name'];
            $clicks_per_url         = $value['total_clicks'];
            $created_at             = $value['created_at'];
            $totalClicksAllUrl      = $summary_arr[0]["totalClicks"];
            
            
            $dividedResult = bcdiv($clicks_per_url, $totalClicksAllUrl, 18);
            $responseRate = bcmul($dividedResult, '100', 2);
            
            $responseRateStr = (string) $responseRate;
            $responseRateStr .= "%";

            $shortUrlDetails_arr = array(
                "id"         => $id,
                "short_url"  => $short_url,
                "url_reference_name"  => $url_reference_name,
                "clicks_per_url"  => $clicks_per_url,
                "responseRate"  => $responseRateStr,
                "created_at"  => $created_at,
    
            );

            $shortUrlDetails_array[$id] = $shortUrlDetails_arr;

        }
        $data["short_url_details_array"] = $shortUrlDetails_array;
        

        return array('status' => 'ok', 'code' => 0, 'statusMsg' => 'Campaign Listing Details', 'data' => $data);
    }


    public function get_short_url_details($params){
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $post = $this->post;

        $admin_page_limit       = $setting->getAdminPageLimit(); 
        $page_number            = $params["page"];
        $page_size              = $params["page_size"] ? $params["page_size"] : $admin_page_limit;
        $date_from = $params["date_from"];
        $date_to = $params["date_to"];
    
        if ($page_number < 1) {
            $page_number = 1;
        }
        
        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

    

        $short_url = $params['short_url'];

        $db->where('short_url', $short_url);
        $short_url = $db->getOne('short_url');

        if(!$short_url){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00554') /*Short URL not found.*/);
        }

        $short_url_created_at = $short_url['created_at'];
        $short_url_id = $short_url['id'];
        $totalClicks = $short_url['total_clicks'];
        // $campaign_id = $short_url['campaign_id'];
        
        // $db->where('id', $campaign_id);
        // $campaign = $db->getOne('campaign');

        // if(!$campaign){
        //     return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00556') /*Campaign not found.*/);
        // }

        if ($date_from == '' && $date_to == ''){
            $shortUrlStartDate = strtotime(date("Y-m-d 00:00:00",strtotime($short_url_created_at)));
            $today = strtotime(date("Y-m-d 23:59:59"));
        } else {
            $shortUrlStartDate = $date_from;
            $today = $date_to;
        }

        // Check if date range is within a day, empty range means All Time
        $is_day_date_range = ($today - $shortUrlStartDate > 86399) ? 0 : 1;

        $counter = 0;
        $totalRecords = 0;

        // If date range is longer than a day, increment in days
        if ($is_day_date_range == 0){
            while($shortUrlStartDate <= $today) {

                $tblName = "short_url_details_".date("Ymd", $shortUrlStartDate);
                $checkTable = $db->tableExists($tblName);
    
                if($checkTable){
              
                    $db->where('short_url_id',$short_url_id);
                    // $db->orderBy('id', 'DESC');
                    $result = $db->get($tblName, null, 'ip_address, country, browser, os, device, telco, created_at');

                    $date = date("Y-m-d 00:00:00", $shortUrlStartDate);
                    $total_click_list[$date]['date'] = $date;
                    $total_click_list[$date]['value'] = "0";
                    
                    foreach($result as $key => $value) {
    
                        $created_at = $value['created_at'];
    
                        $totalRecords++;
                        $detailsList[] = $value;
    
                        $countryList[$value['country']]++;
                        $browserList[$value['browser']]++;
                        $osList[$value['os']]++;
                        $deviceList[$value['device']]++;
                        $telcoList[$value['telco']]++;
                        
                        $total_click_list[$date]['value']++;
                    }
                } else {
                    $date = date("Y-m-d 00:00:00", $shortUrlStartDate);
                    $total_click_list[$date]['date'] = $date;
                    
                    $total_click_list[$date]['value'] = "0";
                }
                $shortUrlStartDate = strtotime(date('Y-m-d H:i:s', strtotime("+1 days", $shortUrlStartDate)));
               
            }
        } else {
            $tblName = "short_url_details_".date("Ymd", $shortUrlStartDate);
            $checkTable = $db->tableExists($tblName);

            if($checkTable){
                $db->where('short_url_id',$short_url_id);
                $result = $db->get($tblName, null, 'ip_address, country, browser, os, device, telco, created_at');
            }

            $inserted = false;
            while($shortUrlStartDate <= $today) {
                if ($checkTable){
                    $currentVal = 0;
                    // Get the rows that are between the current hour and next hour
                    foreach ($result as $key => $value) {

                        if (!$inserted){
                            $totalRecords++;
                            $detailsList[] = $value;
        
                            $countryList[$value['country']]++;
                            $browserList[$value['browser']]++;
                            $osList[$value['os']]++;
                            $deviceList[$value['device']]++;
                            $telcoList[$value['telco']]++;
                        }

                        $rowTime = strtotime($value['created_at']);

                        if ($rowTime >= $shortUrlStartDate && $rowTime < strtotime("+59 minutes +59 seconds", $shortUrlStartDate)) {
                            $currentVal++;
                        }
                    }
                    $inserted = true;
                    $date = date("Y-m-d H:00:00", $shortUrlStartDate);

                    $total_click_list[$date]['date'] = $date;
                    $total_click_list[$date]['value'] = $currentVal;
                } else {
                    $date = date("Y-m-d H:00:00", $shortUrlStartDate);
                    
                    $total_click_list[$date]['date'] = $date;
                    $total_click_list[$date]['value'] = "0";
                }

                $shortUrlStartDate = strtotime(date('Y-m-d H:i:s', strtotime("+1 hour", $shortUrlStartDate)));
            }
        }

        $graph_data = [];
        $graph_data = array_values($total_click_list);
        
        unset($total_click_list);

        //Sort Array Descending Order
        arsort($countryList);
        arsort($browserList);
        arsort($osList);
        arsort($deviceList);
        arsort($telcoList);

        //Get Top Data
        $topCountry = key($countryList);
        $topBrowser = key($browserList);
        $topOs = key($osList);
        $topDevice = key($deviceList);
        $topTelco = key($telcoList);
        
        
        foreach($countryList as $key => $value){

            $percentage = number_format(($value / $totalRecords) * 100, 2);
            $piechart_data = array(
                "value" => $value,
                "name" => $key,
                "percentage" => $percentage
            );

            $country_piechart_data[] = $piechart_data;
            
           
        }
        unset($countryList);

        foreach($browserList as $key => $value){

            $percentage = number_format(($value / $totalRecords) * 100, 2);
            $piechart_data = array(
                "value" => $value,
                "name" => $key,
                "percentage" => $percentage
            );

            $browser_piechart_data[] = $piechart_data;
        
        }

        unset($browserList);

        foreach($osList as $key => $value){

            $percentage = number_format(($value / $totalRecords) * 100, 2);
            $piechart_data = array(
                "value" => $value,
                "name" => $key,
                "percentage" => $percentage
            );

            $os_piechart_data[] = $piechart_data;
            
        }

        unset($osList);

        foreach($deviceList as $key => $value){

            $percentage = number_format(($value / $totalRecords) * 100, 2);
            $piechart_data = array(
                "value" => $value,
                "name" => $key,
                "percentage" => $percentage
            );

            $device_piechart_data[] = $piechart_data;
           
        }

        unset($deviceList);

        foreach($telcoList as $key => $value){

            $percentage = number_format(($value / $totalRecords) * 100, 2);
            $piechart_data = array(
                "value" => $value,
                "name" => $key,
                "percentage" => $percentage
            );

            $telco_piechart_data[] = $piechart_data;
            
        }

        unset($telcoList);

        $detailsList = array_splice($detailsList, $start_limit, $page_size);

        $data['total_clicks'] = $totalClicks ? $totalClicks : 0;
        $data['top_country'] = $topCountry ? $topCountry : '-';
        $data['top_browser'] = $topBrowser ? $topBrowser : '-';
        $data['top_os'] = $topOs ? $topOs : '-';
        $data['top_device'] = $topDevice ?  $topDevice : '-';
        $data['top_telco']= $topTelco ? $topTelco : '-';

        $data['graph_data'] = $graph_data ? $graph_data : [];
        
        $data['country_piechart_data'] = $country_piechart_data ? $country_piechart_data : [];
        $data['os_piechart_data'] = $os_piechart_data ? $os_piechart_data : [];
        $data['browser_piechart_data'] = $browser_piechart_data ? $browser_piechart_data : [];
        $data['device_piechart_data'] = $device_piechart_data ? $device_piechart_data : [];
        $data['telco_piechart_data'] = $telco_piechart_data ? $telco_piechart_data : [];

        $data['details_list'] = $detailsList;
        $data["totalRecord"] = $totalRecords;
        $data["numRecord"] = $page_size;
        $data["totalPage"] = ceil($totalRecords/$page_size);
        $data["pageNumber"] = $page_number;


        return array("code" => 0, "status" => "ok", "statusMsg" => '', 'data' => $data);
        
    }



    /* Member Site API */
    public function insert_short_url_details($params){
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $post = $this->post;

        $short_code = $params['short_code'];
        $ip = $params['ip'];
        $browser = $params['browser'];
        $country = $params['country'];
        $os = $params['os'];
        $device = $params['device'];
        $telco = $params['telco'];

        $db->where('short_code', $short_code);
        $short_url = $db->getOne('short_url');

        if(!$short_url){
            return array('code' => 1, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00554') /*Short URL not found.*/);
        }

        $short_url_id = $short_url['id'];
        $long_url = $short_url['long_url'];
        $total_clicks = $short_url['total_clicks'];
        $campaign_id = $short_url['campaign_id'];

        $db->where('id', $campaign_id);
        $campaign = $db->getOne('campaign');

        if(!$campaign){
            return array('code' => 1, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00556') /*Campaign not found.*/);
        }

        $long_url = $campaign['long_url'];
        
        $updateArray = array(
            "total_clicks" => $db->inc(1),
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where('id', $short_url_id);
        $updated = $db->update('short_url', $updateArray);

        if(!$updated){
            return array('code' => 1, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
        }

        $date = date('Ymd');
        $shortUrlDetailsTable = 'short_url_details_'.$date;

        $check = $db->tableExists($shortUrlDetailsTable);
        if(!$check) {
            $db->rawQuery('CREATE TABLE IF NOT EXISTS '.$shortUrlDetailsTable.' LIKE short_url_details');
        }

        $insertUrl = array(
            "short_url_id"=> $short_url_id,
            "ip_address" => $ip,
            "country" => $country,
            "browser" => $browser,
            "os" => $os,
            "device" => $device,
            "telco" => $telco,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $inserted = $db->insert($shortUrlDetailsTable, $insertUrl);

        if(!$inserted){
            return array('code' => 1, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
        }

        $data['long_url'] = $long_url;
        
        return array('code' => 0, 'message' => "SUCCESS", 'message_d' =>$this->get_translation_message('B00329') /*Campaign successfully created.*/, "data" => $data);

    }


    public function get_long_url($params, $ip, $userAgent){
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $post = $this->post;
        $whoisserver = $this->whoisserver;

        $short_code = $params['short_code'];

        if($short_code == ''){
            return array('code' => 1, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00555') /*Short Code cannot be empty.*/, 'developer_msg' => $db->getLastError());
        }

        $IpDetails = $whoisserver->LookupIP($ip);
        $finalIP = $ip;

        if($countryCode == '' || $countryCode == NULL || $countryCode == 'XX'){
            if($IpDetails['result']['country'] != ''){
                $countryCode = $IpDetails['result']['country'];
            }else if($IpDetails['result']['Country'] != ''){
                $countryCode = $IpDetails['result']['Country'];
            }
        }

        #### get ISP ####
        if($IpDetails['result']['org-name'] != ''){
            $isp = $IpDetails['result']['org-name'];

        }else if($IpDetails['result']['OrgName'] != ''){
            $isp = $IpDetails['result']['OrgName'];
        }else{
            $isp = 'Others';
        }

        #### get browser, device, model, telco etc ####
        $agentInfo = $general->getBrowserNew($userAgent);

        $db->where('iso_code2', $countryCode);
        $country = $db->getValue('country', 'name');

        $params['country'] = $country;
        $params['telco'] = $isp;
        $params['os'] = $agentInfo['OS'];
        $params['browser'] = $agentInfo['browser'];
        $params['device'] = $agentInfo['platform'];
        $params['ip'] = $ip;

        $returnData = $this->insert_short_url_details($params);

        if($returnData['code'] == 1){
            return $returnData;
        }

        return array('code' => 0, 'message' => "SUCCESS", 'message_d' =>$this->get_translation_message('B00330') /*Get Long URL Successful*/, 'data' => $returnData['data']);

    }
 
    /* Member Site API */

    public function get_translation_message($message_code)
    {
        // Language Translations.
        global $general;
        $language = $general->getCurrentLanguage();
        $translations = $general->getTranslations();

        $message = $translations[$message_code][$language];
        return $message;
    }
    
    public function create_short_url($params){

        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $post = $this->post;

        $source = $params["source"];
        $campaign_id = $params["campaign_id"];
        $url_reference_name = $params["url_reference_name"];
        
        if($source == ""){
            return array('code' => 1, 'status' => "error", 'statusMsg' => "Error, source not found.");
        }

        if($campaign_id == ""){
            return array('code' => 1, 'status' => "error", 'statusMsg' => "Error, campaign ID not found.");
        }
        
        if($url_reference_name == ""){
            return array('code' => 1, 'status' => "error", 'statusMsg' => "URL reference name cannot be empty.");
        }
        
        $db->where("source", $source);
        $domain = $db->getOne("site", "short_url");
        $short_url_domain = $domain['short_url'];

        $shortCode = $general->generateAlpaNumeric(6);
        $shortUrl = $short_url_domain . "/" . $shortCode;
        
        $db->where("short_code", $shortCode);
        $result = $db->get("short_url", null, "short_code");

        if($result){
            return array('code' => 1, 'status' => "error", 'statusMsg' => "Code existed.");
        }
        else{
                $created_at = date("Y-m-d H:i:s");
                $createShortUrl = array(
                "short_code"            => $shortCode,
                "short_url"             => $shortUrl,
                "url_reference_name"    => $url_reference_name, 
                "campaign_id"           => $campaign_id,
                "total_clicks"          => 0,
                "created_at"            => $created_at,
                "updated_at"            => $created_at,
            );
            $new_short_url = $db->insert("short_url", $createShortUrl);

            if(!$new_short_url) {
                return array('code' => 1, 'status' => "error", 'statusMsg' => "Failed to create short URL.");

            }
            // return array('code' => 0, 'status' => "SUCCESS", 'statusMsg' => "Short URL successfully created.");
            
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00331') /*Short Url has been created successfully.*/);

        }

    }

    protected function validateUrlFormat($url){

        $prefixContent = substr($url, 0, 4);
    
        if($prefixContent != "http"){
            $url = "http://".$url;
        }

        return filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED);
    }

    protected function verifyUrlExists($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return (!empty($response) && $response != 404);
    }

    public function create_landing_page($params, $userID){
        $db= $this->db;
        $general = $this->general;

        $name = $params['name'];
        $short_code = $params['short_code'];
        $title = $params['title'];
        $subtitle = $params['subtitle'];
        $description = $params['description'];
        $image_url = $params['image_url'];
        $mobile = $params['mobile'];
        $telegram = $params['telegram'] ?  $params['telegram'] : '';
        $whatsapp = $params['whatsapp'] ?  $params ['whatsapp'] : '';
        $email = $params['email'];
        $instagram = $params['instagram'] ?  $params['instagram'] : '';

        if($name == ""){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00579') /*Landing Page Name cannot be empty.*/, "developer_msg" => "Landing Page Name cannot be empty.");
        }

        if($short_code == ""){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00580') /*Short Code cannot be empty.*/, "developer_msg" => "Short Code cannot be empty.");
        }

        if($title == ""){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00581') /*Title cannot be empty.*/, "developer_msg" => "Title cannot be empty.");
        }

        if($subtitle == ""){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00582') /*Subtitle cannot be empty.*/, "developer_msg" => "Subtitle cannot be empty.");
        }

        if($description == ""){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00583') /*Description cannot be empty.*/, "developer_msg" => "Description cannot be empty.");
        }

        if($image_url == ""){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00584') /*Please select an image.*/, "developer_msg" => "Please select an image.");
        }

        // if($mobile == ""){
        //     return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/, "developer_msg" => "Mobile number cannot be empty.");
        // }

        // if($email == ""){
        //     return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00211') /*Email cannot be empty.*/, "developer_msg" => "Email cannot be empty.");
        // }

        if(strlen($title) > 30){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00585') /*Title cannot be more than 30 characters*/, "developer_msg" => "Title cannot be more than 30 characters");
        }

        if(strlen($subtitle) > 50){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00586') /*Subtitle cannot be more than 50 characters*/, "developer_msg" => "Subtitle cannot be more than 50 characters");
        }

        if(strlen($description) > 300){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00587') /*Description cannot be more than 300 characters*/, "developer_msg" => "Description cannot be more than 300 characters");
        }

        if($mobile !=""){
            $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);

            if ($mobileNumberInfo["isValid"] == 0){
                return array('code' => 1, 'message' => "error", 'statusMsg' => $this->get_translation_message('E00046') /*Please enter a valid mobile number.*/, 'developer_msg' => 'Please enter a valid mobile number');
            }  
            $mobileNo = "+".$mobileNumberInfo["mobileNumberWithoutFormat"];
        } else{
            $mobileNo = "";
        }

        if($email!=""){
            if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
                return array('code' => 1, 'status' => "error", 'statusMsg' =>  $this->get_translation_message('E00212')  /*Please enter a valid email address.*/);
            }
        }

        $db->where('short_code', $short_code);
        $landing_page = $db->getOne('landing_page');

        if($landing_page){
            return array('code' => 1, 'message' => "error", 'statusMsg' => $this->get_translation_message('E00588') /*This Landing Page name is used. Please enter another one.*/, 'developer_msg' => 'This Landing Page name is used. Please enter another one.');
        }

        $insertData = array(
            "name" => $name,
            "short_code" => $short_code,
            "reseller_id" => $userID,
            "title" => $title,
            "subtitle" => $subtitle,
            "description" => $description,
            "image_url" => $image_url,
            "mobile" => $mobileNo,
            "telegram" => $telegram,
            "whatsapp" => $whatsapp,
            "email" => $email,
            "instagram" => $instagram,
            "created_at" => date("Y-m-d H:i:s"),
                
        );

        $inserted = $db->insert('landing_page', $insertData);
        
        if(!$inserted){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
        }

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00347') /*Create Landing Page Successful.*/);
    }

    public function get_create_landing_page_details($params){
        global $config;
        $db= $this->db;
        $setting= $this->setting;

        $landingPageDefaultImage = $setting->systemSetting['landingPageDefaultImage'];
        $image_arr = explode(',', $landingPageDefaultImage);

        $data['default_image_data'] = $image_arr;

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00349') /* Get Create Landing Page Successful.*/, 'data' => $data);
    }

    public function get_landing_page_details($params){
        global $config;
        $db= $this->db;

        $short_code = $params['short_code'];
        $username = $params['username'];
        $code = $params['code'];
        $return_referral_code = $params['return_referral_code'];

        if($return_referral_code == 1){
            if($code){
                $db->where('username', $code);
                $db->orWhere('referral_code', $code);
            }
            else{
                $db->where('username', $username);
            }
            $db->where('deleted', '0');
            $reseller = $db->getOne('reseller');
            $reseller_referral_code = $reseller['referral_code'];
            return array("code" => 0, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00226') /* Success*/, 'data'=> $reseller_referral_code);

        }

        if($code){
            $db->where('username', $code);
            $db->orWhere('referral_code', $code);
        }
        else{
            $db->where('username', $username);
        }
        $db->where('deleted', '0');
        $reseller = $db->getOne('reseller');
        $resellerId = $reseller['id'];
        $db->where('short_code', $short_code);
        $db->where('reseller_id', $resellerId);
        $landing_page = $db->getOne('landing_page');

        if(!$landing_page){
            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('E00578') /*Lnading Page not found */);
        }

        $reseller_id = $landing_page['reseller_id'];

        // $db->where('id', $reseller_id);
        // $reseller = $db->getOne('reseller');

        $referral_code = $reseller['referral_code'];
        $landing_page['username'] = $username;
        $landing_page['referral_code'] = $referral_code;
        $landing_page['telegram'] = $landing_page['telegram'] ? $landing_page['telegram'] : '-';
        $landing_page['whatsapp'] = $landing_page['whatsapp'] ? $landing_page['whatsapp'] : '-';
        $landing_page['instagram'] = $landing_page['instagram'] ? $landing_page['instagram'] : '-';
        unset($landing_page['reseller_id']);
        unset($landing_page['id']);
        unset($landing_page['short_code']);

        return array("code" => 0, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00348') /* Get Landing Page Details Successful*/, 'data'=> $landing_page);
        
    }

    public function get_landing_page_presign_url($params){
        global $xunAws;

        $setting = $this->setting;

        $file_name = trim($params["file_name"]);
        $content_type = trim($params["content_type"]);
        $content_size = trim($params["content_size"]);

        $bucket = $setting->systemSetting["awsS3NuxPayBucket"];
        $s3_folder = 'landingpage';
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

    public function get_create_campaign_details($params, $userID){
        global $config;
        $db= $this->db;

        $db->where('id', $userID);
        $reseller = $db->getOne('reseller', 'referral_code, type, username');

        if(!$reseller){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00571') /*Lnading Page not found */);
        }

        $referral_code = $reseller['referral_code'];
        $user_type= $reseller['type'];
        $reseller_username = $reseller['username'];

        if($user_type == 'reseller'){
            $referral_link = $config['nuxpayUrl']."/$reseller_username";
        }
        else if($user_type == 'distributor'){
            $referral_link = $config['nuxpayUrl']."/$reseller_username";
        }

        $referral_link_arr = array(
            "name" => "Referral Link",
            "url" => $referral_link
        );
        $long_url_arr = array();
        $long_url_arr[] = $referral_link_arr;

        if($user_type == "reseller"){
            $db->where('reseller_id', $userID);
            $landing_page = $db->get('landing_page', null, 'name, short_code');

            if($landing_page){
                foreach($landing_page as $key=> $value){
                    $name = $value['name'];
                    $short_code = $value['short_code'];
                    $landing_page_url = $config['nuxpayUrl']."/".$reseller_username."/".$short_code;
    
                    $landing_page_arr = array(
                        "name" => $name,
                        "url" => $landing_page_url
                    );
                    $long_url_arr[] = $landing_page_arr;
                }
            }
            
        }

        $data['long_url_list'] = $long_url_arr;
        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00350') /*Get Create Camoaign Details Successful*/, 'data' => $data);
    }

    public function verify_landing_page_url($params, $userID){
        $db= $this->db;

        $short_code = $params['short_code'];

        if($short_code == ''){
            return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00555') /*Short Code cannot be empty.*/, 'developer_msg' => $db->getLastError());
        }

        $db->where('reseller_id', $userID);
        $db->where('short_code', $short_code);
        $landing_page = $db->getOne('landing_page');

        if($landing_page){
            $count=0;
            $num = 1;
            while(true){
                
                $new_short_code = $short_code.$num;

                $db->where('reseller_id', $userID);
                $db->where('short_code', $new_short_code);
                $landing_page = $db->getOne('landing_page');

                if(!$landing_page){
                    $count++;
                    $url_suggestion_arr[] = $new_short_code;

                    if($count >= 3){
                        break;
                    }
                }
                $num++;
    
            }

            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('E00590') /*This URL is in used, please select another one.*/, 'data' => $url_suggestion_arr);
        }

        return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('E00397') /*Success.*/);
    }

    public function get_landing_page_listing($params, $userID){
        global $config;
        $db = $this->db;
        $setting = $this->setting;

        $member_page_limit  = $setting->getMemberPageLimit();
        $page_number        = $params["page"];
        $page_size          = $params["page_size"] ? $params["page_size"] : $member_page_limit;

        $db->where('id', $userID);
        $resellerDetails = $db->getOne('reseller', null, 'username');

        if (!$resellerDetails){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/);
        }

        if ($page_number < 1){
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $db->where('reseller_id', $userID);
        $copyDb = $db->copy();
        $pageListing = $db->get('landing_page', $limit, 'id, name, short_code, title, subtitle, description, image_url, mobile, email, telegram, whatsapp, instagram, created_at');

        $totalRecord = $copyDb->getValue('landing_page', 'count(id)');
        
        foreach ($pageListing as $key => $landing_page){
            $pageListing[$key]['page_url'] = $config['nuxpayUrl'] . '/' . $resellerDetails['username'] . '/' . $pageListing[$key]['short_code'];
        }

        $returnData['pageListing'] = $pageListing;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;

        if (!$pageListing){
            return array("code" => 1, "status" => "ok", "statusMsg" => $this->get_translation_message('B00001') /*No Results Found.*/, 'developer_msg' => 'No Results Found.', 'data' => $returnData);
        } else {
            return array("code" => 1, "status" => "ok", "statusMsg" => $this->get_translation_message('B00353') /*Landing page listing.*/, 'developer_msg' => 'Landing page listing.', 'data' => $returnData);
        }
    }

    public function edit_landing_page($params, $userID){
        $general = $this->general;
        $db = $this->db;

        $landing_page_id = $params['landing_page_id'];
        $page_name = $params['page_name'];
        $short_code = $params['short_code'];
        $page_title = $params['page_title'];
        $page_subtitle = $params['page_subtitle'];
        $page_description = $params['page_description'];
        $page_image_url = $params['page_image_url'];
        $mobile = $params['mobile'];
        $telegram = $params['telegram'] ?  $params['telegram'] : '';
        $whatsapp = $params['whatsapp'] ?  $params ['whatsapp'] : '';
        $email = $params['email'];
        $instagram = $params['instagram'] ?  $params['instagram'] : '';

        $db->where('reseller_id', $userID);
        $db->where('id', $landing_page_id);
        $pageExists = $db->get('landing_page');

        if (!$pageExists){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00592') /*Landing Page cannot be found.*/, "developer_msg" => "Landing Page cannot be found.");
        }

        if($page_name == ""){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00579') /*Landing Page Name cannot be empty.*/, "developer_msg" => "Landing Page Name cannot be empty.");
        }

        if($short_code == ""){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00580') /*Short Code cannot be empty.*/, "developer_msg" => "Short Code cannot be empty.");
        }

        if($page_title == ""){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00581') /*Title cannot be empty.*/, "developer_msg" => "Title cannot be empty.");
        }

        if($page_subtitle == ""){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00582') /*Subtitle cannot be empty.*/, "developer_msg" => "Subtitle cannot be empty.");
        }

        if($page_description == ""){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00583') /*Description cannot be empty.*/, "developer_msg" => "Description cannot be empty.");
        }

        if(strlen($page_title) > 30){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00585') /*Title cannot be more than 30 characters*/, "developer_msg" => "Title cannot be more than 30 characters");
        }

        if(strlen($page_subtitle) > 50){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00586') /*Subtitle cannot be more than 50 characters*/, "developer_msg" => "Subtitle cannot be more than 50 characters");
        }

        if(strlen($page_description) > 300){
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00587') /*Description cannot be more than 300 characters*/, "developer_msg" => "Description cannot be more than 300 characters");
        }

        if($mobile !=""){
            $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);

            if ($mobileNumberInfo["isValid"] == 0){
                return array('code' => 1, 'message' => "error", 'statusMsg' => $this->get_translation_message('E00046') /*Please enter a valid mobile number.*/, 'developer_msg' => 'Please enter a valid mobile number');
            }  
            $mobileNo = "+".$mobileNumberInfo["mobileNumberWithoutFormat"];
        } else{
            $mobileNo = "";
        }

        if($email!=""){
            if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
                return array('code' => 1, 'status' => "error", 'statusMsg' =>  $this->get_translation_message('E00212')  /*Please enter a valid email address.*/);
            }
        }

        $updateData = array(
            'name' => $page_name,
            'short_code' => $short_code,
            'title' => $page_title,
            'subtitle' => $page_subtitle,
            'description' => $page_description,
            'mobile' => $mobileNo,
            'telegram' => $telegram,
            'whatsapp' => $whatsapp,
            'email' => $email,
            'instagram' => $instagram,
        );

        if ($page_image_url != ''){
            $updateData['image_url'] = $page_image_url;
        }

        $db->where('id', $landing_page_id);
        $db->where('reseller_id', $userID);
        $updated = $db->update('landing_page', $updateData);

        if(!$updated){
            return array('code' => 1, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
        } else {
            return array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00356') /*Landing Page updated successfully*/);
        }
        
    }

}
?>