<?php

class XunWallet
{

    public function __construct($db)
    {
        $this->db = $db;
        $xunWalletTransactionModel = new XunWalletTransactionModel($db);
        $this->xunWalletTransactionModel = $xunWalletTransactionModel;
    }

    public function getWalletTransactionByTxHash($transactionHash)
    {
        $xunWalletTransactionModel = $this->xunWalletTransactionModel;

        $data = $xunWalletTransactionModel->getWalletTransactionByTxHash($transactionHash);
        return $data;
    }

    public function getWalletTransactionByTxHashAndRecipientAddress($transactionHash, $recipientAddress, $bcReferenceID)
    {
        $xunWalletTransactionModel = $this->xunWalletTransactionModel;

        $data = $xunWalletTransactionModel->getWalletTransactionByTxHashAndRecipientAddress($transactionHash, $recipientAddress, $bcReferenceID);
        return $data;
    }

    public function getWalletTransactionByID($id)
    {
        $xunWalletTransactionModel = $this->xunWalletTransactionModel;

        $data = $xunWalletTransactionModel->getWalletTransactionByID($id);
        return $data;
    }

    public function walletServerCallbackUpdate($transactionObj)
    {
        $db = $this->db;
        $xunWalletTransactionModel = new XunWalletTransactionModel($db);

        $transactionHash = $transactionObj->transactionHash;

        $transactionRecord = $xunWalletTransactionModel->getWalletTransactionByTxHash($transactionHash);
        // status: pending, wallet_success, completed

        if ($transactionRecord["status"] !== "completed") {
            if (!$transactionRecord) {
                $xunWalletTransactionModel->insertWalletTransaction($transactionObj);
            } elseif ($transactionRecord["status"] !== $transactionObj->status) {
                $xunWalletTransactionModel->updateWalletTransactionByID($transactionRecord, $transactionObj);
            }
        }

        return $transactionRecord["id"];
    }

    public function walletServerCallbackUpdateTxHashAndStatus($transactionObj)
    {
        $db = $this->db;
        $xunWalletTransactionModel = new XunWalletTransactionModel($db);
        
        $retVal = $xunWalletTransactionModel->updateWalletTransactionTxHashAndStatusByID($transactionObj);
        return $retVal;
    }

    public function cryptoCallbackUpdate($transactionObj, $target = "internal")
    {
        global $xunPayment;
        $db = $this->db;
        $xunWalletTransactionModel = new XunWalletTransactionModel($db);

        $transactionHash = $transactionObj->transactionHash;
        $exTransactionHash = $transactionObj->exTransactionHash;
        $transactionType = $transactionObj->transactionType;
        $addressType = $transactionObj->addressType;
        $recipientAddress = $transactionObj->recipientAddress;
        $bcReferenceID = $transactionObj->bcReferenceID;
        if($target == "internal"){
            $transactionRecord = $this->getWalletTransactionByTxHash($transactionHash);
        }else{
            // $transactionRecord = $this->getWalletTransactionByTxHash($exTransactionHash);
            if($transactionType == "send"){
                $transactionRecord = $this->getWalletTransactionByTxHashAndRecipientAddress($transactionHash, $recipientAddress, $bcReferenceID);
            }
            elseif($transactionType == "receive"){
                $transactionRecord = $this->getWalletTransactionByTxHashAndRecipientAddress($exTransactionHash, $recipientAddress, $bcReferenceID);
            }
            
        }

        if($transactionRecord["status"] == "completed"){
            $returnData = $transactionRecord;
            $returnData["is_completed"] = 1;
            return $returnData;
        }

        if ($transactionType == "send") {
            if ($transactionRecord) {
                // update transaction status to complete
                if($transactionObj->status == 'confirmed'){
                    $transactionObj->status = "completed";
                }
               
                $retVal = $xunWalletTransactionModel->updateWalletTransactionByID($transactionRecord, $transactionObj);
                if ($retVal) {
                    $rowID = $transactionRecord["id"];
                }
            }
        } else {
            if (!$transactionRecord) {
                $transactionObj->status = "completed";
                if($addressType == "prepaid"){
                    $rowID = $xunWalletTransactionModel->insertWalletTransaction($transactionObj);
                }
            } else {
                $walletTransactionAmount = $transactionRecord["amount"];
                $walletTransactionWalletType = $transactionRecord["wallet_type"];

                if ($walletTransactionAmount != 0) {
                    if ($transactionObj->amount == $walletTransactionAmount) {
                        if($transactionObj->status == 'confirmed'){
                            $transactionObj->status = "completed";
                        }
                        
                    } else {
                        $transactionObj->amount = $walletTransactionAmount;
                        $transactionObj->status = $transactionRecord["status"];
                    }
                } else {
                    if($transactionObj->status == 'confirmed'){
                        $transactionObj->status = "completed";
                    }
                   
                }
                $retVal = $xunWalletTransactionModel->updateWalletTransactionByID($transactionRecord, $transactionObj);
                if ($retVal) {
                    $rowID = $transactionRecord["id"];
                }
            }
        }

        //NUXPAY NEW ACCOUNTING TABLE
        if($target == 'external'){
            $db->where('fund_out_transaction_id', $exTransactionHash);

        }
        else{
            $db->where('fund_out_transaction_id', $transactionHash);

        }
        $payment_details_data = $db->getOne('xun_payment_details');

        if($payment_details_data){
            $payment_details_id = $payment_details_data['id'];

            $updatePayment = array(
                "status" => $transactionObj->status
            );
            $db->where('id', $payment_details_id);
            $db->update('xun_payment_details', $updatePayment);

        
        }

        $fund_out_table = $transactionRecord['transaction_history_table'];
        $fund_out_id = $transactionRecord['transaction_history_id'];
        //Daily Table
        if($fund_out_id && $fund_out_table){
    
            $updateTxHistory = array(
                "status" => $transactionObj->status == 'confirmed' || $transactionObj->status == 'completed' ? 'success' : $transactionObj->status,
                "exchange_rate" => $transactionObj->exchangeRate,
                "miner_fee_exchange_rate" => $transactionObj->minerFeeExchangeRate,
                "updated_at" => date("Y-m-d H:i:s")
            );

            $xunPayment->update_payment_transaction_history($fund_out_table, $fund_out_id, $updateTxHistory);
        }
        
        $returnData = $transactionRecord;
        $returnData["id"] = $rowID;
        $returnData["status"] = $transactionObj->status;
        return $returnData;
    }

