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
                            <h4 class="page-title">Edit Module</h4>
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
                    <div class="col-sm-4">
                         <a href="moduleSa.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <h4 class="header-title m-t-0 m-b-30">Module</h4>
                                <div class="row">
                                    <div class="col-md-12">
                                        <form role="form">
                                            <div class="form-group">
                                                <label for="">Module Name</label>
                                                <input type="text" class="form-control">
                                            </div>
                                            <div class="form-group">
                                                <p><label>Display Icon</label></p>
                                                <div class="radio radio-primary radio-inline">
                                                    <input type="radio" name="radio" id="radio3" value="option1">
                                                    <label for="checkbox1"><i class="zmdi zmdi-collection-text"></i></label>
                                                </div>
                                                <div class="radio radio-primary radio-inline">
                                                    <input type="radio" name="radio" id="radio3" value="option1">
                                                    <label for="checkbox1"><i class="zmdi zmdi-email"></i></label>
                                                </div>
                                                <div class="radio radio-primary radio-inline">
                                                    <input type="radio" name="radio" id="radio3" value="option1">
                                                    <label for="checkbox1"><i class="zmdi zmdi-accounts-alt"></i></label>
                                                </div>
                                                <div class="radio radio-primary radio-inline">
                                                    <input type="radio" name="radio" id="radio3" value="option1">
                                                    <label for="checkbox1"><i class="zmdi zmdi-format-align-justify"></i></label>
                                                </div>
                                                <div class="radio radio-primary radio-inline">
                                                    <input type="radio" name="radio" id="radio3" value="option1">
                                                    <label for="checkbox1"><i class="zmdi zmdi-settings"></i></label>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label for="">Status</label>
                                                <div class="m-b-20">
                                                    <div class="radio radio-info radio-inline">
                                                        <input type="radio" id="inlineRadio1" value="option1" name="radioInline" checked="">
                                                        <label for="inlineRadio1"> On </label>
                                                    </div>
                                                    <div class="radio radio-inline">
                                                        <input type="radio" id="inlineRadio2" value="option2" name="radioInline" checked="">
                                                        <label for="inlineRadio2"> Off </label>
                                                    </div>
                                                </div>
                                            </div>

                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="card-box p-b-0">
                                <h4 class="header-title m-t-0 m-b-30">Sub Module</h4>
                                <button type="submit" class="m-b-20 btn btn-primary waves-effect waves-light">Add Sub Module</button>

                                <div class="row">

                                    <div class="col-md-12 card-box kanban-box">
                                        <form role="form">
                                            <div class="col-sm-12 form-group">
                                                <label for="">Sub Module Name</label>
                                                <input type="text" class="form-control">
                                            </div>
                                            <div class="col-sm-12 form-group">
                                                <label for="">Status</label>
                                                <div class="">
                                                    <div class="radio radio-info radio-inline">
                                                        <input type="radio" id="inlineRadio1" value="option1" name="radioInline" checked="">
                                                        <label for="inlineRadio1"> On </label>
                                                    </div>
                                                    <div class="radio radio-inline">
                                                        <input type="radio" id="inlineRadio2" value="option2" name="radioInline" checked="">
                                                        <label for="inlineRadio2"> Off </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-sm-12 m-b-0">
                                                <button type="submit" class="btn btn-default waves-effect waves-light">Remove</button>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="col-md-12 card-box kanban-box">
                                        <form role="form">
                                            <div class="col-sm-12 form-group">
                                                <label for="">Sub Module Name</label>
                                                <input type="text" class="form-control">
                                            </div>
                                            <div class="col-sm-12 form-group">
                                                <label for="">Status</label>
                                                <div class="">
                                                    <div class="radio radio-info radio-inline">
                                                        <input type="radio" id="inlineRadio1" value="option1" name="radioInline" checked="">
                                                        <label for="inlineRadio1"> On </label>
                                                    </div>
                                                    <div class="radio radio-inline">
                                                        <input type="radio" id="inlineRadio2" value="option2" name="radioInline" checked="">
                                                        <label for="inlineRadio2"> Off </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-sm-12 m-b-0">
                                                <button type="submit" class="btn btn-default waves-effect waves-light">Remove</button>
                                            </div>
                                        </form>
                                    </div>

                               </div>
                            </div>
                        </div>
                        <div class="col-md-12 m-b-20">
                            <!-- <button type="submit" class="btn btn-default waves-effect waves-light">Cancel</button> -->
                            <button type="submit" class="btn btn-primary waves-effect waves-light">Save</button>
                        </div>
                    </div>
                    <!-- End row -->

                   <!--  <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <form>
                                    <div id="basicwizard" class=" pull-in">

                                        <div class="tab-content b-0 m-b-0">
                                            <div class="overflowX tab-pane m-t-10 fade active in">
                                                <table class="table table-bordered m-0">
                                                    <thead>
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>Client ID</th>
                                                            <th>Client Username</th>
                                                            <th>Command</th>
                                                            <th>Data In</th>
                                                            <th>Data Out</th>
                                                            <th>Source</th>
                                                            <th>Source Version</th>
                                                            <th>Type</th>
                                                            <th>Status</th>
                                                            <th>Create Time</th>
                                                            <th>Completed Time</th>
                                                            <th>Duration</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <th scope="row">1</th>
                                                            <td>CL112931</td>
                                                            <td>jack2821</td>
                                                            <td>update_password</td>
                                                            <td>request<br>clientID:112931</td>
                                                            <td>response<br>status:ok<br>messageCode:0<br>message:</td>
                                                            <td>Web</td>
                                                            <td>-</td>
                                                            <td>Mozilla/5.0 (Windows NT 10.0; Win64; x64)</td>
                                                            <td>OK</td>
                                                            <td>09:01:07</td>
                                                            <td>09:01:08</td>
                                                            <td>1sec</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div> -->
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