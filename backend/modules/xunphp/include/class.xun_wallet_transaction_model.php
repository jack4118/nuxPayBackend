<?php

class XunWalletTransactionModel
{

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getWalletTransactionByTxHash($transactionHash)
    {
        $db = $this->db;

        $db->where("transaction_hash", $transactionHash);
        $record = $db->getOne("xun_wallet_transaction");

        return $record;
    }

    public function getWalletTransactionByTxHashAndRecipientAddress($transactionHash, $recipientAddress, $bcReferenceID = 0)
    {
        $db = $this->db;

        $db->where("transaction_hash", $transactionHash);
        $db->where("recipient_address", $recipientAddress);
        if($bcReferenceID > 0){
            $db->where('bc_reference_id', $bcReferenceID);

        }
        $record = $db->getOne("xun_wallet_transaction");

        if(!$record){
            $db->where("transaction_hash", $transactionHash);
            $db->where("recipient_address", $recipientAddress);
            $record = $db->getOne('xun_wallet_transaction');
        }

        return $record;
    }

    public function getWalletTransactionByID($id)
    {
        $db = $this->db;

        $db->where("id", $id);
        $record = $db->getOne("xun_wallet_transaction");

        return $record;
    }

    public function insertWalletTransaction($transactionObj)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        if($transactionObj instanceof stdClass){
            $userID = $transactionObj->userID ? $transactionObj->userID : '';
            $senderAddress = $transactionObj->senderAddress ? $transactionObj->senderAddress : '';
            $recipientAddress = $transactionObj->recipientAddress ? $transactionObj->recipientAddress : '';
            $senderUserID = $transactionObj->senderUserID ? $transactionObj->senderUserID : '';
            $recipientUserID = $transactionObj->recipientUserID ? $transactionObj->recipientUserID : '';
            $amount = $transactionObj->amount ? $transactionObj->amount : '';
            $walletType = $transactionObj->walletType ? strtolower($transactionObj->walletType) : '';
            $transactionHash = $transactionObj->transactionHash ? $transactionObj->transactionHash : '';
            $transactionToken = $transactionObj->transactionToken ? $transactionObj->transactionToken : '';
            $transactionType = $transactionObj->transactionType ? $transactionObj->transactionType : '';
            $addressType = $transactionObj->addressType ? $transactionObj->addressType : '';
            $status = $transactionObj->status ? $transactionObj->status : '';
            $escrow = $transactionObj->escrow ? $transactionObj->escrow : '0';
            $referenceID = $transactionObj->referenceID ? $transactionObj->referenceID : '';
            $escrowContractAddress = $transactionObj->escrowContractAddress ? $transactionObj->escrowContractAddress : '';
            $createdAt = $transactionObj->createdAt ? $transactionObj->createdAt : $date;
            $updatedAt = $transactionObj->updatedAt ? $transactionObj->updatedAt : $date;
            $expiresAt = $transactionObj->expiresAt ? $transactionObj->expiresAt : '';
            $fee = $transactionObj->fee ? $transactionObj->fee : '';
            $feeUnit = $transactionObj->feeUnit ? $transactionObj->feeUnit : '';
            $batchID = $transactionObj->batchID ? $transactionObj->batchID : '';
            $exchangeRate = $transactionObj->exchangeRate ? $transactionObj->exchangeRate : '0';
            $minerFeeExchangeRate = $transactionObj->minerFeeExchangeRate ?  $transactionObj->minerFeeExchangeRate : '0';
            $message = $transactionObj->message ? $transactionObj->message : '';
            $transactionHistoryTable = $transactionObj->transactionHistoryTable ? $transactionObj->transactionHistoryTable : '';
            $transactionHistoryID = $transactionObj->transactionHistoryID ? $transactionObj->transactionHistoryID : '';
            $bcReferenceID = $transactionObj->bcReferenceID ?  $transactionObj->bcReferenceID : '0';

            $insertData = array(
                "user_id" => $userID,
                "sender_address" => $senderAddress,
                "recipient_address" => $recipientAddress,
                "sender_user_id" => $senderUserID,
                "recipient_user_id" => $recipientUserID,
                "amount" => $amount,
                "wallet_type" => $walletType,
                "fee" => $fee,
                "fee_unit" => $feeUnit,
                "transaction_hash" => $transactionHash,
                "transaction_token" => $transactionToken,
                "transaction_type" => $transactionType,
                "address_type" => $addressType,
                "status" => $status,
                "escrow" => $escrow,
                "reference_id" => $referenceID,
                "bc_reference_id" => $bcReferenceID,
                "transaction_history_table" => $transactionHistoryTable,
                "transaction_history_id" => $transactionHistoryID,
                "batch_id" => $batchID,
                "escrow_contract_address" => $escrowContractAddress,
                "exchange_rate" => $exchangeRate,
                "miner_fee_exchange_rate" => $minerFeeExchangeRate,
                "message" => $message,
                "expires_at" => $expiresAt,
                "created_at" => $createdAt,
                "updated_at" => $updatedAt,
            );
        }else{
            $insertData = $transactionObj;
            unset($insertData["id"]);
        }

