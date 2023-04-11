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
                            <h4 class="page-title">Super Admin</h4>
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
                                                    <label class="control-label">Module Name</label>
                                                    <select class="form-control">
                                                        <option>All</option>
                                                        <option>Member</option>
                                                        <option>Registration</option>
                                                    </select>
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label">Status</label>
                                                    <select class="form-control">
                                                        <option>All</option>
                                                        <option>On</option>
                                                        <option>Off</option>
                                                    </select>
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

                                <a href="newModuleSa.php" type="button" class="btn btn-primary waves-effect w-md waves-light m-b-20">New Module</a>

                                <form>
                                <!-- <div class="row">
                                        <div class="col-sm-9 m-t-n30">
                                            <div class="float-right dataTables_paginate paging_simpl_numbers" id="datatable_paginate">
                                                <ul class="pagination">
                                                    <li class="paginate_button previous disabled" aria-controls="datatable" tabindex="0" id="datatable_previous"><a href="#">Previous</a></li>
                                                    <li class="paginate_button active" aria-controls="datatable" tabindex="0"><a href="#">1</a></li>
                                                    <li class="paginate_button " aria-controls="datatable" tabindex="0"><a href="#">2</a></li>
                                                    <li class="paginate_button next" aria-controls="datatable" tabindex="0" id="datatable_next"><a href="#">Next</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div> -->

                                    <div id="basicwizard" class="pull-in">
                                        <div class="tab-content b-0 m-b-0 p-t-0">
                                            <div class="overflowX tab-pane fade active in">
                                                <table class="table table-striped table-bordered dataTable no-footer m-0">
                                                    <thead>
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>Module Name</th>
                                                            <th>Display Icon</th>
                                                            <th>Status</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <th scope="row">2</th>
                                                            <td>Users</td>
                                                            <td><i class="zmdi zmdi-accounts-alt"></i></td>
                                                            <td>Off</td>
                                                            <td><a href="editmoduleSa.php" class="m-t-5 m-r-10 btn btn-icon waves-effect waves-light btn-primary"> <i class="fa fa-edit"></i> </a><a class="m-t-5 btn btn-icon waves-effect waves-light btn-primary"> <i class="zmdi zmdi-delete"></i></a></td>
                                                        </tr>
                                                        <tr>
                                                            <th scope="row">1</th>
                                                            <td>Web Services</td>
                                                            <td><i class="zmdi zmdi-collection-text"></i></td>
                                                            <td>On</td>
                                                            <td><a href="editmoduleSa.php" class="m-t-5 m-r-10 btn btn-icon waves-effect waves-light btn-primary"> <i class="fa fa-edit"></i> </a><a class="m-t-5 btn btn-icon waves-effect waves-light btn-primary"> <i class="zmdi zmdi-delete"></i></a></td>
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