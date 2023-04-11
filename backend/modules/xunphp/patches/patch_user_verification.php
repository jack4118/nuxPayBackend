<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$xun_user_verification = $db->get("xun_user_verification");

foreach ($xun_user_verification as $user_verification){
    echo "\n rec: \n";
    print_r($user_verification);
    /*
     * if request => is_valid == 0 => failed, 1 => success
     * if verify => is_valid == 0 => failed, is_verified == 0 => is_verified == 1 => success
    **/
    $is_valid = $user_verification["is_valid"];
    $is_verified = $user_verification["is_verified"];
    if($user_verification["request_at"] > 0){
        $action_type = "request";
        $created_at = $user_verification["request_at"];
        $status = $is_valid ? "success" : "failed";
    }else{
        $action_type = "verify";
        $created_at = $user_verification["verify_at"];
        $status = $is_verified ? "success" : "failed";
    }

    $updateData = [];
    $updateData["status"] = $status;
    $updateData["created_at"] = $created_at;
    print_r($updateData);
    $db->where("id", $user_verification["id"]);
    $db->update("xun_user_verification", $updateData);
}