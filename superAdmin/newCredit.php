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
                            <h4 class="header-title m-t-0 m-b-30">Credit Details</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <form role="form" id="newCredit" data-parsley-validate novalidate>
                                        <div id="basicwizard" class=" pull-in">
                                            <div class="tab-content b-0 m-b-0 p-t-0">
                                                <div id="addNewCreditMsg" class="alert" style="display: none;"></div>
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
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12 m-b-20">
                            <!-- <button type="submit" class="btn btn-default waves-effect waves-light">Cancel</button> -->
                        <button id="addNewCredit" type="submit" class="btn btn-primary waves-effect waves-light">Add</button>
                    </div>
                </div>
                <!-- end row -->

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
        
    var fCallback = sendNew;
    $(document).ready(function() {
        $('#addNewCredit').click(function() {
            var validate = $('#newCredit').parsley().validate();
            if(validate) {
                var creditName = $('#creditName').val();
                var description = $('#description').val();
                var translationCode = $('#translationCode').val();
                var priority = $('#priority').val();
                
                var formData = {
                    command : "addCredit",
                    creditName : creditName,
                    description : description,
                    translationCode : translationCode,
                    priority : priority
                };
                
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }
        });
    });
    
    function sendNew(data, message) {
        showMessage('New credit successfully created.', 'success', 'Add new credit', 'search', 'credit.php');
    }
</script>
</body>
</html>