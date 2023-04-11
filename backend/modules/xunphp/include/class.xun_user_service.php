<?php

class XunUserService extends AbstractXunUser{
    function __construct($db){
        parent::__construct($db);
    }

    public function createUser($user){

    }

    public function getUserByUsername($username, $columns = null, $type = "user", $mapColumn = null){
        $userModel = $this->userModel;

        $userData = $userModel->getUserByUsername($username, $columns, $type, $mapColumn);
        return $userData;
    }

    public function getDeviceInfo($obj, $columns = null){
        $userModel = $this->userModel;

        $userData = $userModel->getDeviceInfo($obj, $columns);
        return $userData;
    }

    public function getUserByEmail($email, $columns = null){
        $userModel = $this->userModel;
        
        $userData = $userModel->getUserByEmail($email, $columns);
        return $userData;
    }
}

?>