<?php

    include_once('../include/config.php');
    include_once('../include/class.database.php');

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

    // loop xun_business_tag_employee
    $db->where("status", 1);
    $xun_business_tag_employee = $db->get("xun_business_tag_employee");

    $date = date("Y-m-d H:i:s");

    foreach($xun_business_tag_employee as $tag_employee){
        // check if the employee record exists
        $business_id = $tag_employee["business_id"];
        $tag = $tag_employee["tag"];
        $username = $tag_employee["username"];
        $db->where("business_id", $business_id);
        $db->where("mobile", $username);
        $db->where("status", 1);
        $db->where("employment_status", "confirmed");
        $employee = $db->getOne("xun_employee");

        if(!$employee){
            // delete tag_employee record
            $id = $tag_employee['id'];
            $updateData["status"] = 0;
            $updateData["updated_at"] = $date;

            $db->where("id", $id);
            $db->update("xun_business_tag_employee", $updateData);

            echo "\n business_id $business_id tag $tag username $username \n";
        }
    }
?>
