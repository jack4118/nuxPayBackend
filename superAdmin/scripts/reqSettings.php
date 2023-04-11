<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the System Settings related conditions.
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
            case "newSetting":
                $params = array("name"      => $_POST['name'],
                                "type"      => $_POST['type'],
                                "value"     => $_POST['value'],
                                "reference" => $_POST['reference'],
                                "module"    => $_POST['module']
                                );
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getSettingsList":
                $inputData = $_POST['inputData'];
                $params = array("searchData" => $inputData,
                                "pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "deleteSettings":
                $deleteData = $_POST['deleteData'];
                $params = array("id" => $deleteData);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getSettingData":
                $params = array("id" => $_POST['settingId']);
                $result = $post->curl($command, $params);
                
                echo $result;
                break;

            case "editSettingData":
                $params = array("id"        => $_POST['settingId'],
                                "name"      => $_POST['name'],
                                "type"      => $_POST['type'],
                                "reference" => $_POST['reference'],
                                "value"     => $_POST['value'],
                                "module"    => $_POST['module']
                               );
                $result = $post->curl($command, $params);

                echo $result;
                break;
        }
    }
?>
