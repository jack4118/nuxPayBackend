<?php

class XunPayModel
{
    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getActivePayProductType($columns = null)
    {
        $db = $this->db;

        $db->where("status", 1);
        $data = $db->get("xun_pay_product_type", null, $columns);

        return $data;
    }

    public function getActiveProductById($obj, $columns = null)
    {
        $db = $this->db;

        $id = $obj->id;

        $db->where("id", $id);
        $db->where("active", 1);
        $data = $db->getOne("xun_pay_product", $columns);

        return $data;
    }

    public function getProduct($obj, $columns = null)
    {
        $db = $this->db;

        $id = $obj->id;

        if ($id) {
            $db->where("id", $id);
        }
        $data = $db->getOne("xun_pay_product", $columns);

        return $data;
    }

    public function getProductList($params, $columns = null)
    {
        $db = $this->db;

        $name = $params["name"];
        $typeIdArr = $params["type_arr"];
        $countryCodeArr = $params["country_iso_code2"];
        $pageSize = $params["page_size"];
        $order = $params["order"];
        $page = $params["page"];
        $isOldVersion = $params["is_old_version"];
        $pagination = $params["pagination"];
        $pagination = isset($pagination) && $pagination === false ? false : true;
        $active = $params["active"];

        $order = $order ? $order : "DESC";

        if ($page) {
            if ($page < 1) {
                $page = 1;
            }
            $start_limit = ($page - 1) * $pageSize;
            $limit = array($start_limit, $pageSize);
        } else {
            $limit = null;
        }

        if (is_array($typeIdArr) && !empty($typeIdArr)) {
            $productTypeObj = new stdClass();
            $productTypeObj->typeIdArr = $typeIdArr;
            $productTypeMap = $this->getProductTypeMap($productTypeObj);
            $productIdArr = array_column($productTypeMap, "product_id");
        } else if ($isOldVersion) {
            $productTypeObj = new stdClass();
            $productTypeObj->typeIdArr = [1, 2];
            $productTypeMap = $this->getProductTypeMap($productTypeObj);
            $productIdArr = array_column($productTypeMap, "product_id");
        }
        if (isset($productIdArr) && !empty($productIdArr)) {
            $db->where("id", $productIdArr, "in");
        }
        if (!empty($name)) {
            $db->where("name", "%$name%", "LIKE");
        }
        if ($countryCodeArr) {
            $db->where("country_iso_code2", $countryCodeArr, "in");
        }
        if ($pagination) {
            $db->where("active", 1);
        } else if (isset($active)) {
            $db->where("active", $active);
        }
        $copyDb = $db->copy();

        $db->orderBy("name", $order);

        $data = $db->get("xun_pay_product", $limit, $columns);

        $returnData = [];
        if ($pagination == true) {
            $totalRecord = $copyDb->getValue("xun_pay_product", "count(id)");

            $returnData["total_record"] = $totalRecord;
            $returnData["num_record"] = (int) $pageSize;
            $returnData["total_page"] = ceil($totalRecord / $pageSize);
            $returnData["page_number"] = (int) $page;
        }

        return array("data" => $data, "page_details" => $returnData);
    }

    public function searchProductList($params, $map = false, $columns = null)
    {
        $db = $this->db;

        $name = $params["name"];
        $typeIdArr = $params["type_arr"];
        $countryCodeArr = $params["country_iso_code2"];
        $active = $params["active"];

        if (is_array($typeIdArr) && !empty($typeIdArr)) {
            $productTypeObj = new stdClass();
            $productTypeObj->typeIdArr = $typeIdArr;
            $productTypeMap = $this->getProductTypeMap($productTypeObj);
            $productIdArr = array_column($productTypeMap, "product_id");
        }

        if (isset($productIdArr) && !empty($productIdArr)) {
            $db->where("id", $productIdArr, "in");
        }
        if (!empty($name)) {
            $db->where("name", "%$name%", "LIKE");
        }
        if ($countryCodeArr) {
            $db->where("country_iso_code2", $countryCodeArr, "in");
        }
        if (isset($active)) {
            $db->where("active", $active);
        }

        if ($map == true) {
            $db->map("id")->ArrayBuilder();
        }

        $data = $db->get("xun_pay_product", $limit, $columns);

        return $data;
    }

    public function getProductTypeMap($obj, $columns = null)
    {
        $db = $this->db;

        $typeIdArr = $obj->typeIdArr;
        $productIdArr = $obj->productIdArr;

        if (!empty($productIdArr)) {
            $db->where("product_id", $productIdArr, "in");
        }

        if (!empty($typeIdArr)) {
            $db->where("type_id", $typeIdArr, "in");
        }

        $data = $db->get("xun_pay_product_product_type_map", null, $columns);
        // print_r($db);
        // print_r($data);
        return $data;
    }

