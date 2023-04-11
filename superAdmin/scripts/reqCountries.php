<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the System Countries related conditions.
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
            case "newCountry":
                $params = array("name"          => $_POST['name'],
                                "isoCode2"     => $_POST['isoCode2'],
                                "isoCode3"     => $_POST['isoCode3'],
                                "countryCode"  => $_POST['countryCode'],
                                "currencyCode" => $_POST['currencyCode']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getCountriesList":
                $inputData = $_POST['inputData'];
                
                $params = array("searchData" => $inputData,
                                "pageNumber" => $_POST['pageNumber'],
                                "pagination" => $_POST['pagination']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "deleteCountry":
                $deleteData = $_POST['deleteData'];
                $params = array("id" => $deleteData);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getCountryData":
                $params = array("id" => $_POST['countryId']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "editCountryData":
                $params = array("id"            => $_POST['countryId'],
                                "name"          => $_POST['name'],
                                "isoCode2"     => $_POST['isoCode2'],
                                "isoCode3"     => $_POST['isoCode3'],
                                "countryCode"  => $_POST['countryCode'],
                                "currencyCode" => $_POST['currencyCode']);
                $result = $post->curl($command, $params);

                echo $result;
                break;
        }
    }
?>
