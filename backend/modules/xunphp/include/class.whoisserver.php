<?php
class WhoisServer{
    ##### get country and ISP using who is tools ####

    function LookupIP($ip) {
        $whoisservers = array(
            //"whois.afrinic.net", // Africa - returns timeout error :-(
            "whois.lacnic.net", // Latin America and Caribbean - returns data for ALL locations worldwide :-)
            "whois.apnic.net", // Asia/Pacific only
            "whois.arin.net", // North America only
            "whois.ripe.net" // Europe, Middle East and Central Asia only
        );
        
        $results = array();
        foreach($whoisservers as $whoisserver) {
            $result = $this->QueryWhoisServer($whoisserver, $ip);
            if($result && !in_array($result, $results)) {
                $results[$whoisserver]= $result;
            }
        }
        // $res = "RESULTS FOUND: " . count($results);
        foreach($results as $whoisserver =>$result) {
            // $res .= "\n\n-------------\nLookup results for " . $ip . " from " . $whoisserver . " server:\n\n" . $result;
            ##### get country and ISP ####
            foreach($result as $val){
                $data[] = explode(":", $val);
            }

            foreach($data as $k => $v){
                if($v[0] == 'org-name' || $v[0] == 'country' || $v[0] == 'OrgName' || $v[0] == 'Country'){

                    $final[$v[0]]= trim($v[1]);
                }else if($v[0] == 'netname'){
                     $final['org-name']= trim($v[1]);
                }
                
            }
            
            $res[] = array(
                "ip" => $ip,
                "whoisserver" => $whoisserver,
                "result" => $final,
            );
        }

        if(count($res)>1){
            return $res[0];
        }else{
            return $res;
        }  
    }

    function QueryWhoisServer($whoisserver, $domain) {
        $port = 43;
        $timeout = 10;
        $fp = @fsockopen($whoisserver, $port, $errno, $errstr, $timeout) or die("Socket Error " . $errno . " - " . $errstr);
        //if($whoisserver == "whois.verisign-grs.com") $domain = "=".$domain; // whois.verisign-grs.com requires the equals sign ("=") or it returns any result containing the searched string.
        fputs($fp, $domain . "\r\n");
        $out = "";
        while(!feof($fp)){
            $out .= fgets($fp);
        }
        fclose($fp);
        $res = "";
        if((strpos(strtolower($out), "error") === FALSE) && (strpos(strtolower($out), "not allocated") === FALSE)) {
            $rows = explode("\n", $out);
 
            $res = array();
            foreach($rows as $row) {
                $row = trim($row);
                if(($row != '') && ($row{0} != '#') && ($row{0} != '%')) {
                   
                    $res[] = $row;
                }
            }
        }

        return $res;
    }

 }
?>