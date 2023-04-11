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
            case "newPermission":
                $params = array("name"  => $_POST['name'],
                                "type"          => $_POST['type'],
                                "description"   => $_POST['description'],
                                "parent"        => $_POST['parent'],
                                "filePath"      => $_POST['filePath'],
                                "priority"      => $_POST['priority'],
                                "iconClass"     => $_POST['iconClass'],
                                "disabled"      => $_POST['disabled']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getPermissionsList":
                $inputData = $_POST['inputData'];
                
                $params = array("searchData" => $inputData,
                                "pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "deletePermissions":
                $deleteData = $_POST['deleteData'];
                $params = array("id" => $deleteData);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "editPermissionData":
                $params = array("id" => $_POST['permissionId'],
                                "name"          => $_POST['name'],
                                "type"          => $_POST['type'],
                                "description"   => $_POST['description'],
                                "parent"        => $_POST['parent'],
                                "filePath"      => $_POST['filePath'],
                                "priority"      => $_POST['priority'],
                                "iconClass"     => $_POST['iconClass'],
                                "disabled"      => $_POST['disabled']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getPermissionData":
                $params = array("id" => $_POST['permissionId']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getPermissionTree":
                $params = array();
                $result = $post->curl($command, $params);

                echo $result;
                break;

            // case "getRolePermissionList":
            //     $inputData = $_POST['inputData'];
            //     $inputData = json_encode($inputData);
            //     $params = array("command"       => $command,
            //                     "ip"            => $_SERVER['REMOTE_ADDR'],
            //                     "source"        => "SuperAdmin",
            //                     "sourceVersion" => "sourceVersion",
            //                     "type"          => "type",
            //                     "language"      => "english",
            //                     "userAgent"     => $_SERVER['HTTP_USER_AGENT'],
            //                     "username"      => $username,
            //                     "userId"        => $userId,
            //                     "sessionID"     => $sessionID,
            //                     "params"        => array("searchData" => $inputData));
            //     $result = $post->curl($command, $params);

            //     $status     = $result['status'];
            //     $code       = $result['code'];
            //     $statusMsg  = $result['statusMsg'];
            //     $data       = $result['data'];

            //     // Decode the data from the backend
            //     $data = json_decode($data);
            //     // Make it as a JSON object and encode it again
            //     $data->status    = $status;
            //     $data->code      = $code;
            //     $data->statusMsg = $statusMsg;
            //     $myJson          = json_encode($data);

            //     echo $myJson;
            //     break;

            case "newRolePermission":
                $params = array("roleName"        => $_POST['roleName'],
                                "permissionsList" => $_POST['permissions']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "deleteRolePermission":
                $deleteData = $_POST['deleteData'];
                $params = array("id" => $deleteData);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "editRolePermission":
                $params = array("roleName" => $_POST['roleName'],
                                "permissionsList" => $_POST['permissions']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getRolePermissionData":
                $params = array("id" => $_POST['roleParamId']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getRoleNames":
                $params = array();
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getPermissionNames":
                $params = array();
                $result = $post->curl($command, $params);

                echo $result;
                break;
        }
    }
?>
