<?php 
    session_start();
    // This is to check if the user didn't login
    if(!isset ($_SESSION['lastVisited'])) {
        $_SESSION['lastVisited'] = "pageLogin.php";
    }
?>
<!DOCTYPE html>
<html>
    <?php include("head.php"); ?>
    <div class="text-center">
        <h2>Access denied</h2>
        <h4>Click the button to go back to where you first started.</h4>
        <button id="goBack" type="submit" class="btn btn-primary waves-effect waves-light">Back</button>
    </div>
    <script>
        var resizefunc = [];
    </script>
    <!-- jQuery  -->
    <?php include("shareJs.php"); ?>
    <?php include("footer.php"); ?>
    <style type="text/css">
    .footer {
        left: 0px;
        text-align: center !important;
    }
    </style>
    <script type="text/javascript">
        $('#goBack').click(function() {
            var url = " <?php  echo $_SESSION['lastVisited']; ?>";
            if (url == 'pageLogin.php') {
                $.ajax({
                    type: 'POST',
                    url: 'scripts/reqLogin.php',
                    data: {type : "logout"},
                    success	: function(result) {
                        window.location.href = 'pageLogin.php';
                    },
                    error	: function(result) {
                        alert("Error!");
                    }
                });
            }
            else
                window.location.href = url;
        });
    </script>
</html>