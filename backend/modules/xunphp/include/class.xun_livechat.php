<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the API Related Database code.
     * Date  29/06/2017.
    **/
    class XunLivechat {
        
        function __construct($db, $post) {
            $this->db = $db;
            $this->post = $post;
        }
        
        function save_livechat_transcript($url_string, $params){
            
            global $config;
            
            $db = $this->db;
            $post = $this->post;
            
            $target_email = $params["target_email"];
            $transcript = $params["transcript"];
            $created_at = date("Y-m-d H:i:s");
            $reference_id = $db->getNewID();
            
            $fields = array("target_email", "transcript", "reference_id", "created_at");
            $values = array($target_email, $transcript, $reference_id, $created_at);
            $insertData = array_combine($fields, $values);
            
            $db->insert("xun_livechat_transcript", $insertData);
            
            return array("status" => "ok", "statusMsg" => "success", "code" => "1", "params" => $params);
        }
        
    }

?>