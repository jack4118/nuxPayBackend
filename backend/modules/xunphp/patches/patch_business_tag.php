<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$xun_business = $db->get("xun_business");

foreach ($xun_business as $business) {
    $business_id = $business["id"];
    $business_email = $business["email"];
    $db->where("business_id", $business_id);
    $db->where("role", "owner");
    $owner = $db->getOne("xun_employee");

    if ($owner["status"] === 1) {
        continue;
    }
    // check if main mobile is verified
    $db->where("email", $business_email);
    $xun_business_account = $db->getOne("xun_business_account");

    if (!$xun_business_account) {
        continue;
    }

    if ($xun_business_account["main_mobile_verified"] === 0) {
        continue;
    }

    echo "\n $business_id";

    $owner_mobile = $xun_business_account["main_mobile"];

    if ($owner["status"] === 0) {
        $update_employee["status"] = 1;
        $db->where("id", $owner["id"]);
        $db->update("xun_employee", $update_employee);

    } else {

        // add employee
        $new_mobile = str_replace("+", "", $owner_mobile);

        $old_id = $business_id . "_" . $new_mobile;

        $created_at = date("Y-m-d H:i:s");

        $fields = array("business_id", "mobile", "name", "status", "employment_status", "created_at", "updated_at", "old_id", "role");
        $values = array($business_id, $owner_mobile, "", "1", "confirmed", $created_at, $created_at, $old_id, "owner");

        $insertData = array_combine($fields, $values);
        $db->insert("xun_employee", $insertData);
    }

    // add xun_business_tag_employee
    $db->where("business_id", $business_id);
    $db->where("status", 1);
    $xun_business_tag = $db->get("xun_business_tag");

    foreach ($xun_business_tag as $business_tag) {
        $tag = $business_tag["tag"];

        $db->where("tag", $tag);
        $db->where("business_id", $business_id);
        $db->where("username", $owner_mobile);
        $xun_business_tag_employee = $db->getOne("xun_business_tag_employee");

        if (!$xun_business_tag_employee) {
            $btag_fields = array("employee_id", "username", "business_id", "tag", "status", "created_at", "updated_at");
            $btag_values = array($old_id, $owner_mobile, $business_id, $tag, 1, $created_at, $created_at);
            $btag_arrayData = array_combine($btag_fields, $btag_values);
            $xun_business_tag_employee = $db->insert("xun_business_tag_employee", $btag_arrayData);
        } else if ($xun_business_tag_employee["status"] === 0) {
            $update_btag_employee["status"] = 1;
            $db->where("id", $xun_business_tag_employee["id"]);
            $db->update("xun_business_tag_employee", $update_btag_employee);
        }
    }
}