        $rowID = $db->insert("xun_wallet_transaction", $insertData);
        // if (!$rowID) {
        //     print_r($db);
        // }

        return $rowID;
    }

    public function updateWalletTransactionByID($transactionRecord, $transactionObj)
    {
        $db = $this->db;
        $date = date("Y-m-d H:i:s");

        $id = $transactionRecord["id"];

        $recordRecipientAddress = $transactionRecord["recipient_address"];
        $transactionRecipientAddress = $transactionObj->recipientAddress;

        $userID = $transactionObj->userID ? $transactionObj->userID : $transactionRecord["user_id"];
        $senderAddress = $transactionObj->senderAddress ? $transactionObj->senderAddress : '';
        $recipientAddress = $recordRecipientAddress ? $recordRecipientAddress : ($transactionRecipientAddress ? $transactionRecord["recipient_address"] : '');
        $status = $transactionObj->status ? $transactionObj->status : '';
        $amount = $transactionObj->amount ? $transactionObj->amount : '';
        $walletType = $transactionObj->walletType ? strtolower($transactionObj->walletType) : '';
        $fee = $transactionObj->fee ? $transactionObj->fee : '';
        $feeUnit = $transactionObj->feeUnit ? $transactionObj->feeUnit : '';
        $exchangeRate = $transactionObj->exchangeRate ? $transactionObj->exchangeRate : '';
        $minerFeeExchangeRate =$transactionObj->minerFeeExchangeRate ? $transactionObj->minerFeeExchangeRate : '';
        $bcReferenceID =$transactionObj->bcReferenceID ? $transactionObj->bcReferenceID : '';

        $updateData["user_id"] = $userID;
        $updateData["sender_address"] = $senderAddress;
        $updateData["recipient_address"] = $recipientAddress;
        $updateData["status"] = $status;
        $updateData["amount"] = $amount;
        $updateData["wallet_type"] = $walletType;
        $updateData["updated_at"] = $date;
        $updateData["fee"] = $fee;
        $updateData["fee_unit"] = $feeUnit;
        $updateData["exchange_rate"] = $exchangeRate;
        $updateData["miner_fee_exchange_rate"] = $minerFeeExchangeRate;
        $updateData['bc_reference_id'] = $bcReferenceID;

        $db->where("id", $id);
        $retVal = $db->update("xun_wallet_transaction", $updateData);

        return $retVal;
    }

    public function updateWalletTransactionTxHashAndStatusByID($transactionObj)
    {
        $db = $this->db;
        $date = date("Y-m-d H:i:s");

        $id = $transactionObj->id;
        $status = $transactionObj->status;
        $transactionHash = $transactionObj->transactionHash;
        $exchangeRate = $transactionObj->exchangeRate ? $transactionObj->exchangeRate : '0';
        $minerFeeExchangeRate = $transactionObj->minerFeeExchangeRate ?  $transactionObj->minerFeeExchangeRate : '0';
        $transactionHistoryTable = $transactionObj->transactionHistoryTable ?  $transactionObj->transactionHistoryTable : '';
        $transactionHistoryID = $transactionObj->transactionHistoryID ?  $transactionObj->transactionHistoryID : '';
        $bcReferenceID = $transactionObj->bcReferenceID ? $transactionObj->bcReferenceID : '0';

        $updateData["status"] = $status;
        $updateData["transaction_hash"] = $transactionHash;
        $updateData["miner_fee_exchange_rate"] = $minerFeeExchangeRate;
        $updateData["exchange_rate"] = $exchangeRate;
        $updateData['bc_reference_id'] = $bcReferenceID;

        $db->where("id", $id);
        $retVal = $db->update("xun_wallet_transaction", $updateData);

        if($transactionHistoryTable && $transactionHistoryID){
            unset($updateData['transaction_hash']);
            $updateData['transaction_id'] = $transactionHash;

            $db->where('id', $transactionHistoryID);
            $transactionHistoryData  = $db->getOne($transactionHistoryTable, 'id, payment_details_id');

            if($transactionHistoryData){
                $payment_details_id = $transactionHistoryData['payment_details_id'];

                unset($updateData['bc_reference_id']);
                $db->where('id', $transactionHistoryID);
                $db->update($transactionHistoryTable, $updateData);

                $updatePaymentDetails = array(
                    "fund_out_transaction_id" => $transactionHash,
                );
                $db->where('id', $payment_details_id);
                $db->update('xun_payment_details', $updatePaymentDetails);
            }

    

        }

        return $retVal;
    }

    public function updateWalletTransaction($id, $updateData){
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $db->where("id", $id);
        $db->update("xun_wallet_transaction", $updateData);
    }

    public function getEscrowReport($obj)
    {
        $db = $this->db;

        $wallet_transaction_id = $obj->wallet_transaction_id;
        $transaction_type = $obj->transaction_type;

        $db->where("wallet_transaction_id", $wallet_transaction_id);
        $db->where("transaction_type", $transaction_type);
        $escrow_report = $db->getOne("xun_escrow_report");
        return $escrow_report;
    }

    public function insertEscrowReport($obj)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $user_id = $obj->user_id;
        $wallet_transaction_id = $obj->wallet_transaction_id;
        $transaction_type = $obj->transaction_type;
        $reason = $obj->reason;
        $wallet_user_id = $obj->wallet_user_id;
        $created_at = $obj->created_at ? $obj->created_at : $date;
        $updated_at = $obj->updated_at ? $obj->updated_at : $date;

        $insert_data = array(
            "user_id" => $user_id,
            "wallet_transaction_id" => $wallet_transaction_id,
            "transaction_type" => $transaction_type,
            "reason" => $reason,
            "wallet_user_id" => $wallet_user_id,
            "created_at" => $created_at,
            "updated_at" => $updated_at
        );

        $row_id = $db->insert("xun_escrow_report", $insert_data);
        // if (!$row_id) {
        //     print_r($db);
        // }

        return $row_id;
    }
}

