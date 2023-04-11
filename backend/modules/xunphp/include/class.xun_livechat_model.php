<?php

class XunLiveChatModel
{
    public function __construct($db)
    {
        $this->db = $db;
    }

    public function createLiveGroupChatRoom($chatroom_obj)
    {
        global $log;
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $chatroom_id = $db->getNewID();

        $chatroom_host = $chatroom_obj->chatroom_host;
        $user_mobile = $chatroom_obj->user_mobile ? $chatroom_obj->user_mobile : '';
        $user_id = $chatroom_obj->user_id;
        $user_host = $chatroom_obj->user_host;
        $business_id = $chatroom_obj->business_id;
        $employee_mobile = $chatroom_obj->employee_mobile;
        $created_at = $chatroom_obj->created_at ? $chatroom_obj->created_at : $date;
        $updated_at = $chatroom_obj->updated_at ? $chatroom_obj->updated_at : $date;

        $insert_data = array(
            "old_id" => $chatroom_id,
            "host" => $chatroom_host,
            "user_mobile" => $user_mobile,
            "user_id" => $user_id,
            "user_host" => $user_host,
            "business_id" => $business_id,
            "status" => 1,
            "creator_mobile" => $employee_mobile,
            "creator_host" => $user_host,
            "created_at" => $created_at,
            "updated_at" => $updated_at,
        );

        $row_id = $db->insert("xun_live_group_chat_room", $insert_data);
        // if (!$row_id) {
        //     print_r($db);
        //     print_r($insert_data);
        // }

        $data = $insert_data;
        $data["id"] = $row_id;
        return $data;
    }

    public function getLiveGroupChatRoomDetailsByBusinessIDUserID($chatroom_obj, $columns = null)
    {
        $db = $this->db;

        $business_id = $chatroom_obj->business_id;
        $user_id = $chatroom_obj->user_id;

        $db->where("business_id", $business_id);
        $db->where("user_id", $user_id);
        $data = $db->getOne("xun_live_group_chat_room", $columns);

        return $data;
    }

    public function getLiveGroupChatRoomDetailsByChatroomID($chatroom_obj, $columns = null)
    {
        $db = $this->db;

        $chatroom_id = $chatroom_obj->chatroom_id;
        // $chatroom_host = $chatroom_obj->chatroom_host;

        $db->where("old_id", $chatroom_id);
        // $db->where("host", $chatroom_host);
        $data = $db->getOne("xun_live_group_chat_room", $columns);

        return $data;
    }

    public function getLiveGroupChatRoomDetailsForBusinessToBusiness($user_id1, $user_id2, $columns = null)
    {
        $db = $this->db;

        // $db->where ("(id = ? or id = ?)", Array(6,2));
        $db->where("(user_id = ? and business_id = ?)", [$user_id1, $user_id2]);
        $db->orWhere("(user_id = ? and business_id = ?)", [$user_id2, $user_id1]);

        $data = $db->getOne("xun_live_group_chat_room", $columns);
        return $data;
    }

}