    public function getProductOption($obj, $columns = null)
    {
        $db = $this->db;

        $pid = $obj->pid;
        if ($pid) {
            $db->where("pid", $pid);
        }

        $data = $db->getOne("xun_pay_product_option", null, $columns);

        return $data;
    }
    public function getFrequentlyUsedProductList($obj, $columns = null)
    {
        $db = $this->db;

        $userID = $obj->userID;
        $limit = $obj->limit;

        $db->where("user_id", $userID);
        $db->orderBy("sort_order", "ASC");
        $data = $db->get("xun_pay_frequently_used_product", $limit, $columns);

        return $data;
    }

    public function getTopProductTypeByCountyCode($obj, $columns = null)
    {
        $db = $this->db;

        $limit = $obj->limit;

        $countryIsoCode2 = $obj->countryIsoCode2;

        if (is_array($countryIsoCode2) && !empty($countryIsoCode2)) {
            $db->where("country_iso_code2", $countryIsoCode2, "in");
        }
        // $db->orderBy("sort_order", "ASC");

        $data = $db->get("xun_pay_top_product_type", $limit, $columns);

        return $data;
    }

    public function getPopularProductByCountryCode($obj, $limit = null, $columns = null)
    {
        $db = $this->db;

        $countryIsoCode2 = $obj->countryIsoCode2;
        $isOldVersion = $obj->isOldVersion;
        // select * from xun_pay_product where country_code in $countryIsoCode2 order by popularity DESC
        if ($isOldVersion === true) {
            $db->where("type", [1, 2], "in");
        }
        if (is_array($countryIsoCode2) && !empty($countryIsoCode2)) {
            $db->where("country_iso_code2", $countryIsoCode2, "in");
        }
        $db->where("active", 1);
        $db->orderBy("popularity", "DESC");
        $data = $db->get("xun_pay_product", $limit, $columns);

        return $data;
    }

    public function getProductListingByID($obj, $map = false, $columns = null)
    {
        $db = $this->db;

        $ids = $obj->ids;

        $db->where("id", $ids, "in");
        if ($map === true) {
            $db->map("id")->ArrayBuilder();
        }

        $data = $db->get("xun_pay_product", null, $columns);

        return $data;
    }

    public function getProductTypeListingByID($obj, $map = false, $columns = null)
    {
        $db = $this->db;

        $ids = $obj->ids;

        if (!is_null($ids)) {
            $db->where("id", $ids, "in");
        }
        $db->where("status", 1);
        if ($map === true) {
            $db->map("id")->ArrayBuilder();
        }

        $data = $db->get("xun_pay_product_type", null, $columns);

        return $data;
    }

    public function getProductCountryByType($obj)
    {
        $db = $this->db;

        $type = $obj->type;
        $isOldVersion = $obj->isOldVersion;

        if ($isOldVersion == true) {
            $db->where("type", [1, 2], "in");
        } else {
            if (!is_null($type)) {
                $db->where("type", $type);
            }
        }

        $db->where("active", 1);
        $data = $db->getValue("xun_pay_product", "distinct(country_iso_code2)", null);

        return $data;
    }

    public function getProductTransaction($obj, $columns = null)
    {
        $db = $this->db;

        $id = $obj->id;
        $walletTransactionId = $obj->walletTransactionId;
        $providerTransactionId = $obj->providerTransactionId;
        $joinWalletTransaction = $obj->joinWalletTransaction;

        if ($id) {
            if ($joinWalletTransaction) {
                $db->where("a.id", $id);
            } else {
                $db->where("id", $id);
            }
        }
        if ($walletTransactionId) {
            $db->where("wallet_transaction_id", $walletTransactionId);
        }

        if ($providerTransactionId) {
            $db->where("provider_transaction_id", $providerTransactionId);
        }

        //  select * from pay_transaction a join wallet_transaction b on a.wallet_transaction_id = b.id where a.id = $id
        /**
         * $db->where("a.user_id", $user_id);
        $db->where("a.status", 1);
        $db->join("xun_marketplace_payment_method b", "a.payment_method_id=b.id", "LEFT");
        $db->orderBy("b.id", "ASC");
        $user_payment_methods = $db->get("xun_marketplace_user_payment_method a", null, "a.id as id, b.id as payment_method_id, b.name as payment_method_name, b.image as payment_method_image, b.payment_type, b.country");
         */

        if ($joinWalletTransaction) {
            $db->join("xun_wallet_transaction b", "a.wallet_transaction_id=b.id", "LEFT");
            $data = $db->getOne("xun_pay_transaction a", $columns);

        } else {
            $data = $db->getOne("xun_pay_transaction", $columns);
        }

        return $data;
    }

