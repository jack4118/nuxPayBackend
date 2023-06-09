<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the System related conditions.
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
            case "getSystemList":
                $inputData = $_POST['inputData'];
                $params = array("searchData" => $inputData,
                                "pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getSystemData":
                $params = array("id" => $_POST['systemId']);
                $result = $post->curl($command, $params);
                
                echo $result;
                break;

            case "getSystemBandwidth":
                $params = array("pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
        }
    }
?>
