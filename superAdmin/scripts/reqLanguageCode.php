<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the Permission related conditions.
     * Date  21/07/2017.
    **/

    // error_reporting(E_ALL);
    // ini_set('display_errors', 1);
	session_start();

    include ($_SERVER["DOCUMENT_ROOT"] . "/include/config.php");
	include($_SERVER["DOCUMENT_ROOT"]."/include/class.post.php");
    
    $post = new post();


	$command = $_POST['command'];

    $username   = @$_SESSION['username'];
    $userId     = @$_SESSION['userId'];
    $sessionID  = @$_SESSION['sessionID'];

    if(@$_POST['type'] == 'logout'){
        session_destroy();
    }
    else{
        switch($command) {
            case "newLanguageCode":
                //json_encode($_POST),
                $contentCode = $_POST['content_code'];
                $module       = $_POST['module']; 
                $site         = $_POST['site'];  
                $category     = $_POST['category'];  

                unset($_POST['command']);
                unset($_POST['contentCode']);
                unset($_POST['site']);
                unset($_POST['category']);
                unset($_POST['module']);

                $languageData = $_POST;


                $params = array(
                                "contentCode"           => $contentCode,
                                "site"                   => $site,
                                "category"               => $category,
                                "module"                 => $module,
                                "languageData"           => $languageData
                                );
                //print_r($params);die;
                $result = $post->curl($command, $params);
                //print_r($result);

                echo $result;
                break;

            case "getLanguageCodeList":
                $inputData = $_POST['inputData'];
                $inputData = json_encode($inputData);
                $params = array("searchData" => $inputData,
                                "pageNumber" => $_POST['pageNumber']);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "getLanguageRows":
                
                $params = array("searchData" => $inputData);

                $result     = $post->curl($command, $params);

                echo $result;
                break;    

            case "deleteLanguageCode":
                $deleteData = $_POST['deleteData'];
                $params = array("id" => $deleteData);
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "editLanguageCodeData":
                $params = array(
                                "id"            => $_POST['languageCodeId'],
                                "contentCode"  => $_POST['contentCode'],
                                "module"        => $_POST['module'],
                                "language"      => $_POST['language'],
                                "site"          => $_POST['site'],
                                "category"      => $_POST['category'],
                                "content"       => $_POST['content']
                                
                                );
                $result = $post->curl($command, $params);

                echo $result;
                break;

            case "uploadFile" :
                foreach($_FILES as $file){
                  $fileName = $file['name'];
                  $tmpName = $file['tmp_name'];
                  $fileType = $file['type'];
                  $fileSize = $file['size'];
                }
                $fileData = file_get_contents($tmpName);
                $baseData = base64_encode ($fileData);
                //print_r($baseData);die;
                $params = array(
                                "data"      => $baseData,
                                "type"      => $fileType,
                                "fileName"  => $fileName);
                //file_put_contents($_SERVER["DOCUMENT_ROOT"]."/include/abc.xlsx", base64_decode($baseData));

                $result = $post->curl($command, $params);

                echo $result;
                
                break;

             case "exportLanguageCodes":
                $params = array("command" => 'exportLanguageCodes');
                $result = $post->curl($command, $params);
                $result = json_decode($result, true);
                $_SESSION["language"] = $result;

                echo "sessionLanguage";
                break;

            case "getLanguageCodeData":
                $params = array("id" => $_POST['languageCodeId']);
                $result = $post->curl($command, $params);

                echo $result;
                break;
        }
    }
?>
