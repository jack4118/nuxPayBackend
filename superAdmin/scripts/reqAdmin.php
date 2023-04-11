<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the Permission related conditions.
     * Date  21/07/2017.
    **/
	session_start();

    include ($_SERVER["DOCUMENT_ROOT"] . "/include/config.php");
	include($_SERVER["DOCUMENT_ROOT"]."/include/class.post.php");
    
    $post = new post();

	$command = $_POST['command'];

    $username   = $_SESSION['username'];
    $userId     = $_SESSION['userId'];
    $sessionID  = $_SESSION['sessionID'];

    if($_POST['type'] == 'logout'){
        session_destroy();
    }
    else{
        switch($command) {
            
            case "getAdmins":
                
                $params = array("searchData" => $_POST['inputData'],
                                "searchDate" => $_POST['searchDate'],
                                "pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getAdminDetails":

                $params = array("id" => $_POST['id']);
                               
                $result = $post->curl($command, $params);
                
                echo $result;
                break;

            case "addAdmin":
                
                $params = array("fullName" => $_POST['fullName'],
                                "username" => $_POST['username'],
                                "email" => $_POST['email'],
                                "password" => $_POST['password'],
                                "roleID" => $_POST['roleID'],
                                "status" => $_POST['status']);
                                

                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "editAdmin":
                
                $params = array("id" => $_POST['id'],
                                "fullName" => $_POST['fullName'],
                                "username" => $_POST['username'],
                                "email" => $_POST['email'],
                                "roleID" => $_POST['roleID'],
                                "status" => $_POST['status']);
                                

                $result = $post->curl($command, $params);

                echo $result;
                break;


            case "deleteAdmin":
                
                $params = array("id" => $_POST['id']);
                                
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getRoles":

                $params = array("searchData" => $_POST['inputData'],
                                "getActiveRoles" => $_POST['getActiveRoles'],
                                "site" => "Admin",
                               );
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
            
            
            

            
        }
    }
?>
