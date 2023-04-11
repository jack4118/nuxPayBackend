<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$livegroupchat_arr = $db->get("xun_live_group_chat_room");

$livegroupchat_arr_len = count($livegroupchat_arr);

for ($i = 0; $i < $livegroupchat_arr_len; $i++)
{
    $data = $livegroupchat_arr[$i];
    $user_mobile = $data["user_mobile"];

    unset($xun_user);
    $db->where("username", $user_mobile);
    $xun_user = $db->getOne("xun_user");

    if($xun_user){
        $user_id = $xun_user["id"];
        unset($update_data);

        $update_data = [];
        $update_data["user_id"] = $user_id;

        $db->where("id", $data["id"]);
        $db->update("xun_live_group_chat_room", $update_data);
    }
}