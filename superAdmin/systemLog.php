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
                            <h4 class="page-title">System Log</h4>
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
                        <div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
                            <div class="panel panel-default bx-shadow-none">
                                <div class="panel-heading" role="tab" id="headingOne">
                                    <h4 class="panel-title">
                                        <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseOne" aria-expanded="false" aria-controls="collapseOne" class="collapse">
                                            Search
                                        </a>
                                    </h4>
                                </div>
                                
                                <div id="collapseOne" class="panel-collapse" role="tabpanel" aria-labelledby="headingOne">
                                    <div class="panel-body">
                                        <form role="form">
                                            <div class="col-sm-4 form-group">
                                                <label for="">Email</label>
                                                <input type="text" class="form-control">
                                            </div>
                                            <div class="col-sm-12">
                                                <button type="submit" class="btn btn-primary waves-effect waves-light">Search</button>
                                                <button type="submit" class="btn btn-default waves-effect waves-light">Reset</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">

                                <div class="row">
                                    <div class="row col-md-12">
                                        <form role="form">
                                            <div class="col-sm-4 form-group">
                                                <label class="control-label">Date</label>
                                                <select class="form-control">
                                                    <option>2017-05-31</option>
                                                    <option>2017-06-01</option>
                                                    <option>2017-06-02</option>
                                                    <option>2017-06-03</option>
                                                    <option>2017-06-04</option>
                                                </select>
                                            </div>
                                <!--             <div class="col-sm-4 form-group">
                                                <label class="control-label">Pages</label>
                                                <select class="form-control">
                                                    <option>1</option>
                                                    <option>2</option>
                                                    <option>3</option>
                                                    <option>4</option>
                                                    <option>5</option>
                                                </select>
                                            </div> -->
                                        </form>
                                    </div>
                                </div><!-- end row -->

                                <form>
                                    <div id="basicwizard" class=" pull-in">
                                        <!-- <ul class="nav nav-tabs navtab-wizard nav-justified bg-muted">
                                            <li class="active"><a href="#tab1" data-toggle="tab" aria-expanded="false">Browse</a></li>
                                            <li class=""><a href="#tab2" data-toggle="tab" aria-expanded="false">Search</a></li>
                                        </ul> -->

                                        <div class="tab-content b-0 m-b-0 p-t-0">
                                            <div class="overflowX tab-pane fade active in">
                                                <table class="table table-striped table-bordered dataTable no-footer m-0">
                                                    <thead>
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>Email</th>
                                                            <th>Performed</th>
                                                            <th>Performed Time</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <th scope="row">5</th>
                                                            <td>jacky88@gmail.com</td>
                                                            <td>Delete Module - Web Services</td>
                                                            <td>09:01:08PM</td>
                                                        </tr>
                                                        <tr>
                                                            <th scope="row">4</th>
                                                            <td>jacky88@gmail.com</td>
                                                            <td>Login</td>
                                                            <td>08:01:08PM</td>
                                                        </tr>
                                                        <tr>
                                                            <th scope="row">3</th>
                                                            <td>jacky88@gmail.com</td>
                                                            <td>Add module - Setting</td>
                                                            <td>07:01:08PM</td>
                                                        </tr>
                                                        <tr>
                                                            <th scope="row">2</th>
                                                            <td>jacky88@gmail.com</td>
                                                            <td>Logout</td>
                                                            <td>06:01:08PM</td>
                                                        </tr>
                                                        <tr>
                                                            <th scope="row">1</th>
                                                            <td>jacky88@gmail.com</td>
                                                            <td>Test API</td>
                                                            <td>05:10:08PM</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="dataTables_info" id="datatable-buttons_info" role="status" aria-live="polite">Showing 1 to 5 of 57 entries</div>

                                    <div class="dataTables_paginate paging_simple_numbers" id="datatable-buttons_paginate"><ul class="pagination"><li class="paginate_button previous disabled" aria-controls="datatable-buttons" tabindex="0" id="datatable-buttons_previous"><a href="#">Previous</a></li><li class="paginate_button active" aria-controls="datatable-buttons" tabindex="0"><a href="#">1</a></li><li class="paginate_button " aria-controls="datatable-buttons" tabindex="0"><a href="#">2</a></li><li class="paginate_button " aria-controls="datatable-buttons" tabindex="0"><a href="#">3</a></li><li class="paginate_button " aria-controls="datatable-buttons" tabindex="0"><a href="#">4</a></li><li class="paginate_button " aria-controls="datatable-buttons" tabindex="0"><a href="#">5</a></li><li class="paginate_button " aria-controls="datatable-buttons" tabindex="0"><a href="#">6</a></li><li class="paginate_button next" aria-controls="datatable-buttons" tabindex="0" id="datatable-buttons_next"><a href="#">Next</a></li></ul></div>

                                        </div>
                                    </div>
                                </form>
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