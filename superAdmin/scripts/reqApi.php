<?php
    /**
     * @author ttwoweb.
     * This file is contains the Webservices for Api Listing.
     *
    **/
	session_start();

    include($_SERVER["DOCUMENT_ROOT"]."/include/config.php");
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
            case "newApi":
                $params = array("commandName" => $_POST['commandName'],
                               "description"  => $_POST['description'],
                               "duration"     => $_POST['duration'],
                               "queries"      => $_POST['queries'],
                               "apiStatus"    => $_POST['apiStatus']);

                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "editApi":
                $params = array("id"           => $_POST['id'],
                                "commandName"  => $_POST['commandName'],
                                "description"  => $_POST['description'],
                                "duration"     => $_POST['duration'],
                                "queries"      => $_POST['queries'],
                                "apiStatus"    => $_POST['apiStatus']);

                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "editApiSampleData":
                $params = array("apiID"     => $_POST['apiId'],
                                "status"    => $_POST['status'],
                                "code"      => $_POST['code'],
                                "statusMsg" => $_POST['statusMsg'],
                                "data"      => $_POST['data']);

                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "apiList":
                $params = array("searchData" => $_POST['searchData'],
                                "pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "deleteApi":
                $deleteData = $_POST['deleteData'];
                $params = array("id" => $deleteData);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getEditApiData":
                $params = array("id"  => $_POST['apiId']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getApiSampleData":
                $params = array("apiID"  => $_POST['apiId']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "newApiParam":
                $params = array("apiParamName" => $_POST['apiParamName'],
                                "apiParamVal"  => $_POST['apiParamVal'],
                                "apiId"        => $_POST['apiId']);

                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getApiParamData":
                $inputData = $_POST['inputData'];
                
                $params    = array("searchData" => $inputData,
                                   "pageNumber" => $_POST['pageNumber']);
                $result    = $post->curl($command, $params);

                echo $result;
                break;

            case "getApiParameterData":
                $params = array("apiId" => $_POST['apiId']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getApiName":
                $params = "";
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getEditParamData":
                $params = array("id"  => $_POST['apiParamId']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "editParam":
                $params = array("id"           => $_POST['apiParamId'],
                                "commandName"  => $_POST['command'],
                                "apiId"        => $_POST['apiId'],
                                "apiParamName" => $_POST['apiParamName'],
                                "apiParamVal"  => $_POST['apiParamVal']);

                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "deleteApiParam":
                $deleteData = $_POST['deleteData'];
                $params = array("id" => $deleteData);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getApiSearchData":
                $params = array("searchData" => $inputData,
                                "pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);
                
                echo $result;
                break;

            case "searchParamHistory":
                $params = array("searchData" => $inputData);
                $result = $post->curl($command, $params);

                echo $result;
                break;
                
            case "getAPIParams":
                $params = array("apiID" => $_POST['apiID']);
                $result = $post->curl($command, $params);
                
                echo $result;
                break;
                
            case "testAPI":
                
                foreach ($_POST as $key => $value)
                {
                    if (in_array($key, array('command', 'site'))) {
                        // Not using this command
                        continue;
                    }
                    
                    $params[$key] = $value;
                }
                
                $result = $post->curl($command, $params, $_POST['site']);
                
                echo $result;
                break;
        }

    }
?>
