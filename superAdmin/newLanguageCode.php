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
                         <a href="languageCode.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>
                 <div class="row">
                    <form role="form" id="addLanguageCode" data-parsley-validate novalidate>
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <!-- <h4 class="header-title m-t-0 m-b-30">Language Code</h4> -->
                                <input type="hidden" class="form-control" value="newLanguageCode" name="command"> 
                                <div class="row">
                                    <div id="form-fields-test-id" class="col-md-6">
                                        <div class="form-group">
                                            <label for="">Content Code*</label>
                                            <input id="content_code" type="text" class="form-control"  name="content_code"  required>
                                        </div>
                                        <div class="form-group">
                                            <label for="">Module*</label>
                                            <input id="module" type="text" class="form-control"  name="module"  required>
                                        </div>
                                        <div class="form-group">
                                            <label for="">Site*</label>
                                            <input id="site" type="text" class="form-control"  name="site"  required>
                                        </div>
                                        <div class="form-group">
                                            <label for="">Category*</label>
                                            <input id="category" type="text" class="form-control" name="category"  required>
                                        </div>                                       
                                    </div>
                                </div>
                            </div>

                        <div class="col-md-12 m-b-20">
                            <button id="addNewLanguageCode" type="button" class="btn btn-primary waves-effect waves-light">Confirm</button>
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
    var url = 'scripts/reqLanguageCode.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;

    var fCallback = loadFormDropdown;
    $(document).ready(function() {
        var formData = {
            command : "getLanguageRows"
        };
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);

        $('#addNewLanguageCode').click(function() {
            var validate = $('#addLanguageCode').parsley().validate();
            if(validate) {
                var contentCode = $('#content_code').val();
                var module      = $('#module').val();
                var site        = $('#site').val();
                var category    = $('#category').val();

                // languageData    = [];

                // //childrenData = dataArray.concat(languageData);
                // languageData.push({ name: "command", value: "newLanguageCode" });
                // languageData.push({ name: "contentCode", value: contentCode });
                // languageData.push({ name: "module", value: module });
                // languageData.push({ name: "site", value: site });
                // languageData.push({ name: "category", value: category });

                //var formData = languageData;
                var formData = $('#addLanguageCode').serialize();

                var fCallback = sendNew;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }
        });
    });

    function loadFormDropdown(data, message) {
        var langName = data.languageData;

        $.each(langName.Language, function(key, val) {
            str = '<div class="form-group"><label for="">Content( ' + val + ' )</label><input id="'+ val +'" type="text" class="form-control" name="'+val+'"></div>';
            $( "#form-fields-test-id" ).append(str);
        });

        // $.each(langName, function(key, val) { alert(val);
        //     str = '<div class="form-group"><label for="">Content( ' + val.toLowerCase() + ' )</label><input id="'+ val.toLowerCase() +'" type="text" class="form-control" name="'+val.toLowerCase()+'"></div>';
        //     $( "#form-fields-test-id" ).append(str);
        // });
    }

    function sendNew(data, message) {
        showMessage('New Lanaguage Code successfully created.', 'success', 'New Language Code', 'MesssageCode', 'languageCode.php');
    }
</script>
</html>