<?php

	include_once('include/config.php');
    include_once('include/class.database.php');

    $db  = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

    $data	= json_decode(file_get_contents('php://input'), true);

    $utm_source = $data["utm_source"]? $data["utm_source"] : 0;
    $utm_medium = $data["utm_medium"]? $data["utm_medium"] : 0;
    $utm_campaign = $data["utm_campaign"]? $data["utm_campaign"] : 0;
    $utm_term = $data["utm_term"]? $data["utm_term"] : 0;
    $device_id = $data["device_id"];
    $ip = $data["ip"]? $data["ip"] : 0;
    $userAgent = $data["user_agent"]? $data["user_agent"] : 0;
    $type = $data["type"]? $data["type"] : 0;
    $country = $data["country"]? $data["country"] : 0;

    $flag = true;

    //generate random id 8 digit 00000001
    if(!$device_id){

        while($flag){

            $randNum =  rand(1,100000000);
            //	echo sprintf("%'08d\n", $randNum);
            $value = $randNum;

            $db->where('device_id', $value);

            $result =  $db->get('utm_record');
            // $result = $db->get("SELECT * FROM utm_record where device_id = $randNum");
                //echo $result;

            if(!$result){

                $flag = false;
                $device_id = $value;  

            }

        }

    } 

    $fields = array("utm_source", "utm_medium", "utm_campaign", "utm_term" , "device_id","ip" ,"user_agent", "type","country");

    $values = array($utm_source, $utm_medium, $utm_campaign, $utm_term,$device_id,$ip,$userAgent,$type,$country);
    $arrayData = array_combine($fields, $values);

    $debitID = $db->insert("utm_device_id_record", $arrayData);

    $output = array('status' => ok, 'code' => 1, 'statusMsg' => Success, 'device_id' => $device_id);

    echo json_encode($output);

    $abc = json_encode($output);  
    

?>
