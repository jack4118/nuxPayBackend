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
                             <a href="client.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                        </div><!-- end col -->
                    </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <div class="row">
                                    <ul class="nav nav-tabs">
                                        <li class="active"><a data-toggle="tab" href="#details">Details</a></li>
                                        <li><a id="clientSettingTab">Settings</a></li>
                                        <li><a href="sponsorTree.php?id=<?php echo $_GET['id']; ?>" id="sponsorTreeTab?">Sponsor Tree</a></li>
                                    </ul>
                                    <div class="tab-content m-b-30">
                                        <div id="details" class="tab-pane fade in active">
                                            <div class="row">
                                                <form role="form">
                                                    <div id="clientDetails" class="tab-content b-0 m-b-0 p-t-0">
                                                        <div id="clientDetailsMsg" class="alert" style="display:none;"></div>
                                                        <div id="" class="tab-content b-0 m-b-0 p-t-0">
                                                            <h4 class="header-title m-t-0 m-b-30">Client Particulars</h4>
                                                            <div class="form-group col-lg-6">
                                                                <label>Client ID</label>
                                                                <input name="ID" type="text" class="form-control" disabled/>
                                                            </div>
                                                            <div class="form-group col-lg-6">
                                                                <label>Username</label>
                                                                <input name="username" type="text" class="form-control" disabled/>
                                                            </div>
                                                            <div class="form-group col-lg-6">
                                                                <label>Name</label>
                                                                <input name="name" type="text" class="form-control" disabled/>
                                                            </div>
                                                            <div class="form-group col-lg-6">
                                                                <label>Type</label>
                                                                <input name="type" type="text" class="form-control" disabled/>
                                                            </div>
                                                            <div class="form-group col-lg-6">
                                                                <label>Email</label>
                                                                <input name="email" type="text" class="form-control" disabled/>
                                                            </div>
                                                            <div class="form-group col-lg-6">
                                                                <label>Phone</label>
                                                                <input name="phone" type="text" class="form-control" disabled/>
                                                            </div>
                                                        </div>
                                                        <div id="" class="tab-content b-0 m-b-0 p-t-0">
                                                            <h4 class="header-title m-t-30 m-b-30">Address Particulars</h4>
                                                            <div class="form-group  col-lg-12">
                                                                <label for="">Address</label>
                                                                <input name="address" type="text" class="form-control" disabled/>
                                                            </div>
                                                            <div class="form-group col-lg-6">
                                                                <label for="">State</label>
                                                                <input name="state" type="text" class="form-control" disabled/>
                                                            </div>
                                                            <div class="form-group col-lg-6">
                                                                <label for="">City</label>
                                                                <input name="city" type="text" class="form-control" disabled/>
                                                            </div>
                                                            <div class="form-group col-lg-6">
                                                                <label for="">County</label>
                                                                <input name="county" type="text" class="form-control" disabled/>
                                                            </div>
                                                            <div class="form-group col-lg-6">
                                                                <label for="">Country</label>
                                                                <input name="country" type="text" class="form-control" disabled/>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> <!-- row -->

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
    var url = 'scripts/reqClient.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;
        
    var fCallback = loadClientDetails;
    $(document).ready(function() {
        var editId = window.location.search.substr(1).split("=");
        editId = editId[1];
        if(editId != '') {
            var formData = {
                'command': 'getClientDetails',
                'editId' : editId
            };
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }
        
        $('#clientSettingTab').click(function() {
            window.location.href = "clientSetting.php?id="+editId;
        });
    });
    
    function loadClientDetails(data, message) {
        var clientData = data.clientDetail;
        if(typeof(clientData) != 'undefined') {
            $('#clientDetailsMsg').removeClass('alert-success').html('').hide();
            $.each(clientData, function(key, val) {
                if(val == 'null')
                    val = '';
                $('#clientDetails').find('input[name="' + key + '"]').val(val);
            });
        }
        else
            $('#clientDetailsMsg').addClass('alert-success').html('<span>'+message+'</span>').show();
    }
</script>
</body>
</html>