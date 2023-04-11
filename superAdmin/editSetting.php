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
                         <a href="settingsList.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <h4 class="header-title m-t-0 m-b-30">Setting</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <form role="form" id="editSettings" data-parsley-validate novalidate>
                                            <div class="form-group">
                                                <label for="">Name</label>
                                                <input id="name" type="text" class="form-control"  required>
                                            </div>
                                            <div class="form-group">
                                                <label for="">Value</label>
                                                <input id="value" type="text" class="form-control"  required>
                                            </div>
                                            <div class="form-group">
                                                <label for="">Type</label>
                                                <input id="type" type="text" class="form-control">
                                            </div>
                                            <div class="form-group">
                                                <label for="">Reference</label>
                                                <input id="reference" type="text" class="form-control">
                                            </div>
                                            <div class="form-group">
                                                <label for="">Module</label>
                                                <input id="module" type="text" class="form-control">
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 m-b-20">
                            <!-- <button type="submit" class="btn btn-default waves-effect waves-light">Cancel</button> -->
                            <button id="editSetting" type="submit" class="btn btn-primary waves-effect waves-light">Confirm</button>
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
    var url = 'scripts/reqSettings.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;

    $(document).ready(function() {
        var settingId = window.location.search.substr(1).split("=");
        settingId = settingId[1];
        if(settingId != '') {
            var formData = {
                'command': 'getSettingData',
                'settingId' : settingId
            };
            fCallback = loadEdit;
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }

        $('#editSetting').click(function() {
            var validate = $('#editSettings').parsley().validate();
            if(validate) {
                var name = $('#name').val();
                var type = $('#type').val();
                var value = $('#value').val();
                var reference = $('#reference').val();
                var module = $('#module').val();

                var formData = { "settingId" : settingId,
                                  command    : "editSettingData", 
                                 "name"      : name, 
                                 "type"      : type, 
                                 "value"     : value, 
                                 "reference" : reference,
                                 "module"    : module
                            };
                fCallback = sendEdit;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }
        });
    });

    function loadEdit(data, message) {
        $.each(data.settingData, function(key, val) {
            $('#'+key).val(val);
        });
    }

    function sendEdit(data, message) {
        showMessage('Setting successfully updated.', 'success', 'Edit Setting', 'Setting', 'settingsList.php');
    }
</script>
</html>