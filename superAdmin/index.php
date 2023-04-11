<?php 

    session_start();

    if(isset($_SESSION["userID"]) && isset($_SESSION["sessionID"])) {
        if(time() - $_SESSION['sessionTimeOut'] >= $_SESSION['sessionExpireTime']) {
            header("Location: pageLogin.php");
        }
        else {
            header("Location: webServices.php");
        }
    }
    else {
        header("Location: pageLogin.php");
    }

?>