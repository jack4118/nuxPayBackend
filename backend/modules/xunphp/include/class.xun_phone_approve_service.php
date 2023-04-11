<?php
class XunPhoneApproveService
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->phoneApproveModel = new XunPhoneApproveModel($db);
    }

    public function getRequestDetails($obj, $columns = null)
    {
        $phoneApproveModel = $this->phoneApproveModel;
        $result = $phoneApproveModel->getRequestDetails($obj, $columns);

        return $result;
    }

    public function getRequest($obj, $columns = null)
    {
        $phoneApproveModel = $this->phoneApproveModel;
        $result = $phoneApproveModel->getRequest($obj, $columns);

        return $result;
    }

    public function getUserRequestDetails($obj, $columns = null)
    {
        $phoneApproveModel = $this->phoneApproveModel;
        $result = $phoneApproveModel->getUserRequestDetails($obj, $columns);

        return $result;
    }

    public function updateRequestStatus($obj)
    {
        $phoneApproveModel = $this->phoneApproveModel;
        $result = $phoneApproveModel->updateRequestStatus($obj);

        return $result;
    }



}
?>