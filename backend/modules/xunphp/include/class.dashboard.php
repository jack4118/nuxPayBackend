<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * Date  25/04/2018.
    **/

    class Dashboard {
        
        function __construct($db, $announcement, $cash, $admin) {
            $this->db = $db;
            $this->announcement = $announcement;
            $this->cash = $cash;
            $this->admin = $admin;
        }

        public function getDashboard($params) {
            $db = $this->db;
            $announcement = $this->announcement;
            $cash = $this->cash;
            $admin = $this->admin;

            $clientID = $params['clientID'];

            if(empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to load dashboard.", 'data' => "");

            // Get credit types setting
            $db->where('name', 'is%', 'LIKE');
            $creditTypesSetting = $db->get('credit_setting', null, 'credit_id, name, value');
            if(empty($creditTypesSetting))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            // Get is wallet credit types
            $isWallet  = $db->subQuery();
            $isWallet->where('name', "isWallet");
            $isWallet->where('value', 1);
            $isWallet->get('credit_setting', null, 'credit_id');
            $db->where('id', $isWallet, 'IN');
            $result = $db->get('credit', null, 'id, name, translation_code');
            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            foreach($result as $value) {
                $wallet['name'] = $value['name'];
                $wallet['translation_code'] = $value['translation_code'];
                $wallet['balance'] = $cash->getBalance($clientID, $value["name"]);
                $wallet['id'] = $value['id'];

                $walletList[] = $wallet;
            }
            unset($result);

            // Get client blocked rights
            $column = array(
                "client_id",
                "(SELECT name FROM mlm_client_rights WHERE id = rights_id) AS right_name",
                "(SELECT credit_id FROM mlm_client_rights WHERE id = rights_id) AS credit_id"
            );
            $db->where("client_id", $clientID);
            $db->where("(SELECT credit_id FROM mlm_client_rights WHERE id = rights_id)", "", "!=");
            $result = $db->get("mlm_client_blocked_rights", NULL, $column);

            // Client blocked rights
            $data['blockedRights'] = $result;

            // News list
            $result = $announcement->dashboardNews();
            if($result['status'] == "ok")
                $data['news'] = $result['data']['news'];

            // Wallet list
            $data['wallet'] = $this->getWallets($params);

            // Portfolio list
            $argu['pageNumber'] = 1;
            $portfolio = $admin->getPortfolioList($argu);
            $data['portfolio'] = $portfolio['data'];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getWallets($params){

            $db = $this->db;
            $announcement = $this->announcement;
            $cash = $this->cash;
            $admin = $this->admin;

            $clientID = $params['clientID'];

            if(empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to load dashboard.", 'data' => "");

            // Get credit types setting
            $db->where('name', 'is%', 'LIKE');
            $creditTypesSetting = $db->get('credit_setting', null, 'credit_id, name, member');
            if(empty($creditTypesSetting))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            // Get is wallet credit types
            $isWallet = $db->subQuery();
            $isWallet->where('name', "isWallet");
            $isWallet->where('value', 1);
            $isWallet->get('credit_setting', null, 'credit_id');
            $db->where('id', $isWallet, 'IN');
            $result = $db->get('credit', null, 'id, name, translation_code');
            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            foreach($result as $value) {
                $wallet['name'] = $value['name'];
                $wallet['translation_code'] = $value['translation_code'];
                $wallet['balance'] = $cash->getBalance($clientID, $value["name"]);
                $wallet['id'] = $value['id'];

                foreach($creditTypesSetting as $creditSetting){
                    if ($creditSetting['credit_id'] == $value['id']){
                        $wallet[$creditSetting['name']] = $creditSetting['member'];
                    }
                }

                $walletList[] = $wallet;
            }

            return $walletList;
        }
    }
?>