    public function getProductTransactionPagination($obj, $columns = null)
    {
        $db = $this->db;

        $userID = $obj->userID;
        $productIdArr = $obj->productIdArr;
        $statusArr = $obj->statusArr;
        $from = $obj->from;
        $to = $obj->to;

        $pageSize = $obj->pageSize;
        $order = $obj->order;
        $page = $obj->page;

        $page = $page ? $page : 1;

        if ($page) {
            if ($page < 1) {
                $page = 1;
            }
            $start_limit = ($page - 1) * $pageSize;
            $limit = array($start_limit, $pageSize);
        }

        $db->where("user_id", $userID);

        if (!empty($productIdArr)) {
            $db->where("a.product_id", $productIdArr, "in");
        }
        if (!empty($statusArr)) {
            $db->where("b.status", $statusArr, "in");
        }
        if (!empty($from)) {
            $db->where("b.created_at", $from, ">=");
        }
        if (!empty($to)) {
            $db->where("b.created_at", $to, "<");
        }

        $db->join("xun_pay_transaction_item b", "a.id=b.pay_transaction_id", "LEFT");
        $copyDb = $db->copy();
        $db->orderBy("b.created_at", $order);
        $db->orderBy("b.id", $order);
        

        $data = $db->get("xun_pay_transaction a", $limit, $columns);

        $returnData = [];
        $totalRecord = $copyDb->getValue("xun_pay_transaction a", "count(*)");

        $returnData["total_record"] = $totalRecord;
        $returnData["num_record"] = (int) $db->count;
        $returnData["total_page"] = ceil($totalRecord / $pageSize);
        $returnData["page_number"] = (int) $page;

        return array("data" => $data, "page_details" => $returnData);

    }

    public function insertPayTransactionItem($obj){
        global $log;
        $db = $this->db;

        $payTransactionId = $obj->payTransactionId;
        $paymentId = $obj->paymentId;
        $status = $obj->status;
        $message = $obj->message;
        $action = $obj->action;
        $code = $obj->code;
        $expiredDate = $obj->expiredDate;
        $date = date("Y-m-d H:i:s");

        $message = $message ? $message : "";
        $code = $code ? $code : "";

        $insertData = array(
            "pay_transaction_id" => $payTransactionId,
            "payment_id" => $paymentId,
            "status" => $status,
            "message" => $message,
            "action" => $action,
            "code" => $code,
            "expired_date" => $expiredDate,
            "created_at" => $date,
            "updated_at" => $date
        );

        $rowId = $db->insert("xun_pay_transaction_item", $insertData);
        if(!$rowId){
            // print_r($insertData);
            // print_r($db->getLastError());

            $log->write(date("Y-m-d H:i:s") . " - Error inserting to xun_pay_transaction_item. " . $db->getLastError());
        }

        return $rowId;
    }

    public function getPayTransactionItemList($obj, $columns){
        $db = $this->db;

        $payTransactionId = $obj->payTransactionId;
        
        $db->where("pay_transaction_id", $payTransactionId);
        $data = $db->get("xun_pay_transaction_item", null, $columns);

        return $data;
    }

    public function updatePayTransactionItemStatus($obj)
    {
        $db = $this->db;

        $id = $obj->id;
        $status = $obj->status;
        $date = date("Y-m-d H:i:s");

        $updateData = [];
        $updateData["status"] = $status;
        $updateData["updated_at"] = $date;

        $db->where("id", $id);
        $db->where("status", $status, "!=");
        $retVal = $db->update("xun_pay_transaction_item", $updateData);
        return $retVal;
    }

    public function getPayTransactionItem($obj, $columns = null){
        $db = $this->db;

        $payTransactionId = $obj->payTransactionId;
        $orderId = $obj->orderId;

        $db->where("pay_transaction_id", $payTransactionId);
        $db->where("order_id", $orderId);
        $data = $db->getOne("xun_pay_transaction_item", $columns);
        return $data;
    }

    public function getPayTransactionDetails($obj, $columns = null)
    {
        $db = $this->db;

        $id = $obj->id;
        $joinWalletTransaction = $obj->joinWalletTransaction;

        if ($id) {
            if ($joinWalletTransaction) {
                $db->where("a.id", $id);
            } else {
                $db->where("id", $id);
            }
        }

        if ($joinWalletTransaction) {
            $db->join("xun_pay_transaction b", "a.pay_transaction_id=b.id", "LEFT");
            $db->join("xun_wallet_transaction c", "b.wallet_transaction_id=c.id", "LEFT");

            $data = $db->getOne("xun_pay_transaction_item a", $columns);

        } else {
            $data = $db->getOne("xun_pay_transaction_item", $columns);
        }

        return $data;
    }

    public function updatePayTransactionItem($obj){
        global $log;
        $db = $this->db;

        $id = $obj->id;
        $status = $obj->status;
        $message = $obj->message;
        $action = $obj->action;
        $code = $obj->code;
        $expiredDate = $obj->expiredDate;
        $date = date("Y-m-d H:i:s");

        $message = $message ? $message : "";
        $code = $code ? $code : "";

        $updateData = array(
            "status" => $status,
            "message" => $message,
            "action" => $action,
            "code" => $code,
            "expired_date" => $expiredDate,
            "updated_at" => $date
        );

        $db->where("id", $id);
        $rowId = $db->update("xun_pay_transaction_item", $updateData);
        if(!$rowId){

            $log->write(date("Y-m-d H:i:s") . " - Error updating xun_pay_transaction_item. " . $db->getLastError());
        }
        return $rowId;
    }
}
