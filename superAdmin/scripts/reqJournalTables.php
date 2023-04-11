<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the System JournalTables related conditions.
     * Date  31/07/2017.
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
            case "newJournalTable":
                $params = array("tableName" => $_POST['tableName'],
                                "type" => $_POST['tableType'],
                                "disabled"  => $_POST['disabled']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getJournalTablesList":
                $inputData = $_POST['inputData'];
                
                $params    = array("searchData" => $inputData,
                                   "pageNumber" => $_POST['pageNumber']);
                $result    = $post->curl($command, $params);

                echo $result;
                break;

            case "deleteJournalTables":
                $deleteData = $_POST['deleteData'];
                $params     = array("id" => $deleteData);
                $result     = $post->curl($command, $params);
                echo $result;
                break;

            case "getJournalTableData":
                $params = array("id" => $_POST['journalTableId']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "editJournalTableData":
                $params = array("id"        => $_POST['journalTableId'],
                                "type"      => $_POST['type'],
                                "disabled"  => $_POST['disabled']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getJournalTableNames":
                $params = array("action" => $_POST["action"]);
                $result = $post->curl($command, $params);

                echo $result;
                break;
        }
    }
?>
