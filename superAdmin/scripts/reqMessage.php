<?php
    /**
     * @author ttwoweb.
     * This file is contains the Webservices for messageAssigned Listing.
     *
    **/
    session_start();

    include ($_SERVER["DOCUMENT_ROOT"] . "/include/config.php");
    include($_SERVER["DOCUMENT_ROOT"]."/include/class.post.php");

    $post      = new post();
    $command   = $_POST['command'];
    $inputData = $_POST['inputData'];
    $sessionID = $_SESSION['sessionID'];
    $username  = $_SESSION['username'];
    $userID    = $_SESSION['userID'];

    if($_POST['type'] == 'logout'){
        session_destroy();
    }
    else{

        switch($command) {
            case "newMessageAssigned":
                $code = trim($_POST['msgCode']);
                $params = array("messageCode"      => $code,
                                "messageRecipient" => $_POST['msgRecipient'],
                                "messageType"      => $_POST['msgType']);

                $result = $post->curl($command, $params);
                
                echo $result;
                break;

            case "editMessageAssigned":
                $code = trim($_POST['code']);
                $params = array("id"        => $_POST['id'],
                                "code"      => $code,
                                "recipient" => $_POST['recipient'],
                                "type"      => $_POST['type']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "messageAssignedList":
                $params = array("pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "deleteMessageAssigned":
                $deleteData = $_POST['deleteData'];
                $params     = array("id" => $deleteData);
                $result     = $post->curl($command, $params);
                
                echo $result;
                break;

            case "getEditMessageAssignedData":
                $params = array("id" => $_POST['id']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

                
            case "sendMessage":
                $params = array(
                                "messageCode"      => $_POST['messageCode'],
                                "messageRecipient" => $_POST['messageRecipient'],
                                "messageType"      => $_POST['messageType']
                                );
                $result = $post->curl($command, $params);

                echo $result;
                break;
                    
            case "getMessageCode":
                $params = "";
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getMessageType":
                $params = "";
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getMessageSearchData":
                
                $params = array("searchData" => $_POST['searchData'],
                                "pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getMessageSentList":
                
                $params = array("searchData" => $_POST['searchData'],
                                "pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getMessageQueueList":
                
                $params = array("searchData" => $_POST['searchData'],
                                "pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getMessageInList" :

                $params = array("searchData" => $_POST['searchData'],
                                "pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);

                echo $result;
                break;
        }
    }
?>