class XunWalletTransaction{
    private $id = 0;
    private $userID = 0;
    private $senderAddress = "";
    private $recipientAddress = "";
    private $senderUserID = 0;
    private $recipientUserID = 0;
    private $amount = 0;
    private $walletType = "";
    private $fee = 0;
    private $feeUnit = "";
    private $transactionHash = "";
    private $transactionToken = "";
    private $status = "";
    private $addressType = "";
    private $transactionType = "";
    private $escrow = 0;
    private $escrowContractAddress = "";
    private $referenceID = 0;
    private $batchID = 0;
    private $message = "";
    private $receiverReference = 0;
    private $exchangeRate = 0;
    private $minerFeeExchangeRate = 0;
    private $expiresAt = "";
    private $createdAt = "";
    private $updatedAt = "";

    /**
     * Get the value of id
     */ 
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @return  self
     */ 
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of userID
     */ 
    public function getUserID()
    {
        return $this->userID;
    }

    /**
     * Set the value of userID
     *
     * @return  self
     */ 
    public function setUserID($userID)
    {
        $this->userID = $userID;

        return $this;
    }

    /**
     * Get the value of senderAddress
     */ 
    public function getSenderAddress()
    {
        return $this->senderAddress;
    }

    /**
     * Set the value of senderAddress
     *
     * @return  self
     */ 
    public function setSenderAddress($senderAddress)
    {
        $this->senderAddress = $senderAddress;

        return $this;
    }

    /**
     * Get the value of recipientAddress
     */ 
    public function getRecipientAddress()
    {
        return $this->recipientAddress;
    }

    /**
     * Set the value of recipientAddress
     *
     * @return  self
     */ 
    public function setRecipientAddress($recipientAddress)
    {
        $this->recipientAddress = $recipientAddress;

        return $this;
    }

    /**
     * Get the value of senderUserID
     */ 
    public function getSenderUserID()
    {
        return $this->senderUserID;
    }

    /**
     * Set the value of senderUserID
     *
     * @return  self
     */ 
    public function setSenderUserID($senderUserID)
    {
        $this->senderUserID = $senderUserID;

        return $this;
    }

    /**
     * Get the value of recipientUserID
     */ 
    public function getRecipientUserID()
    {
        return $this->recipientUserID;
    }

    /**
     * Set the value of recipientUserID
     *
     * @return  self
     */ 
    public function setRecipientUserID($recipientUserID)
    {
        $this->recipientUserID = $recipientUserID;

        return $this;
    }

    /**
     * Get the value of amount
     */ 
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Set the value of amount
     *
     * @return  self
     */ 
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Get the value of walletType
     */ 
    public function getWalletType()
    {
        return $this->walletType;
    }

    /**
     * Set the value of walletType
     *
     * @return  self
     */ 
    public function setWalletType($walletType)
    {
        $this->walletType = $walletType;

        return $this;
    }

    /**
     * Get the value of fee
     */ 
    public function getFee()
    {
        return $this->fee;
    }

    /**
     * Set the value of fee
     *
     * @return  self
     */ 
    public function setFee($fee)
    {
        $this->fee = $fee;

        return $this;
    }

