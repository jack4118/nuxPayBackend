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
                         <a href="messageCode.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <h4 class="header-title m-t-0 m-b-30">Message</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <form role="form" data-parsley-validate novalidate id="editMessageCodes">
                                            <div class="form-group">
                                                <label for="">Code*</label>
                                                <input type="number" name="code" id="code" class="form-control"   required minlength="3">
                                            </div>
                                            <div class="form-group">
                                                <label for="">Title*</label>
                                                <input type="text" class="form-control" id="title" name="title"  required>
                                            </div>
                                            <div class="form-group">
                                                <label for="">Content*</label>
                                                <textarea rows="4" cols="50" class="form-control" id="content" required></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label for="">Description*</label>
                                                <textarea rows="4" cols="50" class="form-control" id="description" name="description" required></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label for="">Module*</label>
                                                <input type="text" class="form-control" id="module" required>
                                            </div>
                                            <!-- <div class="form-group m-b-20">
                                                <label for="">Description</label>
                                                <input type="text" name="description" id="description" class="form-control" placeholder="Example: User failed to login">
                                            </div> -->
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 m-b-20">
                            <!-- <button type="submit" class="btn btn-default waves-effect waves-light">Cancel</button> -->
                            <button type="submit" id="editMessageCode" class="btn btn-primary waves-effect waves-light">Confirm</button>
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
    var url = 'scripts/reqMessageCodes.php';
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
                'command': 'getEditMessageCodeData',
                'messageCodeId' : editId
            }; 
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }

        $('#editMessageCode').click(function() {
            var validate = $('#editMessageCodes').parsley().validate();
            if(validate) {
                var code        = $('#code').val();
                var title       = $('#title').val();
                var content     = $('#content').val();
                var description = $('#description').val();
                var module      = $('#module').val();
                
                var formData = {
                    command         : 'editMessageCode',
                    'id'            : editId,
                    'code'          : code,
                    'title'         : title,
                    'content'       : content,
                    'description'   : description,
                    'module'        : module
                };
                fCallback = sendEdit;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }
        });
    });
    function loadEdit(data, message) {
        $.each(data.messageCodeData, function(key, val) {
            $.each(val, function(innerKey, innerVal) {
                $('#'+innerKey).val(innerVal);
            });
        });
    }
    function sendEdit(data, message) {
        showMessage('Successfully updated the record.', 'success', 'Edit message Code', 'check', 'messageCode.php');
    }
</script>
</body>
</html>