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
                         <a href="languageList.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>
                 <div class="row">
                    <form role="form" id="addLanguage" data-parsley-validate novalidate>
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <!-- <h4 class="header-title m-t-0 m-b-30">Language Code</h4> -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="">Language*</label>
                                            <input id="language" type="text" class="form-control" id="language" name="language"  required>
                                        </div>
                                        <!-- <div class="form-group">
                                            <label for="">Language Code*</label>
                                            <input id="language_code" type="text" class="form-control" name="language_code"  required>
                                        </div> -->
                                        <div class="form-group">
                                            <label for="">ISO Code*</label>
                                            <input id="iso_code" type="text" class="form-control" name="iso_code"  required>
                                        </div>
                                        <div class="form-group">
                                            <label for="">Status</label>
                                            <div class="m-b-20" id="status">
                                                <div class="radio radio-info radio-inline">
                                                    <input type="radio" id="inlineRadio1" value="0" name="status" checked="">
                                                    <label for="inlineRadio1"> Active </label>
                                                </div>
                                                <div class="radio radio-inline">
                                                    <input type="radio" id="inlineRadio2" value="1" name="status" checked="">
                                                    <label for="inlineRadio2"> Disabled </label>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- <div class="form-group">
                                            <label class="control-label">Status</label>
                                            <select id="status" name="status" class="form-control">
                                                <option value="">--Please Select--</option>
                                                <option value="0">Active</option> 
                                                <option value="1">Disabled</option>
                                            </select>
                                        </div> -->
                                    </div>
                                </div>
                            </div>

                        <div class="col-md-12 m-b-20">
                            <button id="addNewLanguage" type="button" class="btn btn-primary waves-effect waves-light">Confirm</button>
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
</body>
<script>
    // Initialize the arguments for ajaxSend function
    var url = 'scripts/reqLanguage.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;
        
    var fCallback = sendNew;
    $(document).ready(function() {
        $('#addNewLanguage').click(function() {
            var validate = $('#addLanguage').parsley().validate();
            if(validate) {
                var language = $('#language').val();
                //var languageCode = $('#language_code').val();
                var isoCode = $('#iso_code').val();
                //var status = $('#status').find('option:selected').val();
                var status = $("input[name='status']:checked").val();
                
                var formData = {
                    command : "newLanguage",
                    language : language,
                    //languageCode : languageCode,
                    isoCode : isoCode,
                    status : status
                };
                
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }
        });
    });
    
    function sendNew(data, message) {
        showMessage('New language successfully created.', 'success', 'Add New Language', 'language', 'languageList.php');
    }
</script>
</html>