    /**
     * Get the value of feeUnit
     */ 
    public function getFeeUnit()
    {
        return $this->feeUnit;
    }

    /**
     * Set the value of feeUnit
     *
     * @return  self
     */ 
    public function setFeeUnit($feeUnit)
    {
        $this->feeUnit = $feeUnit;

        return $this;
    }

    /**
     * Get the value of transactionHash
     */ 
    public function getTransactionHash()
    {
        return $this->transactionHash;
    }

    /**
     * Set the value of transactionHash
     *
     * @return  self
     */ 
    public function setTransactionHash($transactionHash)
    {
        $this->transactionHash = $transactionHash;

        return $this;
    }

    /**
     * Get the value of transactionToken
     */ 
    public function getTransactionToken()
    {
        return $this->transactionToken;
    }

    /**
     * Set the value of transactionToken
     *
     * @return  self
     */ 
    public function setTransactionToken($transactionToken)
    {
        $this->transactionToken = $transactionToken;

        return $this;
    }

    /**
     * Get the value of status
     */ 
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set the value of status
     *
     * @return  self
     */ 
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get the value of addressType
     */ 
    public function getAddressType()
    {
        return $this->addressType;
    }

    /**
     * Set the value of addressType
     *
     * @return  self
     */ 
    public function setAddressType($addressType)
    {
        $this->addressType = $addressType;

        return $this;
    }

    /**
     * Get the value of transactionType
     */ 
    public function getTransactionType()
    {
        return $this->transactionType;
    }

    /**
     * Set the value of transactionType
     *
     * @return  self
     */ 
    public function setTransactionType($transactionType)
    {
        $this->transactionType = $transactionType;

        return $this;
    }

    /**
     * Get the value of escrow
     */ 
    public function getEscrow()
    {
        return $this->escrow;
    }

    /**
     * Set the value of escrow
     *
     * @return  self
     */ 
    public function setEscrow($escrow)
    {
        $this->escrow = $escrow;

        return $this;
    }

    /**
     * Get the value of escrowContractAddress
     */ 
    public function getEscrowContractAddress()
    {
        return $this->escrowContractAddress;
    }

    /**
     * Set the value of escrowContractAddress
     *
     * @return  self
     */ 
    public function setEscrowContractAddress($escrowContractAddress)
    {
        $this->escrowContractAddress = $escrowContractAddress;

        return $this;
    }

    /**
     * Get the value of referenceID
     */ 
    public function getReferenceID()
    {
        return $this->referenceID;
    }

    /**
     * Set the value of referenceID
     *
     * @return  self
     */ 
    public function setReferenceID($referenceID)
    {
        $this->referenceID = $referenceID;

        return $this;
    }

    /**
     * Get the value of batchID
     */ 
    public function getBatchID()
    {
        return $this->batchID;
    }

    /**
     * Set the value of batchID
     *
     * @return  self
     */ 
    public function setBatchID($batchID)
    {
        $this->batchID = $batchID;

        return $this;
    }

    /**
     * Get the value of message
     */ 
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Set the value of message
     *
     * @return  self
     */ 
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Get the value of receiverReference
     */ 
    public function getReceiverReference()
    {
        return $this->receiverReference;
    }

    /**
     * Set the value of receiverReference
     *
     * @return  self
     */ 
    public function setReceiverReference($receiverReference)
    {
        $this->receiverReference = $receiverReference;

        return $this;
    }

    /**
     * Get the value of exchangeRate
     */ 
    public function getExchangeRate()
    {
        return $this->exchangeRate;
    }

    /**
     * Set the value of exchangeRate
     *
     * @return  self
     */ 
    public function setExchangeRate($exchangeRate)
    {
        $this->exchangeRate = $exchangeRate;

        return $this;
    }

    /**
     * Get the value of minerFeeExchangeRate
     */ 
    public function getMinerFeeExchangeRate()
    {
        return $this->minerFeeExchangeRate;
    }

    /**
     * Set the value of minerFeeExchangeRate
     *
     * @return  self
     */ 
    public function setMinerFeeExchangeRate($minerFeeExchangeRate)
    {
        $this->minerFeeExchangeRate = $minerFeeExchangeRate;

        return $this;
    }

    /**
     * Get the value of expiresAt
     */ 
    public function getExpiresAt()
    {
        return $this->expiresAt;
    }

    /**
     * Set the value of expiresAt
     *
     * @return  self
     */ 
    public function setExpiresAt($expiresAt)
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    /**
     * Get the value of createdAt
     */ 
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set the value of createdAt
     *
     * @return  self
     */ 
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get the value of updatedAt
     */ 
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set the value of updatedAt
     *
     * @return  self
     */ 
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}