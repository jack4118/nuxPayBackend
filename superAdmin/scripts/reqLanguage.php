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
            case "newLanguage":
                $params = array(
                                "language"          => $_POST['language'],
                                //"languageCode"     => $_POST['languageCode'],
                                "isoCode"          => $_POST['isoCode'],
                                "status"            => $_POST['status']
                                );
                $result = $post->curl($command, $params);
                
                echo $result;
                break;

            case "getLanguageList":
                $inputData = $_POST['inputData'];
                
                $params = array("searchData" => $inputData);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "deleteLanguage":
                $deleteData = $_POST['deleteData'];
                $params = array("id" => $deleteData);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "editLanguageData":
                $params = array(
                                "id"                => $_POST['id'],
                                "languageName"      => $_POST['languageName'],
                                //"languageCode"      => $_POST['languageCode'],
                                "isoCode"           => $_POST['isoCode'],
                                "status"            => $_POST['status']
                                );
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getLanguageData":
                $params = array("id" => $_POST['editId']);
                
                $result = $post->curl($command, $params);

                echo $result;
                break;
        }
    }
?>
