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
                         <a href="providers.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <h4 class="header-title m-t-0 m-b-30">Provider</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <form role="form" id="editProvider" data-parsley-validate novalidate>
                                            <input type="hidden" name="command" value="editProvider">
                                            <input type="hidden" id="providerId" name="providerId" value="">

                                            <div class="form-group">
                                                <label for="">Name</label>
                                                <input id="name" type="text" class="form-control" name="name"  required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="">Username</label>
                                                <input id="username" type="text" class="form-control" name="username"  required>
                                            </div>

                                            <div class="form-group">
                                                <label for="">Password</label>
                                                <input id="password" type="password" class="form-control" name="password"  required>
                                            </div>

                                            <div class="form-group">
                                                <label for="">Company</label>
                                                <input id="company" type="text" class="form-control" name="company"  required>
                                            </div>

                                            <div class="form-group">
                                                <label for="">Api Key</label>
                                                <input id="api_key" type="text" class="form-control" name="apiKey">
                                            </div>

                                            <div class="form-group">
                                                <label for="">Type</label>
                                                <input id="type" type="text" class="form-control" name="type"  required>
                                            </div> 

                                            <div class="form-group">
                                                <label for="">Priority</label>
                                                <input id="priority" type="text" class="form-control" name="priority">
                                            </div> 

                                            <div class="form-group">
                                                <label for="">Status</label>
                                                <div class="m-b-20" id="status">
                                                    <div class="radio radio-info radio-inline">
                                                        <input type="radio" id="disabled" value="0" name="disabled" checked="">
                                                        <label for="inlineRadio1"> Active </label>
                                                    </div>
                                                    <div class="radio radio-inline">
                                                        <input type="radio" id="disabled" value="1" name="disabled" checked="">
                                                        <label for="inlineRadio2"> Disabled </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label for="">Default Sender</label>
                                                <input id="default_sender" type="text" class="form-control" name="defaultSender">
                                            </div> 

                                            <div class="form-group">
                                                <label for="">URL 1</label>
                                                <input id="url1" type="text" class="form-control" name="url1">
                                            </div> 

                                            <div class="form-group">
                                                <label for="">URL 2</label>
                                                <input id="url2" type="text" class="form-control" name="url2">
                                            </div>

                                            <div class="form-group">
                                                <label for="">Remarks</label>
                                                <input id="remark" type="textarea"  cols="2" rows="3" class="form-control" name="remark">
                                            </div> 

                                            

                                            <div class="form-group">
                                                <label for="">Currency</label>
                                                <input id="currency" type="text" class="form-control" name="currency">
                                            </div>

                                            <div class="form-group">
                                                <label for="">Balance</label>
                                                <input id="balance" type="text" class="form-control" name="balance">
                                            </div>

                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 m-b-20">
                            <button id="editProviderButton" type="button" class="btn btn-primary waves-effect waves-light">Confirm</button>
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
    var url = 'scripts/reqProvider.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;

    $(document).ready(function() {
        var editId = window.location.search.substr(1).split("=");
        $("#providerId").val(editId[1]);
        editId = editId[1];
        if(editId != '') {
            var formData = {
                'command': 'getEditProviderData',
                'providerId' : editId
            };
            fCallback = loadEdit;
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }

        $('#editProviderButton').click(function() {
            var validate = $('#editProvider').parsley().validate();
            if(validate) {
                var formData = $('#editProvider').serialize();

                fCallback = sendEdit;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }
        }); 
    });

    function loadEdit(data, message) {
        $.each(data.providerData, function(key, val) {
            if(key == 'disabled' && val == 0) {
                $('#'+key).prop('checked', true);
            } else if(key == 'disabled' && val == 1) { 
                $('#'+key).prop('checked', false);
            }else {
                $('#'+key).val(val);
            }
        });
    }

    function sendEdit(data, message) {
        showMessage('Provider successfully updated.', 'success', 'Edit Provider', 'Provider', 'providers.php');
    }
</script>
</html>