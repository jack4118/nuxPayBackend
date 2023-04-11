<?php

class GroupChatModel
{
    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getGroupChatByInviteKey($inviteKey, $columns = null)
    {
        $db = $this->db;

        $db->where("invite_key", $inviteKey);
        $data = $db->getOne("xun_group_chat", $columns);

        return $data;
    }

    public function getGroupChatParticipants($obj, $columns = null, $returnMap = false)
    {
        $db = $this->db;

        $groupId = $obj->groupId;
        $groupHost = $obj->groupHost;

        $db->where("group_id", $groupId);
        $db->where("group_host", $groupHost);

        if ($returnMap == true) {
            $data = $db->map("username")->ArrayBuilder()->get("xun_muc_user", $columns);
        } else {
            $data = $db->get("xun_muc_user", $columns);
        }

        return $data;
    }

    public function getGroupChatParticipant($obj, $columns = null)
    {
        $db = $this->db;

        $username = $obj->username;
        $groupId = $obj->groupId;
        $groupHost = $obj->groupHost;

        $db->where("username", $username);
        $db->where("group_id", $groupId);
        $db->where("group_host", $groupHost);

        $data = $db->getOne("xun_muc_user", $columns);
        return $data;
    }

    public function insertMucUser($obj)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $username = $obj->username;
        $groupId = $obj->groupId;
        $groupHost = $obj->groupHost;
        $createdAt = $obj->createdAt;
        $createdAt = $createdAt ? $createdAt : $date;

        $insertData = array(
            "username" => $username,
            "group_id" => $groupId,
            "group_host" => $groupHost,
            "created_at" => $createdAt,
        );

        $rowID = $db->insert("xun_muc_user", $insertData);

        if (!$rowID) {
            print_r($insertData);
            echo "\n " . $db->getLastError();
        }
        return $rowID;
    }

    public function getGroupPublicKey($obj, $columns = null)
    {
        $db = $this->db;

        $groupId = $obj->groupId;

        $db->where("key_user_id", $groupId);
        $data = $db->getOne("xun_public_key", $columns);

        return $data;
    }
}
