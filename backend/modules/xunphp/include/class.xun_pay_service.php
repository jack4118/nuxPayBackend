<?php
class XunPayService
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->payModel = new XunPayModel($db);
    }

    public function getActivePayProductType($columns = null)
    {
        $payModel = $this->payModel;
        $result = $payModel->getActivePayProductType($columns);

        return $result;
    }

    public function getActiveProductById($obj, $columns = null)
    {
        $payModel = $this->payModel;
        $result = $payModel->getActiveProductById($obj, $columns);

        return $result;
    }

    public function getProduct($obj, $columns = null)
    {
        $payModel = $this->payModel;
        $result = $payModel->getProduct($obj, $columns);

        return $result;
    }

    public function getProductList($params, $columns = null)
    {
        $payModel = $this->payModel;
        $result = $payModel->getProductList($params, $columns);

        return $result;
    }

    public function getProductTypeMap($obj, $columns = null)
    {
        $payModel = $this->payModel;
        $result = $payModel->getProductTypeMap($obj, $columns);

        return $result;
    }

    public function getProductOption($obj, $columns = null)
    {
        $payModel = $this->payModel;
        $result = $payModel->getProductOption($obj, $columns);

        return $result;
    }

    public function getFrequentlyUsedProductList($obj, $columns = null)
    {
        $payModel = $this->payModel;
        $result = $payModel->getFrequentlyUsedProductList($obj, $columns);

        return $result;
    }

    public function getTopProductTypeByCountyCode($obj, $columns = null)
    {
        $payModel = $this->payModel;
        $result = $payModel->getTopProductTypeByCountyCode($obj, $columns);

        return $result;
    }

    public function getPopularProductByCountryCode($obj, $limit = null, $columns = null)
    {
        $payModel = $this->payModel;

        $result = $payModel->getPopularProductByCountryCode($obj, $limit, $columns);

        return $result;
    }

    public function getProductCountryByType($obj)
    {
        $payModel = $this->payModel;
        $result = $payModel->getProductCountryByType($obj);

        return $result;
    }

    public function searchProductList($params, $map = false, $columns = null)
    {
        $payModel = $this->payModel;

        $result = $payModel->searchProductList($params, $map, $columns);

        return $result;
    }

    public function getProductListingByID($obj, $map = false, $columns = null)
    {
        $payModel = $this->payModel;

        $ids = $obj->ids;
        if (empty($ids)) {
            return [];
        }

        $result = $payModel->getProductListingByID($obj, $map, $columns);

        return $result;
    }

    public function getProductTypeListingByID($obj, $map = false, $columns = null)
    {
        $payModel = $this->payModel;

        $result = $payModel->getProductTypeListingByID($obj, $map, $columns);

        return $result;
    }

    public function getProductTransaction($obj, $columns = null)
    {
        $payModel = $this->payModel;

        $result = $payModel->getProductTransaction($obj, $columns);

        return $result;
    }

    public function getProductTransactionPagination($obj, $columns = null)
    {
        $payModel = $this->payModel;

        $result = $payModel->getProductTransactionPagination($obj, $columns);

        return $result;
    }

    public function getPayTransactionItemList($obj, $columns = null)
    {
        $payModel = $this->payModel;

        $result = $payModel->getPayTransactionItemList($obj, $columns);

        return $result;
    }

    public function insertPayTransactionItem($obj){
        $payModel = $this->payModel;

        $result = $payModel->insertPayTransactionItem($obj);

        return $result;
    }
    
    public function updatePayTransactionItemStatus($obj){
        $payModel = $this->payModel;

        $result = $payModel->updatePayTransactionItemStatus($obj);

        return $result;
    }
    
    public function getPayTransactionItem($obj, $columns = null){
        $payModel = $this->payModel;

        $result = $payModel->getPayTransactionItem($obj, $columns);

        return $result;
    }

    public function getPayTransactionDetails($obj, $columns = null){
        $payModel = $this->payModel;

        $result = $payModel->getPayTransactionDetails($obj, $columns);

        return $result;
    }
    
    public function updatePayTransactionItem($obj){
        $payModel = $this->payModel;

        $result = $payModel->updatePayTransactionItem($obj);

        return $result;
    }
}
