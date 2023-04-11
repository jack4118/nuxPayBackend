<?php
	session_start();

    include ($_SERVER["DOCUMENT_ROOT"] . "/include/config.php");
	include($_SERVER["DOCUMENT_ROOT"]."/include/class.post.php");
    
    $post = new post();

	$command = $_POST['command'];
    $username = $_SESSION['username'];

    if($_POST['type'] == 'logout'){
        session_destroy();
    }
    else{

        switch($command) {
            case "getNewUpgrades":
                $result = $post->curl($command, "");

                echo $result;
                break;
            
            case "getUpgradesHistory":
                $inputData = $_POST['inputData'];
                
                $params = array("searchData" => $inputData,
                                "pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
                
            case "updateAllUpgrades":
                $params = array("username" => $username);
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
        }
    }

?>
