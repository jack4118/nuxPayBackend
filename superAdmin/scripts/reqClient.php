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
            case "getClients":
                $inputData = $_POST['inputData'];
                
                $params = array("searchData" => $inputData,
                                "tsLoginFrom" => $_POST['tsLoginFrom'],
                                "tsLoginTo" => $_POST['tsLoginTo'],
                                "tsActivityFrom" => $_POST['tsActivityFrom'],
                                "tsActivityTo" => $_POST['tsActivityTo'],
                                "pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
                
            case "getClientSettings":
                $params = array("id" => $_POST['editId']
                               );
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
            
            case "getClientDetails":
                $params = array("id" => $_POST['editId']
                               );
                $result = $post->curl($command, $params);
                
                echo $result;
                break;

             case "getSponsorTree":
                $params = array("clientID" => $_POST['clientId'],
                                "targetID" => $_POST['targetId']?$_POST['targetId']:$_POST['clientId'],
                                "targetUsername" => $_POST['targetUsername'],
                                "viewType" => $_POST['viewType'],
                                "offsetSecs" => $_POST['offsetSecs']);
                               
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
        }
    }

?>
