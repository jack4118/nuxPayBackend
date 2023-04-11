<?php

class XunPaymentGateWayModel
{
    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getWalletByID($id, $columns = null)
    {
        $db = $this->db;

        $db->where("id", $id);
        $data = $db->getOne("xun_crypto_wallet", $columns);

        return $data;
    }

    public function getWalletByBusinessID($businessID, $columns = null)
    {
        $db = $this->db;

        $db->where("business_id", $businessID);
        $data = $db->get("xun_crypto_wallet", null, $columns);

        return $data;
    }

    public function getWalletByBusinessIDandWalletType($businessID, $walletType, $columns = null)
    {
        $db = $this->db;

        $db->where("business_id", $businessID);
        $db->where("type", $walletType);
        $data = $db->getOne("xun_crypto_wallet", $columns);

        return $data;
    }

    public function insertWalletRecord($obj)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $insertData = array(
            "business_id" => $obj->businessID,
            "type" => $obj->type,
            "status" => $obj->status,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $rowID = $db->insert("xun_crypto_wallet", $insertData);
        // if (!$rowID) {
        //     print_r($insertData);
        //     echo $db->getLastError();
        // }
        return $rowID;
    }

    public function updateWalletStatus($obj)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $id = $obj->id;
        $status = $obj->status;
        $updateData = [];
        $updateData["updated_at"] = $date;
        $updateData["status"] = $status;

        $db->where("id", $id);
        $retVal = $db->update("xun_crypto_wallet", $updateData);
        return $retVal;
    }

    public function getBusinessPaymentGatewayAddressByID($id, $columns = null)
    {
        $db = $this->db;

        $db->where("id", $id);

        $data = $db->getOne("xun_crypto_address", $columns);

        return $data;
    }

    public function getBusinessPaymentGatewayAddressByWalletIDandType($walletID, $type, $columns = null)
    {
        $db = $this->db;

        $db->where("wallet_id", $walletID);
        $db->where("type", $type);

        $data = $db->get("xun_crypto_address", $columns);

        return $data;
    }

    public function insertBusinessPaymentGatewayAddress($obj)
    {

        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $insertData = array(
            "wallet_id" => $obj->walletID,
            "crypto_address" => $obj->cryptoAddress,
            "status" => $obj->status,
            "type" => $obj->type,
            "created_at" => $obj->created_at ? $obj->created_at : $date,
            "updated_at" => $obj->updated_at ? $obj->updated_at : $date,
        );

        $rowID = $db->insert("xun_crypto_address", $insertData);

        // if (!$rowID) {
        //     print_r($insertData);
        //     echo $db->getLastError();
        // }

        return $rowID;
    }

    public function insertBusinessPaymentGatewayFundOutDestinationAddress($obj)
    {

        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $insertData = array(
            "wallet_id" => $obj->walletID,
            "address_id" => $obj->addressID,
            "destination_address" => $obj->destinationAddress,
            "address_type" => $obj->addressType,
            "status" => $obj->status,
            "created_at" => $obj->created_at ? $obj->created_at : $date,
            "updated_at" => $obj->updated_at ? $obj->updated_at : $date,
        );

        $rowID = $db->insert("xun_crypto_fund_out_destination_address", $insertData);

        // if (!$rowID) {
        //     print_r($insertData);
        //     echo $db->getLastError();
        // }

        return $rowID;
    }

    public function getFundOutDestinationAddress($walletID, $destAddress)
    {
        $db = $this->db;

        $db->where("a.wallet_id", $walletID);
        $db->where("a.destination_address", $destAddress);
        $db->join("xun_crypto_address b", "a.address_id = b.id", "LEFT");

        $data = $db->getOne("xun_crypto_fund_out_destination_address a", "a.id as id, a.address_id as address_id,a.address_type, b.crypto_address, b.type");

        return $data;
    }

    public function getFundOutDestinationAddressByWalletIDandAddressID($walletID, $addressID)
    {
        $db = $this->db;

        $db->where("wallet_id", $walletID);
        $db->where("address_id", $addressID);

        $data = $db->getOne("xun_crypto_fund_out_destination_address");

        return $data;
    }

    public function insertFundOutTransaction($obj)
    {

        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $insertData = array(
            "user_id" => $obj->userID,
            "wallet_transaction_id" => $obj->walletTransactionID,
            "address_id" => $obj->addressID,
            "created_at" => $obj->createdAt ? $obj->createdAt : $date,
            "updated_at" => $obj->updatedAt ? $obj->updatedAt : $date,
        );

        $rowID = $db->insert("xun_payment_gateway_fund_out_transaction", $insertData);

        // if (!$rowID) {
        //     print_r($insertData);
        //     echo $db->getLastError();
        // }

        return $rowID;
    }

    public function getFundOutTransaction($id)
    {
        $db = $this->db;

        $db->where("id", $id);

        $data = $db->getOne("xun_payment_gateway_fund_out_transaction");

        return $data;
    }

    public function getPaymentGatewayHistory($obj, $limit = null, $columns = null)
    {
        $db = $this->db;

        $walletType = $obj->walletType;
        $type = $obj->type;
        $status = $obj->status;

        $orderBy = $obj->orderBy;

        if ($walletType) {
            $db->where("wallet_type", $walletType);
        }
        if ($type) {
            $db->where("type", $type);
        }
        if ($status) {
            $db->where("status", $status);
        }
        if ($orderBy) {
            $db->orderBy("id", $orderBy);
        }

        $data = $db->get("xun_crypto_history", $limit, $columns);

        return $data;
    }

    public function getPaymentGatewayDelegateAddress($obj, $columns = null)
    {
        $db = $this->db;

        $userId = $obj->userId;
        $address = $obj->address;

        $noSearchQuery = 1;
        if ($userId) {
            $db->where("user_id", $userId);
            $noSearchQuery = 0;
        }

        if($address){
            $noSearchQuery = 0;
            $db->where("address", $address);
        }

        if($noSearchQuery){
            return false;
        }

        $data = $db->getOne("xun_payment_gateway_delegate_address", $columns);
        return $data;
    }

    public function insertPaymentGatewayDelegateAddress($params)
    {
        $db = $this->db;

        $rowId = $db->insert("xun_payment_gateway_delegate_address", $params);

        if(!$rowId){
            throw new Exception($db->getLastError());
        }

        return $rowId;
    }
}
