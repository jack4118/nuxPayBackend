<?php
class GroupChatService
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->groupChatModel = new GroupChatModel($db);
    }

    public function getGroupChatByInviteKey($inviteKey, $columns = null)
    {
        $groupChatModel = $this->groupChatModel;
        $result = $groupChatModel->getGroupChatByInviteKey($inviteKey, $columns);

        return $result;
    }

    public function getGroupChatParticipants($obj, $columns = null, $returnMap = false)
    {
        $groupChatModel = $this->groupChatModel;
        $result = $groupChatModel->getGroupChatParticipants($obj, $columns, $returnMap);

        return $result;
    }

    public function getGroupChatParticipant($obj, $columns = null)
    {
        $groupChatModel = $this->groupChatModel;
        $result = $groupChatModel->getGroupChatParticipant($obj, $columns);

        return $result;
    }

    public function insertMucUser($obj)
    {
        $groupChatModel = $this->groupChatModel;
        $result = $groupChatModel->insertMucUser($obj);

        return $result;
    }

    public function getGroupPublicKey($obj, $columns = null)
    {
        $groupChatModel = $this->groupChatModel;
        $result = $groupChatModel->getGroupPublicKey($obj, $columns);

        return $result;
    }

}
