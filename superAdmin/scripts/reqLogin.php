<?php
    /**
     * @author ttwoweb.
     * This file is contains the Webservices.
     *
    **/
    
	session_start();
    include($_SERVER["DOCUMENT_ROOT"]."/include/class.post.php");

    //$general = new general();
    //$language = $general->getLanguage();
	$post = new post();

	$command = $_POST['command'];

    if($_POST['type'] == 'logout'){
        session_destroy();
    }
    else {
        $params = array("username" => $_POST['username'],
                        "password" => $_POST['password']
                        );
        $result = $post->curl($command, $params);
//        $status = $result['status'];
//        $code = $result['code'];
//        $statusMsg = $result['statusMsg'];
        $userData = $result['data']['userDetails'];
//        print_r($result);
//
//        $data = json_decode($data);
//        
        $pages = $result['data']['pages'];
        $hiddens = $result['data']['hidden'];
        $permissions = $result['data']['permissions'];
        
        $userID = $userData['userID'];
        $username = $userData['username'];
        $userEmail = $userData['userEmail'];
        $userRoleID = $userData['userRoleID'];
        $sessionID = $userData['sessionID'];
        $sessionTimeOut = $userData['sessionTimeOut'];
        $pagingCount = $userData['pagingCount'];
        $timeOutFlag = $userData['timeOutFlag'];
       
        $_SESSION["permission"] = $permissions;
//        $_SESSION["userData"] = $permissions;
        $_SESSION["userID"] = $userID;
        $_SESSION["username"] = $username;
        $_SESSION["userEmail"] = $userEmail;
        $_SESSION["userRoleID"] = $userRoleID;
        $_SESSION["sessionID"] = $sessionID;
        $_SESSION["pagingCount"] = $pagingCount;
        $_SESSION["sessionExpireTime"] = $timeOutFlag;
        
        // Set session for menu and submenu
        foreach($permissions as $array) {
            if($array['file_path'] != '')
                $_SESSION["access"][$array['file_path']] = $array['name'];
            $menuPath[$array['id']] = $array['file_path'];
        }

        // Set session for hidden page
        foreach($hiddens as $array) {
            $menuPath[$array['id']] = $array['file_path'];
            $_SESSION["access"][$array['file_path']] = $array['name'];
//            $_SESSION["parentPage"][$array['file_path']] = $menuPath[$array['parent_id']];
        }

        // Set session for hidden parent. To get to know which parent this hidden page belongs to
        foreach($pages as $array) {
            $_SESSION["parentPage"][$array['file_path']] = $menuPath[$array['parent_id']];
        }
        
        // Set session for page
        foreach($pages as $array) {
            $menuPath[$array['id']] = $array['file_path'];
            $_SESSION["access"][$array['file_path']] = $array['name'];
//            $_SESSION["parentPage"][$array['file_path']] = $menuPath[$array['parent_id']];
        }
        
        // Set session for page parent. To get to know which parent this page belongs to
        foreach($pages as $array) {
            $_SESSION["parentPage"][$array['file_path']] = $menuPath[$array['parent_id']];
        }
        
        $_SESSION['lastVisited'] = 'webServices.php';
        $_SESSION['menuPath'] = $menuPath;
        $myJson = json_encode($result);
        echo $myJson;
    }
?>
