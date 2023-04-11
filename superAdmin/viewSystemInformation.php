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
<style type="text/css">
    .textAlign {
        text-align: left !important;
    }
</style>
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
                         <a href="systemInformation.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="card-box">
                                <h4 class="header-title m-t-0 m-b-30">System Information</h4>
                                <h5>Server Info.</h5>
                                <div class="row">
                                    <div class="col-lg-6">
                                        <form class="form-horizontal" role="form">
                                            <div class="form-group">
                                                <label class="col-md-4 control-label">Server Name :</label>
                                                <div class="col-md-8">
                                                    <span class="col-md-8 control-label textAlign" id="server_name"></span>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="col-md-4 control-label">IP :</label>
                                                <div class="col-md-8">
                                                    <span class="col-md-8 control-label textAlign" id="server_ip"></span>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="col-md-4 control-label">Type :</label>
                                                <div class="col-md-8">
                                                    <span class="col-md-8 control-label textAlign" id="type"></span>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="col-md-4 control-label">OS :</label>
                                                <div class="col-md-8">
                                                    <span class="col-md-8 control-label textAlign" id="release"></span>
                                                </div>
                                            </div>
                                        </form>
                                    </div><!-- end col -->
                                    <div class="col-lg-6">
                                        <form class="form-horizontal" role="form">
                                            <div class="form-group">
                                                <label class="col-md-4 control-label">Total CPU :</label>
                                                <div class="col-md-8">
                                                    <span class="col-md-8 control-label textAlign" id="total_cpu"></span>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="col-md-4 control-label">Disk Size :</label>
                                                <div class="col-md-8">
                                                    <span class="col-md-8 control-label textAlign" id="disk_size"></span>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="col-md-4 control-label">Disk Available :</label>
                                                <div class="col-md-8">
                                                    <span class="col-md-8 control-label textAlign" id="disk_available"></span>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="col-md-4 control-label">Total Memory :</label>
                                                <div class="col-md-8">
                                                    <span class="col-md-8 control-label textAlign" id="total_memory"></span>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="col-md-4 control-label">Total Swap :</label>
                                                <div class="col-md-8">
                                                    <span class="col-md-8 control-label textAlign" id="total_swap"></span>
                                                </div>
                                            </div>

                                            <!-- <div class="form-group">
                                                <label class="col-md-4 control-label">Disk Usage :</label>
                                                <div class="col-md-8">
                                                    <span class="col-md-8 control-label textAlign" id="disk_usage"></span>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="col-md-4 control-label">CPU Load :</label>
                                                <div class="col-md-8">
                                                    <span class="col-md-8 control-label textAlign" id="cpu_load_avg"></span>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="col-md-4 control-label">Memory Usage :</label>
                                                <div class="col-md-8">
                                                    <span class="col-md-8 control-label textAlign" id="memory_usage"></span>
                                                </div>
                                            </div> -->

                                        </form>
                                    </div><!-- end col -->
                                </div><!-- end row -->
                            </div>
                        </div><!-- end col -->
                    </div>
                </div> <!-- container -->
            </div> <!-- content -->
            <?php include("footer.php"); ?>
        </div>
        <!-- End content-page -->
        <!-- ============================================================== -->
        <!-- End Right content here -->
        <!-- ============================================================== -->
    </div>

<!-- jQuery  -->
    <?php include("shareJs.php"); ?>
    <script type="text/javascript">
        // Initialize the arguments for ajaxSend function
        var url = 'scripts/reqSystem.php';
        var method = 'POST';
        var debug = 1;
        var bypassBlocking = 0;
        var bypassLoading = 0;
        var resizefunc = [];

        $(document).ready(function() {
            var systemId = $.urlParam('id');
            if(systemId != '') {
                var formData = {
                    'command': 'getSystemData',
                    'systemId' : systemId
                };
                fCallback = getSystemData;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }
        });

        function getSystemData(data, message) {
            console.log(data);
            $.each(data, function(key, val) {
                $('#'+key).append(val);
            });
        }

        //For capturing the parameter from the URL.
        $.urlParam = function(name){
            var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
            if (results==null){
               return null;
            }
            else{
               return decodeURI(results[1]) || 0;
            }
        }
    </script>
</html>