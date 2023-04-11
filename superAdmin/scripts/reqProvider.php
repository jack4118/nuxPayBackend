<?php
    /**
     * @author ttwoweb.
     * This file is contains the Webservices for Provider Listing.
     *
    **/
	session_start();

    include ($_SERVER["DOCUMENT_ROOT"] . "/include/config.php");
    include($_SERVER["DOCUMENT_ROOT"]."/include/class.post.php");

	$post      = new post();
    $command   = $_POST['command'];
    $inputData = $_POST['inputData'];
    $sessionID = $_SESSION['sessionID'];
    $username = $_SESSION['username'];
    $userID = $_SESSION['userID'];

    if($_POST['type'] == 'logout'){
        session_destroy();
    } else{
        switch($command) {
            case "newProvider":
                $params = array("commandName"   => $_POST['command'],
                               "name"  => $_POST['name'],
                               "username"       => $_POST['username'],
                               "password"       => $_POST['password'],
                               "company"        => $_POST['company'],
                               "apiKey"        => $_POST['apiKey'],
                               "type"           => $_POST['type'],
                               "priority"       => $_POST['priority'],
                               "disabled"       => $_POST['providerStatus'],
                               "defaultSender" => $_POST['defaultSender'],
                               "url1"           => $_POST['url1'],
                               "url2"           => $_POST['url2'],
                               "remark"         => $_POST['remark'],
                               "currency"       => $_POST['currency'],
                               "balance"        => $_POST['balance']);

                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "editProvider":
                $params = array("id"             => $_POST['providerId'],
                                "commandName"    => $_POST['command'],
                                "name"  => $_POST['name'],
                                "username"       => $_POST['username'],
                                "password"       => $_POST['password'],
                                "company"        => $_POST['company'],
                                "apiKey"        => $_POST['apiKey'],
                                "type"           => $_POST['type'],
                                "priority"       => $_POST['priority'],
                                "disabled"       => $_POST['disabled'],
                                "defaultSender" => $_POST['defaultSender'],
                                "url1"           => $_POST['url1'],
                                "url2"           => $_POST['url2'],
                                "remark"         => $_POST['remark'],
                                "currency"       => $_POST['currency'],
                                "balance"        => $_POST['balance']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getProviderData":
                $inputData = $_POST['inputData'];
                
                $params    = array("searchData" => $inputData,
                                   "pageNumber" => $_POST['pageNumber']);
                $result    = $post->curl($command, $params);

                echo $result;
                break;

            case "deleteProvider":
                $params = array("id" => $_POST['deleteData'],
                                "searchData" => $_POST['inputData']
                                );
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getEditProviderData":
                $params = array("id"  => $_POST['providerId']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getMessageType":
                $params = "";
                $result = $post->curl($command, $params);

                echo $result;
                break;
        }

    }
?>
