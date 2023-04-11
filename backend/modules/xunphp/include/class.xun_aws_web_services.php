<?php

class XunAWSWebservices
{

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function insertWebserviceData($dataIn, $createTime, $command)
    {
        $db = $this->db;

        $command = $db->escape($command);
        $createTime = $db->escape($createTime);
        $dataIn = $db->escape($dataIn);

        $fields = array("command", "data_in", "created_at");
        $values = array($command, $dataIn, $createTime);
        $insertData = array_combine($fields, $values);

        $insertId = $db->insert("xun_aws_web_services", $insertData);

        return $insertId;
    }

    public function updateWebserviceData($webserviceID, $dataOut, $status, $completeTime, $processedTime)
    {
        $db = $this->db;

        $status = $db->escape($status);
        $completeTime = $db->escape($completeTime);
        $processedTime = $db->escape($processedTime);
        $dataOut = $db->escape($dataOut);

        $fields = array("data_out", "status", "completed_at", "duration");
        $values = array($dataOut, $status, $completeTime, $processedTime);
        $updateData = array_combine($fields, $values);

        $db->where("id", $webserviceID);
        $db->update("xun_aws_web_services", $updateData);
    }

}
