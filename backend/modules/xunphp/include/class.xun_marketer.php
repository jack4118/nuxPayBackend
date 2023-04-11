<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/

class XunMarketer
{

    public function __construct($db, $setting, $general)
    {
        $this->db = $db;
        $this->setting = $setting;
        $this->general = $general;
    }

    public function get_marketer_commission_balance($business_marketer_commission_id, $wallet_type)
    {
        $db = $this->db;

        $db->where('b.id', $business_marketer_commission_id);
        $db->join('xun_business_marketer_commission_scheme b', 'b.marketer_id = a.marketer_id', 'INNER');
        $reseller = $db->getOne('reseller a', 'b.marketer_id, a.id');

        $reseller_id = $reseller['id'];

        $db->where('business_marketer_commission_id', $business_marketer_commission_id);
        $db->where('reseller_id', $reseller_id);
        $db->where('wallet_type', $wallet_type);
        $sum_result = $db->getOne('xun_marketer_commission_transaction', 'SUM(credit) as sumCredit, SUM(debit) as sumDebit');

        $marketer_wallet_balance = '0.0000000';
        if ($sum_result) {
            $sum_credit = $sum_result['sumCredit'];
            $sum_debit = $sum_result['sumDebit'];

            $marketer_wallet_balance = bcsub($sum_credit, $sum_debit, 8);

        }

        return $marketer_wallet_balance;
    }

    public function get_marketer_all_commission_balance($marketer_id, $wallet_type){
        $db = $this->db;

        $db->where('b.marketer_id', $marketer_id);
        $db->where('a.wallet_type', $wallet_type);
        $db->join('xun_business_marketer_commission_scheme b', 'b.id = a.business_marketer_commission_id', 'LEFT');
        $sum_result = $db->getOne('xun_marketer_commission_transaction a', 'SUM(a.credit) as sumCredit, SUM(a.debit) as sumDebit');

        $marketer_wallet_balance = '0.0000000';
        if ($sum_result) {
            $sum_credit = $sum_result['sumCredit'];
            $sum_debit = $sum_result['sumDebit'];

            $marketer_wallet_balance = bcsub($sum_credit, $sum_debit, 8);

        }

        return $marketer_wallet_balance;
    }

}
