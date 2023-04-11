<?php

    include_once('../include/config.php');
    include_once('../include/class.database.php');

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

    $db->where("old_id", "");
    $xun_employee = $db->get("xun_employee");

    foreach($xun_employee as $employee){
        $mobile = $employee["mobile"];
        $business_id = $employee["business_id"];

        $new_mobile = str_replace("+", "", $mobile);

        $old_id = $business_id . "_" . $new_mobile;
        $updateData["old_id"] = $old_id;

        echo "\n $mobile, $business_id, $old_id\n";
        $db->where("id", $employee["id"]);
        $db->update("xun_employee", $updateData);
    }

    $db->where("employee_id", "");
    $xun_business_tag_employee = $db->get("xun_business_tag_employee");

    foreach($xun_business_tag_employee as $tag_employee){
        $mobile = $tag_employee["username"];
        $business_id = $tag_employee["business_id"];

        $new_mobile = str_replace("+", "", $mobile);

        $old_id = $business_id . "_" . $new_mobile;
        $updateTagData["employee_id"] = $old_id;

        echo "\n $mobile, $business_id, $old_id\n";
        $db->where("id", $tag_employee["id"]);
        $db->update("xun_business_tag_employee", $updateTagData);
    }


?>
