<?php
    session_start();

    // Get current page name
    $thisPage = basename($_SERVER['PHP_SELF']);

    // Check the session for this page
    if(!isset ($_SESSION['access'][$thisPage]))
        echo '<script>window.location.href="accessDenied.php";</script>';
    else
        $_SESSION['lastVisited'] = $thisPage;
?>
<!DOCTYPE html>
<html>
<?php include("head.php"); ?>
    <!-- Begin page -->
    <div id="wrapper">
        <!-- Top Bar Start -->
        <?php include("topbar.php"); ?>
        <!-- Top Bar End -->
        
        <!-- ========== Left Sidebar Start ========== -->
        <?php include("sidebar.php"); ?>
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
                             <a href="apiParamList.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                        </div><!-- end col -->
                    </div>

                    <div class="row">
                        <form role="form" id="addApi">
                            <div class="col-lg-12">
                                <div class="card-box p-b-0">
                                    <h4 class="header-title m-t-0 m-b-30">API Parameter</h4>
                                    <div class="row">
                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label class="control-label">Api Name</label>
                                                <select class="form-control"  required id="api_id" name="apiName">
                                                    <option>--Please Select--</option>>
                                                </select>
                                                <ul class="parsley-errors-list filled" id="apiNameError" style="display: none;"><li class="parsley-required">Please select Api.</li></ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                 <label for="">Parameters Name</label>
                                                <input type="text" class="form-control" name="apiParamName" id="apiParamName">
                                                <ul class="parsley-errors-list filled" id="apiParamNameError" style="display: none;"><li class="parsley-required">Please enter api param name.</li></ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-8 card-box kanban-box">
                                            <div class="col-sm-12">
                                                <div class="radio radio-info radio-inline">
                                                    <input type="radio" id="inlineRadio1" value="alphanumeric" name="apiParamVal" checked="">
                                                    <label for="inlineRadio1"> Alphanumberic </label>
                                                </div>
                                                <div class="radio radio-info radio-inline">
                                                    <input type="radio" id="inlineRadio1" value="numeric" name="apiParamVal" checked="">
                                                    <label for="inlineRadio2"> Numeric </label>
                                                </div>
                                                <div class="radio radio-info radio-inline">
                                                    <input type="radio" id="inlineRadio1" value="blob" name="apiParamVal" checked="">
                                                    <label for="inlineRadio3"> Blob </label>
                                                </div>
                                            </div>
                                        </div>
                                        <ul class="parsley-errors-list filled" id="apiParamValError" style="display: none;"><li class="parsley-required">Please enter api param name.</li></ul>
                                   </div>
                                </div>
                            </div>
                            <div class="col-md-12 m-b-20">
                                <button type="button" class="btn btn-primary waves-effect waves-light" id="saveApiParam">Confirm</button>
                            </div>
                        </form>
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
    <script src="js/api.js"></script>
    <script type="text/javascript">
        // Initialize the arguments for ajaxSend function
        var url = 'scripts/reqApi.php';
        var method = 'POST';
        var debug = 0;
        var bypassBlocking = 0;
        var bypassLoading = 0;

        $(document).ready(function() {
            getApiName();
            $('#saveApiParam').click(function() {
                var apiParamName = $('#apiParamName').val();
                var apiParamVal  = $("input[name='apiParamVal']:checked").val();
                var apiName      = jQuery("#api_id option:selected").val();

                var formData = {'command'       : "newApiParam",
                                'apiParamName'  : apiParamName, 
                                'apiParamVal'   : apiParamVal, 
                                "apiId"         : apiName };

                var fCallback = sendNew;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            });
        });

        function sendNew(data, message) {
            showMessage('Api Parameter successfully created.', 'success', 'Add New Api Parameter', 'ApiParam', 'apiParamList.php');
        }
    </script>
</html>