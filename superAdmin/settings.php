<!DOCTYPE html>
<html>
<?php include("head.php"); ?>
<body class="fixed-left">

    <!-- Begin page -->
    <div id="wrapper">

        <!-- Top Bar Start -->
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
                            <h4 class="page-title">Settings</h4>
                        </li>
                    </ul>

                    <!-- Right(Notification and Searchbox -->
                    <ul class="nav navbar-nav navbar-right">
                        <li>
                            <!-- Notification -->
                            <div class="notification-box">
                                <ul class="list-inline m-b-0">
                                    <li>
                                        <a href="pageLogin.php" class="right-bar-toggle">
                                            <i class="zmdi zmdi-power"></i>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <!-- End Notification bar -->
                        </li>
                        <!-- <li class="hidden-xs">
                            <form role="search" class="app-search">
                                <input type="text" placeholder="Search..."
                                       class="form-control">
                                <a href=""><i class="fa fa-search"></i></a>
                            </form>
                        </li> -->
                    </ul>
                </div><!-- end container -->
            </div><!-- end navbar -->
        </div>
        <!-- Top Bar End -->


        <!-- ========== Left Sidebar Start ========== -->
        <?php include("header.php"); ?>
        <!-- Left Sidebar End -->



        <!-- ============================================================== -->
        <!-- Start right Content here -->
        <!-- ============================================================== -->
        <div class="content-page">
            <!-- Start content -->
            <div class="content">
                <div class="container">



                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">

                                <div class="row">
                                    <div class="row col-md-12">
                                        <form role="form">
                                            <!-- <div class="col-sm-4 form-group">
                                                <label class="control-label">Date</label>
                                                <select class="form-control">
                                                    <option>2017-05-31</option>
                                                    <option>2017-06-01</option>
                                                    <option>2017-06-02</option>
                                                    <option>2017-06-03</option>
                                                    <option>2017-06-04</option>
                                                </select>
                                            </div> -->
                                            <div class="col-sm-4 form-group">
                                                <label class="control-label">Rows per page</label>
                                                <select class="form-control">
                                                    <option>20</option>
                                                    <option>50</option>
                                                    <option>100</option>
                                                    <option>200</option>
                                                </select>
                                            </div>
                                            <div class="col-md-12 m-b-20">
                                                <button type="submit" class="btn btn-default waves-effect waves-light">Cancel</button>
                                                <button type="submit" class="btn btn-primary waves-effect waves-light">Save</button>
                                            </div>
                                        </form>
                                    </div>
                                </div><!-- end row -->
                            </div>
                        </div>

                    </div>
                    <!-- End row -->

                </div> <!-- container -->

            </div> <!-- content -->

            <?php include("footer.php"); ?>

        </div>
        <!-- End content-page -->


        <!-- ============================================================== -->
        <!-- End Right content here -->
        <!-- ============================================================== -->

    </div>
    <!-- END wrapper -->

    <script>
        var resizefunc = [];
    </script>

    <!-- jQuery  -->
    <?php include("shareJs.php"); ?>

</body>
</html>