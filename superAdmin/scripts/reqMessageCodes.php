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
            case "saveMessageCodeData":
                $code        = $_POST['code'];
                $description = $_POST['description'];
                $content     = $_POST['content'];
                $title       = $_POST['title'];
                $module      = $_POST['module'];
                
                $params = array('code' => $code, 'content' => $content, 'description' => $description, "title" => $title, "module" => $module);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getMessageCodes":
                
                $params = array("searchData" => $_POST['searchData'],
                                "pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getMessageRows":
                
                $params = array("command"       => $command,
                                "ip"            => $_SERVER['REMOTE_ADDR'],
                                "source"        => "SuperAdmin",
                                "sourceVersion" => "sourceVersion",
                                "type"          => "type",
                                "language"      => "english",
                                "userAgent"     => $_SERVER['HTTP_USER_AGENT'],
                                "username"      => $username,
                                "userId"        => $userId,
                                "sessionID"     => $sessionID,
                                "params"        => array("searchData" => $inputData));
                $result     = $post->curl($command, $params);
                echo $result;
                break;

            case "deleteMessageCode":
                $deleteData = $_POST['deleteData'];
                $params = array("id" => $deleteData);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "editMessageCode":
                $params = array (
                                    'id' => $_POST['id'],
                                    'code' => $_POST['code'],
                                    'title' => $_POST['title'],
                                    'content' => $_POST['content'],
                                    'description' => $_POST['description'],
                                    'module' => $_POST['module']
                                );
                $result = $post->curl($command, $params);

                echo $result;
                break;
                
            case "getEditMessageCodeData":
                $params = array("id" => $_POST['messageCodeId']);
                $result = $post->curl($command, $params);

                echo $result;
                break;
        }
    }
?>
