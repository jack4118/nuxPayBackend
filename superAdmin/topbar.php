<?php 
    session_start();

    // Get current page name
    $thisPage = basename($_SERVER['PHP_SELF']);

    // Check the session for this page
    if(!isset ($_SESSION['access'][$thisPage]))
        echo '<script>window.location.href="accessDenied.php";</script>';
    
    // Recursive function. Will keep on search for the parent of your page
    // When it stops, it will echo the breadcrumbs out
    function buildBreadcrumbs($link, $liString) {
        $queryString = '';
        if (isset($_SESSION['queryString'][$link]))
            $queryString = $_SESSION['queryString'][$link];
        $name = $_SESSION['access'][$link];
        $breadcrumbs = '<li class="breadcrumb-item"><a href="'.$link.$queryString.'">'.$name.'</a></li>'.$liString;
        if (isset($_SESSION['parentPage'][$link])) {
            buildBreadcrumbs($_SESSION['parentPage'][$link], $breadcrumbs);
        }
        else
            echo $breadcrumbs;
    }
?>

<div class="topbar">

    <!-- LOGO -->
    <?php include("logo.php"); ?>

    <!-- Button mobile view to collapse sidebar menu -->
    <div class="navbar navbar-default" role="navigation">
        <div class="container">

            <!-- Page title -->
             <ul class="nav navbar-nav navbar-left">
                <li>
                    <button class="button-menu-mobile open-left">
                        <i class="zmdi zmdi-menu"></i>
                    </button>
                </li>
                <li>
                    <h4 class="page-title"><?php echo $_SESSION['access'][$thisPage]; ?></h4>
                </li>
            </ul>

            <!-- Right(Notification and Searchbox -->
            <ul class="nav navbar-nav navbar-right">
                <li>
                    <!-- Notification -->
                    <div class="notification-box">
                        <ul class="list-inline m-b-0">
                            <li>
                                <a id="logoutBtn" onclick="beforeLogout();" class="right-bar-toggle">
                                    <i class="zmdi zmdi-power"></i>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <!-- End Notification bar -->
                </li>
                <!-- <li class="hidden-xs">
                    <form role="search" class="app-search">
                        <input type="text" placeholder="Search..." class="form-control">
                        <a href=""><i class="fa fa-search"></i></a>
                    </form>
                </li> -->
            </ul>
        </div><!-- end container -->
    </div><!-- end navbar -->
    <div id="breadcrumbs">
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="webServices.php">Home</a></li>
                <?php
                    unset($_SESSION['queryString']);
                    if($_SERVER['QUERY_STRING'] != '')
                        $_SESSION['queryString'][$thisPage] = '?'.$_SERVER['QUERY_STRING'];
                    if($thisPage != 'webServices.php')
                        buildBreadcrumbs($thisPage, ''); 
                ?>  
        </ul>
    </div>
</div>