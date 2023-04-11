<?php

	include_once('include/config.php');
    include_once('include/class.database.php');

    $db     = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

	$data	= json_decode(file_get_contents('php://input'), true);

    $business_id = $data["business_id"] ? $data["business_id"] : 0;
    $business_name = $data["business_name"] ? $data["business_name"] : 0;
    $utm_source = $data["utm_source"]? $data["utm_source"] : 0;
    $utm_medium = $data["utm_medium"]? $data["utm_medium"] : 0;
    $utm_campaign = $data["utm_campaign"]? $data["utm_campaign"] : 0;
    $utm_term = $data["utm_term"]? $data["utm_term"] : 0;
    $device_id = $data["device_id"]? $data["device_id"] : 0;
    $ip = $data["ip"]? $data["ip"] : 0;
    $userAgent = $data["user_agent"]? $data["user_agent"] : 0;
    $type = $data["type"]? $data["type"] : 0;
    $country = $data["country"]? $data["country"] : 0;

    $fields = array("business_id", "business_name", "utm_source", "utm_medium", "utm_campaign", "utm_term" , "device_id"
    ,"ip" ,"user_agent", "type","country");

    $values = array($business_id, $business_name, $utm_source, $utm_medium, $utm_campaign, $utm_term,$device_id,$ip,$userAgent,$type,$country);
    $arrayData = array_combine($fields, $values);

    $debitID = $db->insert("utm_record", $arrayData);

    return array('status' => 'ok', 'code' => "1", 'statusMsg' => "abc", 'data' => "");
			

?>
