<?php
	session_start();

    include ($_SERVER["DOCUMENT_ROOT"] . "/include/config.php");
	include($_SERVER["DOCUMENT_ROOT"]."/include/class.post.php");
    
    $post = new post();

	$command = $_POST['command'];

    if($_POST['type'] == 'logout'){
        session_destroy();
    }
    else{

        switch($command) {
            case "getUsers":
                $inputData = $_POST['inputData'];
                $params = array("searchData" => $inputData,
                                "pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
                
            case "deleteUser":
                $deleteData = $_POST['deleteData'];
                $params = array("id" => $deleteData);
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
            
            case "addUser":
                $params = array("fullName" => $_POST['fullName'],
                                "email"    => $_POST['email'],
                                "username" => $_POST['username'],
                                "password" => $_POST['password'],
                                "roleID"   => $_POST['roleID'],
                                "status"   => $_POST['status']
                               );
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
                
            case "getUserDetails":
                $params = array("id" => $_POST['editId']);
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
                
            case "editUser":
                $params = array("id"       => $_POST['userID'],
                                "fullName" => $_POST['fullName'],
                                "email"    => $_POST['email'],
                                "roleID"   => $_POST['roleID'],
                                "status"   => $_POST['status']
                               );
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
                
            case "getRoles":
                $inputData = $_POST['inputData'];
                $getActiveRoles = $_POST['getActiveRoles'];
                $params = array("searchData" => $inputData,
                                "pageNumber" => $_POST['pageNumber'],
                                "getActiveRoles" => $getActiveRoles,
                                "site" => $_POST['site'],
                               );
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
            
            case "deleteRole":
                $deleteData = $_POST['deleteData'];
                $params = array("id" => $deleteData);
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
            
            case "addRole":
                $params = array("roleName" => $_POST['roleName'],
                                "description" => $_POST['description'],
                                "status" => $_POST['status']
                               );
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
                
            case "getRoleDetails":
                $params = array("id" => $_POST['editId']
                               );
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
                
            case "editRole":
                $params = array("id" => $_POST['roleID'],
                                "roleName" => $_POST['roleName'],
                                "description" => $_POST['description'],
                                "status" => $_POST['status']
                               );
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
            
        }
    }

?>
