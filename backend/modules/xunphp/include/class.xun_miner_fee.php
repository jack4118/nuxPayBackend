<?php
class XunMinerFee
{
    public function __construct($db, $general, $setting, $log)
    {
        $this->db = $db;
        $this->general = $general;
        $this->setting = $setting;
        $this->log = $log;
    }

    public function getMinerFeeBalance($address, $walletType){
        $db = $this->db;

        $db->where("address", $address);
        $db->where("wallet_type", $walletType);
        $db->where("is_deleted", 0);
        $minerFeeBalance = $db->getValue("xun_miner_fee_transaction", "sum(credit) - sum(debit)");

        $minerFeeBalance = $minerFeeBalance ?: 0;
        return $minerFeeBalance;
    }

    public function insertMinerFeeTransaction($params){
        $db = $this->db;

        $address = $params["address"];
        $walletTransactionId = $params["wallet_transaction_id"];
        $referenceId = $params["reference_id"];
        $referenceId = $referenceId ?: $walletTransactionId;
        $referenceTable = $params["reference_table"];
        $type = $params["type"];
        $walletType = $params["wallet_type"];
        $credit = $params["credit"] ?: 0;
        $debit = $params["debit"] ?: 0;
        $balance = $this->getMinerFeeBalance($address, $walletType);
        $balance = $balance + $credit - $debit;
        $createdAt = $params["created_at"] ?: date("Y-m-d H:i:s");
        $updatedAt = $params["updated_at"] ?: date("Y-m-d H:i:s");

        $insertData = array(
            "address" => $address,
            "reference_id" => $referenceId,
            "reference_table" => $referenceTable,
            "type" => $type,
            "wallet_type" => $walletType,
            "credit" => $credit,
            "debit" => $debit,
            "balance" => $balance,
            "is_deleted" => 0,
            "created_at" => $createdAt,
            "updated_at" => $updatedAt
        );

        $rowId = $db->insert("xun_miner_fee_transaction", $insertData);
        
        if(!$rowId){
            $log->write(date("Y-m-d H:i:s") . " Error insert into xun_miner_fee_transaction. " . $db->getLastError());
        }

        return $rowId;
    }
}
?>