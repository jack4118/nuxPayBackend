<?php
class XunCoins
{

    public function __construct($db, $setting)
    {
        $this->db = $db;
        $this->setting = $setting;
    }

    public function getCoin($obj){
        $db = $this->db;

        $currencyID = $obj->currencyID;
        if(is_array($currencyID) && !empty($currencyID)){
            $db->where("currency_id", $currencyID, "IN");
            $data = $db->get("xun_coins");
        }else if($currencyID){
            $db->where("currency_id", $currencyID);
            $data = $db->getOne("xun_coins");
        }

        return $data;
    }

    public function getPayCoins(){
        $db = $this->db;

        $db->where("is_pay", 1);
        $data = $db->map("currency_id")->ArrayBuilder()->get("xun_coins");

        return $data;
    }

    public function getCoinSetting($setting = null){
        $db = $this->db;

        if(!$setting){
            return;
        }
        $db->where($setting, 1);
        $data = $db->map("currency_id")->ArrayBuilder()->get("xun_coins");

        return $data;
    }

    public function checkCoinSetting($setting, $currencyID){
        $db = $this->db;

        if(!$setting){
            throw new Exception("Invalid coin settings");
        }

        if(!$currencyID){
            throw new Exception("Currency ID cannot be empty");
        }
        
        $db->where($setting, 1);
        $db->where("currency_id", $currencyID);
        
        $data = $db->getOne("xun_coins");
        return $data;
    }

    public function getBusinessCoinInfo($obj, $columns = null, $mapColumn = null){
        $db = $this->db;

        $walletType = $obj->walletType;

        if(is_array($walletType) && !empty($walletType)){
            $db->where("wallet_type", $walletType, "IN");

            if(!is_null($mapColumn)){
                $db->map($mapColumn)->ArrayBuilder();
            }

            $data = $db->get("xun_business_coin", null, $columns);
        }else if($walletType){
            $db->where("wallet_type", $walletType);
            $data = $db->getOne("xun_business_coin", $columns);
        }

        return $data;
    }

}
?>