    public function insertUserWalletTransaction($transactionObj)
    {
        $db = $this->db;

        $xunWalletTransactionModel = new XunWalletTransactionModel($db);

        if($transactionObj instanceof XunWalletTransaction){
            $transactionObj = $this->mapWalletTransactionToArray($transactionObj);
        }
        $id = $xunWalletTransactionModel->insertWalletTransaction($transactionObj);

        return $id;
    }

    public function updateWalletTransactionHash($transactionObj)
    {
        $db = $this->db;

        $xunWalletTransactionModel = new XunWalletTransactionModel($db);

        $id = $transactionObj->id;
        $updateData = [];
        $updateData["transaction_hash"] = $transactionObj->transactionHash;
        $updateData["reference_id"] = $transactionObj->referenceID;
        $updateData["expires_at"] = $transactionObj->expiresAt;
        $updateData["escrow_contract_address"] = $transactionObj->escrowContractAddress;
        $updateData["updated_at"] = $transactionObj->updatedAt;
        $updateData['bc_reference_id'] = $transactionObj->bcReferenceID;

        if($transactionObj->status){
            $updateData["status"] = $transactionObj->status;
        }

        $retVal = $xunWalletTransactionModel->updateWalletTransaction($id, $updateData);

        return $retVal;
    }

    public function updateUserWalletTransaction($transactionObj)
    {
        $db = $this->db;
        $xunWalletTransactionModel = new XunWalletTransactionModel($db);

        $transactionHash = $transactionObj->transactionHash;

        $transactionRecord = $xunWalletTransactionModel->getWalletTransactionByTxHash($transactionHash);
        // status: pending, wallet_success, completed

        if ($transactionRecord["status"] !== "completed") {
            if (!$transactionRecord) {
                $xunWalletTransactionModel->insertWalletTransaction($transactionObj);
            } elseif ($transactionRecord["status"] !== $transactionObj->status) {
                $xunWalletTransactionModel->updateWalletTransactionByID($transactionRecord, $transactionObj);
            }
        }

        return $transactionRecord["id"];
    }

    public function getEscrowReport($obj)
    {
        $xunWalletTransactionModel = $this->xunWalletTransactionModel;

        return $xunWalletTransactionModel->getEscrowReport($obj);
    }

    public function insertEscrowReport($obj)
    {
        $xunWalletTransactionModel = $this->xunWalletTransactionModel;
        return $xunWalletTransactionModel->insertEscrowReport($obj);
    }

