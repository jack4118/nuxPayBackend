<?php

class XunPhoneApproveModel
{
    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getRequest($obj, $columns = null)
    {
        $db = $this->db;

        $batchId = $obj->batchId;

        if ($batchId) {
            $db->where("batch_id", $batchId);
        }

        $data = $db->getOne("xun_business_phone_approve_request", null, $columns);
        return $data;
    }

    public function getRequestDetails($obj, $columns = null)
    {
        $db = $this->db;

        $batchId = $obj->batchId;
        $requestId = $obj->requestId;

        $join = false;
        if ($batchId) {
            $db->where("b.batch_id", $batchId);
            $join = true;
        }

        if ($requestId) {
            $db->where("a.request_id", $requestId);
        }

        if ($join === true) {
            $db->join("xun_business_phone_approve_request b", "a.request_id=b.id", "LEFT");
        }

        $data = $db->get("xun_business_phone_approve_request_detail a", null, $columns);
        return $data;
    }

    public function getUserRequestDetails($obj, $columns)
    {
        $db = $this->db;

        $batchId = $obj->batchId;
        $username = $obj->username;
        $userId = $obj->userId;
        $walletTransactionId = $obj->walletTransactionId;
        $externalAddress = $obj->externalAddress;

        $query = false;
        if($batchId){
            $db->where("a.batch_id", $batchId);
            $query = true;
        }

        if($userId){
            $db->where("b.user_id", $userId);
            $query = true;
        }else if($username){
            $db->where("b.username", $username);
            $query = true;
        }else if($walletTransactionId){
            $db->where("b.wallet_transaction_id", $walletTransactionId);
            $query = true;
        }else if($externalAddress){
            $db->where("b.address", $externalAddress);
            $query = true;
        }else{
            $query = false;
        }

        if($query === true){
            $db->join("xun_business_phone_approve_request_detail b", "a.id=b.request_id", "LEFT");
            $data = $db->getOne("xun_business_phone_approve_request a", $columns);
        }else{
            throw new Exception("invalid query. Error : " . $db->getLastError() . ";" . $db->getLastQuery());
        }

        return $data;

    }

    public function updateRequestStatus($obj)
    {
        $db = $this->db;

        $id = $obj->id;
        $status = $obj->status;
        $message = $obj->message;

        $date = date("Y-m-d H:i:s");

        if(!$id){
            return;
        }

        $updateData = [];
        $updateData["status"] = $status;
        $updateData["message"] = $message;
        $updateData["updated_at"] = $date;

        $db->where("id", $id);
        $updateVal = $db->update("xun_business_phone_approve_request", $updateData);
        if(!$updateVal){
            throw new Exception($db->getLastError());
        }

        return $updateVal;
    }
}
