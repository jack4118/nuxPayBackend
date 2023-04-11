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

            case "getActivity":
                
                $params = array(
                                "searchData"       => $_POST['searchData'],
                                "pageNumber"       => $_POST["pageNumber"],
                                "offsetSecs"       => $_POST["offsetSecs"]
                                );
                
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
        }
    }

?>