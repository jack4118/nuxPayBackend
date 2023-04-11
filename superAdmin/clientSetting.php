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
                                        <li><a id="clientDetailsTab">Details</a></li>
                                        <li class="active"><a data-toggle="tab" href="#settings">Settings</a></li>
                                        <li><a href="sponsorTree.php?id=<?php echo $_GET['id']; ?>">Sponsor Tree</a></li>
                                    </ul>
                                    <div class="tab-content m-b-30">
                                        <div id="settings" class="tab-pane fade in active">
                                            <div class="row">
                                                <form role="form">
                                                    <div id="clientSettingList" class="tab-content b-0 m-b-0 p-t-0">
                                                        <div id="clientSettingMsg" class="alert" style="display:none;"></div>
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
    var editId = window.location.search.substr(1).split("=");
    editId = editId[1];
    // Initialize the arguments for ajaxSend function
    var url = 'scripts/reqClient.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;
        
    var fCallback = loadClientSetting;
    $(document).ready(function() {
        if(editId != '') {
            var formData = {
                'command': 'getClientSettings',
                'editId' : editId
            };
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }
        
        $('#clientDetailsTab').click(function() {
            window.location.href = "clientDetails.php?id="+editId;
        });
    });
    
    function loadClientSetting(data, message) {
        var clientData = data.clientSetting;
        if(typeof(clientData) != 'undefined') {
            $('#clientSettingMsg').removeClass('alert-success').html('').hide();
            $('#clientSettingList').append('<div class="form-group" hidden><label class="control-label">Client ID</label><input id="id" type="text" class="form-control" value="' + editId + '"></div>');
            $.each(clientData['name'], function(key, val) {
                var value = clientData['value'][key];
                var type = (clientData['type'][key] != "")? ' - ' + clientData['type'][key] : "";
                $('#clientSettingList').append('<div class="form-group"><label class="control-label">' + val + type + '</label><input type="text" class="form-control" value="' + value + '" disabled></div>');
            });
        }
        else
            $('#clientSettingMsg').addClass('alert-success').html('<span>'+message+'</span>').show();
    }
</script>
</body>
</html>
