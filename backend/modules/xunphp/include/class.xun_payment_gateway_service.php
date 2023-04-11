<?php
class XunPaymentGatewayService
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->paymentGatewayModel = new XunPaymentGateWayModel($db);
    }

    public function getWalletByBusinessIDandWalletType($businessID, $walletType, $columns = null)
    {
        $paymentGatewayModel = $this->paymentGatewayModel;
        $result = $paymentGatewayModel->getWalletByBusinessIDandWalletType($businessID, $walletType, $columns);

        return $result;
    }

    public function insertWalletRecord($obj)
    {
        $paymentGatewayModel = $this->paymentGatewayModel;
        $result = $paymentGatewayModel->insertWalletRecord($obj);

        return $result;
    }

    public function updateWalletStatus($obj)
    {
        $paymentGatewayModel = $this->paymentGatewayModel;
        $result = $paymentGatewayModel->updateWalletStatus($obj);

        return $result;
    }

    public function createWallet($obj)
    {
        //  Usage: Create payment gateway wallet for business by wallet type
        $businessID = $obj->businessID;
        $walletType = $obj->type;
        $status = $obj->status;

        $walletRecord = $this->getWalletByBusinessIDandWalletType($businessID, $walletType);

        if ($walletRecord) {
            // update status
            if ($walletRecord["status"] != $status) {
                $obj->id = $walletRecord["id"];
                $this->updateWalletStatus($obj);
            }
        } else {
            $walletID = $this->insertWalletRecord($obj);
            $walletRecord["id"] = $walletID;
        }
        return $walletRecord;
    }

    public function getBusinessPaymentGatewayAddressByID($id, $columns = null)
    {
        $paymentGatewayModel = $this->paymentGatewayModel;
        $result = $paymentGatewayModel->getBusinessPaymentGatewayAddressByID($id, $columns);

        return $result;
    }

    public function getBusinessPaymentGatewayAddressByWalletIDandType($walletID, $type, $columns = null)
    {
        $paymentGatewayModel = $this->paymentGatewayModel;
        $result = $paymentGatewayModel->getBusinessPaymentGatewayAddressByWalletIDandType($walletID, $type, $columns);

        return $result;
    }

    public function insertBusinessPaymentGatewayAddress($obj)
    {
        $paymentGatewayModel = $this->paymentGatewayModel;
        $result = $paymentGatewayModel->insertBusinessPaymentGatewayAddress($obj);

        return $result;
    }

    public function insertBusinessPaymentGatewayFundOutDestinationAddress($obj)
    {
        $paymentGatewayModel = $this->paymentGatewayModel;
        $result = $paymentGatewayModel->insertBusinessPaymentGatewayFundOutDestinationAddress($obj);

        return $result;
    }

    public function getFundOutDestinationAddress($walletID, $destAddress)
    {
        $paymentGatewayModel = $this->paymentGatewayModel;
        $result = $paymentGatewayModel->getFundOutDestinationAddress($walletID, $destAddress);

        return $result;
    }

    public function getFundOutDestinationAddressByWalletIDandAddressID($walletID, $addressID)
    {
        $paymentGatewayModel = $this->paymentGatewayModel;
        $result = $paymentGatewayModel->getFundOutDestinationAddressByWalletIDandAddressID($walletID, $addressID);

        return $result;
    }

    public function insertFundOutTransaction($obj)
    {
        $paymentGatewayModel = $this->paymentGatewayModel;
        $result = $paymentGatewayModel->insertFundOutTransaction($obj);

        return $result;
    }

    public function getFundOutTransaction($obj)
    {
        $paymentGatewayModel = $this->paymentGatewayModel;
        $result = $paymentGatewayModel->getFundOutTransaction($obj);

        return $result;
    }

    public function getPaymentGatewayHistory($obj, $limit = null, $columns = null)
    {
        $paymentGatewayModel = $this->paymentGatewayModel;
        $result = $paymentGatewayModel->getPaymentGatewayHistory($obj, $limit, $columns);

        return $result;
    }

    public function getPaymentGatewayDelegateAddress($obj, $columns = null)
    {
        $paymentGatewayModel = $this->paymentGatewayModel;
        $result = $paymentGatewayModel->getPaymentGatewayDelegateAddress($obj, $columns);

        return $result;
    }

    public function insertPaymentGatewayDelegateAddress($params)
    {
        $paymentGatewayModel = $this->paymentGatewayModel;
        $result = $paymentGatewayModel->insertPaymentGatewayDelegateAddress($params);

        return $result;
    }

}
