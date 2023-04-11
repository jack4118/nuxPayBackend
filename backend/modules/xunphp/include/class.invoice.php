<?php

class Invoice{

    function __construct($db, $setting){

        $this->db       = $db;
        $this->setting  = $setting;

    }

    /**
     * use to generate invoice number
     * @return string
     */

    public function generateInvoiceNumber(){

        $invoiceLength      = $this->setting->getInvoiceNumberLength();

        $invoiceNumber = "";

        for ($i = 0; $i < $invoiceLength; $i++) {

            $invoiceNumber .= mt_rand(0, 9);
        }

        return $invoiceNumber;
    }

    /**
     * @param $clientId
     * @param $totalAmount
     * @param array $products
     * @param array $wallets
     * @return bool
     * @internal param $portfolioId
     * @internal param $amount
     */

    public function insertFullInvoice($clientId, $totalAmount, array $products, array $wallets){

        $db                 = $this->db;
        $tableName          = "mlm_invoice";
        $invoiceNo          = "";

        if (empty($clientId) || !is_numeric($clientId)) {
            return false;
        }

        if ($totalAmount < 0 || !is_numeric($totalAmount)) {
            return false;
        }

        if (empty($products) || !is_array($products)){
            return false;
        }

        //keep generate invoice number until it is unique in database
        while(true){

            $invoiceNo = $this->generateInvoiceNumber();
            $db->where("invoice_no", $invoiceNo);
            $count = $db->getValue($tableName, "count(*)");

            if ($count == 0)
                break;
        }

        if (empty($invoiceNo)){
            echo "invoice number is not generated";
            return false;
        }

        $productsCount      = count($products);
        $invoiceId          = $this->insertInvoice($clientId, $invoiceNo, $totalAmount, $productsCount);

        if (!$invoiceId)
            return false;

        foreach($products as $product) {

            $productId      = $product['productId'];
            $bonusValue     = $product['bonusValue'];
            $productPrice   = $product['productPrice'];
            $unitPrice      = $product['unitPrice'];
            $belongId       = $product['belongId'];
            $portfolioId    = $product['portfolioId']?:'';

            $invoiceItemId = $this->insertInvoiceItem($invoiceId, $productId, $bonusValue, $productPrice, $unitPrice, $portfolioId, $belongId);

            if (!$invoiceItemId)
                return false;

            foreach ($wallets as $wallet) {

                $walletType     = $wallet['creditType'];
                $amount         = $wallet['paymentAmount'] / $productsCount;

                $invoiceItemPaymentId = $this->insertInvoiceItemPayment($invoiceId, $invoiceItemId, $productId, $walletType, $amount);

                if (!$invoiceItemPaymentId)
                    return false;

            }
        }

        return $invoiceId;
    }

    /**
     * @param $clientId
     * @param $invoiceNo
     * @param $amount
     * @param $productsCount
     * @return  $invoiceId   ****** not invoice number ******
     * @internal param $portfolioId
     */

    public function insertInvoice($clientId, $invoiceNo, $amount, $productsCount){

        $db                 = $this->db;
        $tableName          = "mlm_invoice";

        $insertData         = array(

            "client_id"     => $clientId,
            "invoice_no"    => $invoiceNo,
            "total_amount"  => $amount,
            "total_item"    => $productsCount,
            "created_at"    => $db->now()
        );

        $invoiceId = $db->insert($tableName, $insertData);

        return $invoiceId;
    }

    /**
     * @param $invoiceId
     * @param $productId
     * @param $bonusValue
     * @param $productPrice
     * @param $portfolioId
     * @param $belongId
     * @return  $invoiceItemId
     */

    public function insertInvoiceItem($invoiceId, $productId, $bonusValue, $productPrice, $unitPrice, $portfolioId, $belongId){

        $db                 = $this->db;
        $tableName          = "mlm_invoice_item";

        $insertData = array(

            "invoice_id"    => $invoiceId,
            "product_id"    => $productId,
            "bonus_value"   => $bonusValue,
            "product_price" => $productPrice,
            "unit_price"    => $unitPrice,
            "portfolio_id"  => $portfolioId,
            "belong_id"     => $belongId
        );

        $invoiceItemId = $db->insert($tableName, $insertData);

        return $invoiceItemId;
    }

    /**
     * @param $invoiceId
     * @param $invoiceItemId
     * @param $productId
     * @param $wallet
     * @param $amount
     * @return $invoiceItemPaymentId
     */

    public function insertInvoiceItemPayment($invoiceId, $invoiceItemId, $productId, $wallet, $amount){

        $db                         = $this->db;
        $tableName                  = "mlm_invoice_item_payment";

        $insertData = array(

            "invoice_item_id"   => $invoiceItemId,
            "invoice_id"        => $invoiceId,
            "product_id"        => $productId,
            "credit_type"       => $wallet,
            "amount"            => $amount,
        );

        $invoiceItemPaymentId = $db->insert($tableName, $insertData);

        return $invoiceItemPaymentId;
    }

    /**
     * @param $searchData
     * @param $limit
     * @param $column
     * @return $invoiceList
     */

    public function getInvoiceList($searchData, $limit, $column){

        $db                         = $this->db;
        $tableName                  = "mlm_invoice";

        foreach ($searchData as $array) {
            foreach ($array as $key => $value) {
                if ($key == 'dataName') {
                    $dbColumn = $value;
                }
                else if ($key == 'dataValue') {
                    foreach ($value as $innerVal) {
                        $db->where($dbColumn, $innerVal);
                    }
                }

            }
        }

        $invoiceList = $db->get($tableName, $limit, $column);

        return $invoiceList;

    }


}


?>