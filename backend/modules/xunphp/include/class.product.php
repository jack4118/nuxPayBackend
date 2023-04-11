<?php

class Product{

    function __construct($db, $setting, $general){

        $this->db = $db;
        $this->setting = $setting;
        $this->general = $general;
    }

    /**
     * @return string
     */

    public function generatePinNumber(){

        $pinNumberLength      = $this->setting->getPinNumberLength();

        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $max        = strlen($characters) - 1;

        $pinNumber = "";

        for ($i = 0; $i < $pinNumberLength; $i++) {

            $pinNumber .= $characters[mt_rand(0, $max)];
        }

        return $pinNumber;
    }

    /**
     * @param $params
     * @return pinId
     */

    public function purchaseNewPin($params){

        $db         = $this->db;
        $tableName  = "mlm_pin";

        $insertData = array(

            'product_id'        => $params['productId'],
            'code'              => $params['pinNumber'],
            'status'            => "New",
            'created_at'        => $db->now(),
            'buyer_id'          => $params['buyerId'],
            'client_id'         => $params['clientId'],
            'price'             => $params['price'],
            'bonus_value'       => $params['bonusValue'],
            'belong_id'         => $params['belongId'],
            'batch_id'          => $params['batchId'],
            'pin_type'          => $params['pinType'],
            'owner_id'          => $params['ownerId'],
            'unit_price'        => $params['unitPrice']

        );

        $pinId = $db->insert($tableName, $insertData);

        return $pinId;

    }

    /**
     * @param $pinId
     * @param $creditType
     * @param $amount
     * @return $pinPaymentId
     */

    public function pinPayment($pinId, $creditType, $amount){

        $db         = $this->db;
        $tableName  = "mlm_pin_payment";
        $insertData = array(

            'pin_id'        => $pinId,
            'credit_type'   => $creditType,
            'amount'        => $amount
        );

        $pinPaymentId   = $db->insert($tableName, $insertData);

        return $pinPaymentId;

    }

    public function getMinMaxPaymentMethod($amount, $creditType, $paymentType) {
        $db = $this->db;
        $setting = $this->setting;

        if(empty($paymentType)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify payment type", 'data' => "");
        }

        if(!empty($creditType)){
            $db->where('credit_type', $creditType);
        }

        $db->where('payment_type', $paymentType);
        $db->where('status', 'Active');
        
        // Get payment method
        $result = $db->get("mlm_payment_method", null, "id, credit_type, min_percentage, max_percentage, payment_type");

        if(empty($result)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Payment Method Not Found.", 'data' => "");
        }else{
            foreach($result as $value){
                $temp["min_percentage"] = $value["min_percentage"];
                $temp["max_percentage"] = $value["max_percentage"];
                $temp["min_usage"] = $amount * ($value["min_percentage"]/100);
                $temp["max_usage"] = $amount * ($value["max_percentage"]/100);

                $paymentMethod[$value["credit_type"]] = $temp;
            }

            return $paymentMethod;
        }
    }

    public function checkMinMaxPayment($totalAmount, $payAmount, $creditType, $paymentType){
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;
        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        if(empty($paymentType)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify payment type", 'data' => "");
        }

        if(empty($creditType)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify credit type", 'data' => "");
        }

        if(empty($totalAmount)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify total amount", 'data' => "");
        }

        if($payAmount == ""){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify pay amount", 'data' => "");
        }

        $db->where('credit_type', $creditType);
        $db->where('payment_type', $paymentType);
        $db->where('status', 'Active');
        
        // Get payment method
        $result = $db->getOne("mlm_payment_method", "credit_type, min_percentage, max_percentage, payment_type");

        if(empty($result)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Payment Method Not Found.", 'data' => "");
        }else{
            $min_usage = $totalAmount * ($result["min_percentage"]/100);
            $max_usage = $totalAmount * ($result["max_percentage"]/100);

            // check min and max
            if($min_usage && ($payAmount < $min_usage)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => str_replace("%%wallet%%", $creditType, $translations["E00474"][$language]), 'data' => array("field" => $creditType));
            }

            if($max_usage && ($payAmount > $max_usage)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => str_replace("%%wallet%%", $creditType, $translations["E00475"][$language]), 'data' => array("field" => $creditType));
            }
           
        }

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
    }

}

?>