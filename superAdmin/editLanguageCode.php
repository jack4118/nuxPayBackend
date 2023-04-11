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
<body class="fixed-left">
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
                         <a href="languageCode.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>
                 <div class="row">
                    <form role="form" id="editLanguageCodes" data-parsley-validate novalidate>
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <!-- <h4 class="header-title m-t-0 m-b-30">Language Code</h4> -->
                                <!-- <input type="hidden" class="form-control" value="newLanguageCode" name="command">  -->
                                <div class="row">
                                    <div id="form-fields-test-id" class="col-md-12">
                                        
                                        <div class="form-group">
                                            <label for="">Content Code*</label>
                                            <input id="code" type="text" class="form-control"  name="content_code" readonly  required>
                                        </div>
                                        <div class="form-group">
                                            <label for="">Module*</label>
                                            <input id="module" type="text" class="form-control"  name="module"  required>
                                        </div>
                                        <div class="form-group">
                                            <label for="">Site*</label>
                                            <input id="site" type="text" class="form-control"  name="site" readonly  required>
                                        </div>
                                        <div class="form-group">
                                            <label for="">Category*</label>
                                            <input id="type" type="text" class="form-control" name="category" readonly  required>
                                        </div>

                                        <div class="form-group">
                                            <label for="">Language*</label>
                                            <input id="language" type="text" class="form-control" name="language"  required>
                                        </div>

                                        <div class="form-group">
                                            <label for="">Content*</label>
                                            <input id="content" type="text" class="form-control" name="content"  required>
                                        </div>                                        
                                    </div>
                                </div>
                            </div>

                        <div class="col-md-12 m-b-20">
                            <button id="editLanguageCode" type="button" class="btn btn-primary waves-effect waves-light">Confirm</button>
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
    <div id="savedModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                    <h4 class="modal-title" id="myModalLabel">New Language Code</h4>
                </div>
                <div class="modal-body">
                    <p>Congratulations!... Language saved successfully!!.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Ok</button>
                </div>
            </div>
        </div>
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
    var url = 'scripts/reqLanguageCode.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;

    $(document).ready(function() {
        var languageCodeId = window.location.search.substr(1).split("=");
        languageCodeId = languageCodeId[1];

        if(languageCodeId != '') {
            var formData = {
                'command': 'getLanguageCodeData',
                'languageCodeId' : languageCodeId
            };
            fCallback = loadEdit;
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }

        $('#editLanguageCode').click(function() {
            var validate = $('#editLanguageCodes').parsley().validate();
            if(validate) {
                var contentCode = $('#code').val();
                var module      = $('#module').val();
                var site        = $('#site').val();
                var category    = $('#type').val();
                var language    = $('#language').val();
                var content     = $('#content').val();

                var formData    = {
                    "languageCodeId" : languageCodeId, 
                    "command"        : "editLanguageCodeData", 
                    "contentCode"    : contentCode, 
                    "module"         : module,
                    "site"           : site, 
                    "category"       : category, 
                    "language"       : language, 
                    "content"        : content 
                };

                fCallback = sendEdit;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }
        });
    });

    function loadEdit(data, message) {
        $.each(data, function(key, val) {
            if(key == 'status') {
                $('#'+key).find('input[value="'+val+'"]').attr('checked', 'checked');
            } else {
                $('#'+key).val(val);
            }
        });
    }

    function sendEdit(data, message) {
        showMessage('Language Code successfully updated.', 'success', 'Edit Language Code', 'LanguageCode', 'languageCode.php');
    }
</script>
</html>