    public function mapWalletTransactionToArray($obj, $columns = ""){
        if (!$obj instanceof XunWalletTransaction) {
            return false;
        }

        $tableColumns = [
            "id", "user_id", "sender_address", "recipient_address", "sender_user_id",
            "recipient_user_id", "amount", "wallet_type", "fee", "fee_unit", 
            "transaction_hash", "transaction_token", "status", "address_type", 
            "transaction_type", "escrow", 'escrow_contract_address', "miner_fee_exchange_rate",
            "expires_at", "created_at", "updated_at",
        ];

        if (!empty($columns)) {
            $selectedColumns = explode(",", $columns);
        } else {
            $selectedColumns = $tableColumns;
        }

        $dataArr = [];

        foreach ($selectedColumns as $col) {
            $col = trim($col);

            switch ($col) {
                case "id":
                    $dataArr[$col] = $obj->getId();
                    break;
                case "user_id":
                    $dataArr[$col] = $obj->getUserID();
                    break;
                case "sender_address":
                    $dataArr[$col] = $obj->getSenderAddress();
                    break;
                case "recipient_address":
                    $dataArr[$col] = $obj->getRecipientAddress();
                    break;
                case "sender_user_id":
                    $dataArr[$col] = $obj->getSenderUserID();
                    break;
                case "recipient_user_id":
                    $dataArr[$col] = $obj->getRecipientUserID();
                    break;
                case "amount":
                    $dataArr[$col] = $obj->getAmount();
                    break;
                case "wallet_type":
                    $dataArr[$col] = $obj->getWalletType();
                    break;
                case "fee":
                    $dataArr[$col] = $obj->getFee();
                    break;
                case "fee_unit":
                    $dataArr[$col] = $obj->getFeeUnit();
                    break;
                case "transaction_hash":
                    $dataArr[$col] = $obj->getTransactionHash();
                    break;
                case "transaction_token":
                    $dataArr[$col] = $obj->getTransactionToken();
                    break;
                case "status":
                    $dataArr[$col] = $obj->getStatus();
                    break;
                case "address_type":
                    $dataArr[$col] = $obj->getAddressType();
                    break;
                case "transaction_type":
                    $dataArr[$col] = $obj->getTransactionType();
                    break;
                case "escrow":
                    $dataArr[$col] = $obj->getEscrow();
                    break;
                case "escrow_contract_address":
                    $dataArr[$col] = $obj->getEscrowContractAddress();
                    break;
                case "miner_fee_exchange_rate":
                    $dataArr[$col] = $obj->getMinerFeeExchangeRate();
                    break;
                case "expires_at":
                    $dataArr[$col] = $obj->getExpiresAt();
                    break;
                case "created_at":
                    $dataArr[$col] = $obj->getCreatedAt();
                    break;
                case "updated_at":
                    $dataArr[$col] = $obj->getUpdatedAt();
                    break;
            }
        }
        return $dataArr;
    }

    public function mapWalletTransactionToObj($dataArr)
    {
        $db = $this->db;
        $walletTransaction = new XunWlletTransaction($db);
        foreach ($dataArr as $col => $value) {
            switch ($col) {
                case "id":
                    $dataArr[$col] = $obj->setId($value);
                    break;
                case "user_id":
                    $dataArr[$col] = $obj->setUserID($value);
                    break;
                case "sender_address":
                    $dataArr[$col] = $obj->setSenderAddress($value);
                    break;
                case "recipient_address":
                    $dataArr[$col] = $obj->setRecipientAddress($value);
                    break;
                case "sender_user_id":
                    $dataArr[$col] = $obj->setSenderUserID($value);
                    break;
                case "recipient_user_id":
                    $dataArr[$col] = $obj->setRecipientUserID($value);
                    break;
                case "amount":
                    $dataArr[$col] = $obj->setAmount($value);
                    break;
                case "wallet_type":
                    $dataArr[$col] = $obj->setWalletType($value);
                    break;
                case "fee":
                    $dataArr[$col] = $obj->setFee($value);
                    break;
                case "fee_unit":
                    $dataArr[$col] = $obj->setFeeUnit($value);
                    break;
                case "transaction_hash":
                    $dataArr[$col] = $obj->setTransactionHash($value);
                    break;
                case "transaction_token":
                    $dataArr[$col] = $obj->setTransactionToken($value);
                    break;
                case "status":
                    $dataArr[$col] = $obj->setStatus($value);
                    break;
                case "address_type":
                    $dataArr[$col] = $obj->setAddressType($value);
                    break;
                case "transaction_type":
                    $dataArr[$col] = $obj->setTransactionType($value);
                    break;
                case "escrow":
                    $dataArr[$col] = $obj->setEscrow($value);
                    break;
                case "escrow_contract_address":
                    $dataArr[$col] = $obj->setEscrowContractAddress($value);
                    break;
                case "miner_fee_exchange_rate":
                    $dataArr[$col] = $obj->setMinerFeeExchangeRate($value);
                    break;
                case "expires_at":
                    $dataArr[$col] = $obj->setExpiresAt($value);
                    break;
                case "created_at":
                    $dataArr[$col] = $obj->setCreatedAt($value);
                    break;
                case "updated_at":
                    $dataArr[$col] = $obj->setUpdatedAt($value);
                    break;
            }
        }
        return $walletTransaction;
    }
    
    public function updateWalletTransaction($id, $updateData){
        $db = $this->db;
        $xunWalletTransactionModel = new XunWalletTransactionModel($db);

        $retVal = $xunWalletTransactionModel->updateWalletTransaction($id, $updateData);

        return $retVal;
    }
}
