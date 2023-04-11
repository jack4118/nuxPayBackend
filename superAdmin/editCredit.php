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
                             <a href="credit.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                        </div><!-- end col -->
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <div class="row">
                                    <ul class="nav nav-tabs">
                                        <li class="active"><a data-toggle="tab" href="#details">Details</a></li>
                                        <li><a id="editCreditSettingTab">Settings</a></li>
                                    </ul>
                                    <div class="tab-content m-b-30">
                                        <div id="details" class="tab-pane fade in active">
                                            <div class="row">
                                                <div id="creditDetails" class="tab-content b-0 m-b-0 p-t-0">
                                                    <div id="" class="tab-content b-0 m-b-0 p-t-0">
                                                        <form role="form" id="editCredits" data-parsley-validate novalidate>
                                                            <h4 class="header-title m-t-0 m-b-30">Credit Details</h4>
                                                            <div id="editCreditMsg" class="alert" style="display: none;"></div>
                                                            <div class="form-group" hidden>
                                                                <label for="">ID</label>
                                                                <input id="id" type="text" class="form-control" disabled/>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="">Credit Name*</label>
                                                                <input id="creditName" type="text" class="form-control"  required>
                                                            </div>
                                                            <!-- <div class="form-group">
                                                                <label for="">Description</label>
                                                                <input id="description" type="text" class="form-control">
                                                            </div> -->
                                                            <div class="form-group">
                                                                <label for="">Translation Code</label>
                                                                <input id="translationCode" type="text" class="form-control">
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="">Priority</label>
                                                                <input id="priority" type="text" class="form-control">
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="">Description</label>
                                                                <textarea rows="4" cols="50" class="form-control" id="description" name="description"></textarea>
                                                            </div>
                                                        </form>
                                                        <div class="col-md-12 m-b-20">
                                                            <button id="editCreditBtn" type="submit" class="btn btn-primary waves-effect waves-light">Save</button>
                                                        </div>
                                                    </div>
                                                </div>
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
    var url = 'scripts/reqCredit.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;
        
    var fCallback = loadEdit;
    $(document).ready(function() {
        var editId = window.location.search.substr(1).split("=");
        editId = editId[1];
        if(editId != '') {
            var formData = {
                'command': 'getCreditDetails',
                'editId' : editId
            };
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }
        
        $('#editCreditSettingTab').click(function() {
            window.location.href = "editCreditSetting.php?id="+editId;
        });
        
        $('#editCreditBtn').click(function() {
            var validate = $('#editCredits').parsley().validate();
            if(validate) {
                var creditID = $('input#id').val();
                var creditName = $('input#creditName').val();
                var description = $('input#description').val();
                var translationCode = $('input#translationCode').val();
                var priority = $('input#priority').val();
                
                var formData = {
                    command : 'editCredit',
                    creditID : creditID,
                    creditName : creditName,
                    description : description,
                    translationCode : translationCode,
                    priority : priority
                };
                fCallback = sendEdit;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }
        }); 
    });
    
    function loadEdit(data, message) {
        $.each(data.creditDetails, function(key, val) {
            $('#'+key).val(val);
        });
    }
    
    function sendEdit(data, message) {
        showMessage('Credit data successfully updated.', 'success', 'Edit credit', 'credit-card', 'credit.php');
    }
</script>
</body>
</html>