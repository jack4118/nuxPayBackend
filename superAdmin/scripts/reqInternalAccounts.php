<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the System InternalAccounts related conditions.
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
            case "newInternalAccount":
                $params = array("username"              => $_POST['username'],
                                "name"                  => $_POST['name'],
                                "description"           => $_POST['description'],
                                // "password"              => $_POST['password'],
                                // "transaction_password"  => $_POST['transaction_password'],
                                // "type"                  => $_POST['type'],
                                // "description"           => $_POST['description'],
                                // "email"                 => $_POST['email'],
                                // "phone"                 => $_POST['phone'],
                                // "address"               => $_POST['address'],
                                // "country_id"            => $_POST['country_id'],
                                // "state_id"              => $_POST['state_id'],
                                // "county_id"             => $_POST['county_id'],
                                // "city_id"               => $_POST['city_id'],
                                // "sponsor_id"            => $_POST['sponsor_id'],
                                // "placement_id"          => $_POST['placement_id'],
                                // "disabled"              => $_POST['disabled']
                                );
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getInternalAccountsList":
                $inputData = $_POST['inputData'];
                
                $params = array("searchData" => $inputData,
                                "pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "deleteInternalAccount":
                $deleteData = $_POST['deleteData'];
                $params = array("id" => $deleteData);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getInternalAccountData":
                $params = array("id" => $_POST['internalAccountId']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "editInternalAccountData":
                $params = array("id"                    => $_POST['internalAccountId'],
                                "username"              => $_POST['username'],
                                "name"                  => $_POST['name'],
                                "description"           => $_POST['description'],
                                // "password"              => $_POST['password'],
                                // "transaction_password"  => $_POST['transaction_password'],
                                // "type"                  => $_POST['type'],
                                // "description"           => $_POST['description'],
                                // "email"                 => $_POST['email'],
                                // "phone"                 => $_POST['phone'],
                                // "address"               => $_POST['address'],
                                // "country_id"            => $_POST['country_id'],
                                // "state_id"              => $_POST['state_id'],
                                // "county_id"             => $_POST['county_id'],
                                // "city_id"               => $_POST['city_id'],
                                // "sponsor_id"            => $_POST['sponsor_id'],
                                // "placement_id"          => $_POST['placement_id'],
                                // "disabled"              => $_POST['disabled']
                                );
                $result = $post->curl($command, $params);

                echo $result;
                break;
        }
    }
?>
