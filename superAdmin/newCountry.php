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
                         <a href="countries.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <h4 class="header-title m-t-0 m-b-30">Country</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <form role="form" id="newCountry" data-parsley-validate novalidate>
                                        <input type="hidden" name="command" value="newCountry">
                                            <div class="form-group">
                                                <label for="">Name*</label>
                                                <input id="name" name="name" type="text" class="form-control"   required>
                                            </div>

                                            <div class="form-group">
                                                <label for="">ISO Code2*</label>
                                                <input id="isoCode2" type="text" class="form-control" name="isoCode2"  required>
                                            </div>

                                            <div class="form-group">
                                                <label for="">ISO Code3*</label>
                                                <input id="isoCode3" type="text" class="form-control" name="isoCode3"  required>
                                            </div>

                                            <div class="form-group">
                                                <label for="">Country Code*</label>
                                                <input id="countryCode" type="text" class="form-control" name="countryCode"  required>
                                            </div>

                                            <div class="form-group">
                                                <label for="">Currency Code*</label>
                                                <input id="currencyCode" type="text" class="form-control" name="currencyCode"  required>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 m-b-20">
                            <button id="addNewCountry" type="submit" class="btn btn-primary waves-effect waves-light">Confirm</button>
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
<script>
    // Initialize the arguments for ajaxSend function
    var url = 'scripts/reqCountries.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;
        
    var fCallback = sendNew;
    $(document).ready(function() {
        $('#addNewCountry').click(function() {
            var validate = $('#newCountry').parsley().validate();
            if(validate) {
                var formData = $('#newCountry').serialize();
                
                var fCallback = sendNew;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }
        });
    });
    
    function sendNew(data, message) {
        showMessage('Country successfully created.', 'success', 'Add New Country', 'Country', 'countries.php');
    }
</script>
</html>