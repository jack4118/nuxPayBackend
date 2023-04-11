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
                         <a href="languageList.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>
                 <div class="row">
                    <form role="form" id="editLanguageCode" data-parsley-validate novalidate>
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <!-- <h4 class="header-title m-t-0 m-b-30">Edit Language</h4> -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="">Language*</label>
                                            <input id="languageName" type="text" class="form-control" name="languageName"  required>
                                        </div>
                                        <!-- <div class="form-group">
                                            <label for="">Language Code*</label>
                                            <input id="languageCode" type="text" class="form-control"  name="languageCode"  required>
                                        </div> -->
                                        <div class="form-group">
                                            <label for="">ISO Code*</label>
                                            <input type="text" class="form-control" id="isoCode" name="isoCode"  required>
                                        </div>
                                        <div class="form-group">
                                            <label for="">Status</label>
                                            <div id="status" class="m-b-20">
                                                <div class="radio radio-info radio-inline">
                                                    <input type="radio" id="inlineRadio1" value="0" name="status"/>
                                                    <label for="inlineRadio1"> Active </label>
                                                </div>
                                                <div class="radio radio-inline">
                                                    <input type="radio" id="inlineRadio2" value="1" name="status"/>
                                                    <label for="inlineRadio2"> Disabled </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <div class="col-md-12 m-b-20">
                            <button id="editLanguage" type="button" class="btn btn-primary waves-effect waves-light">Confirm</button>
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
        
    var fCallback = loadEdit;
    $(document).ready(function() {
        var editId = window.location.search.substr(1).split("=");
        editId = editId[1];
        if(editId != '') {
            var formData = {
                'command': 'getLanguageData',
                'editId' : editId
            }; 
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }

        $('#editLanguage').click(function() {
            var validate = $('#editLanguageCode').parsley().validate();
            if(validate) {
                var languageName = $('#languageName').val();
                //var languageCode = $('#languageCode').val();
                var isoCode = $('#isoCode').val();
                var status = $("input[name='status']:checked").val();
                
                var formData = {
                    command         : 'editLanguageData',
                    'id'            : editId,
                    'languageName'  : languageName,
                    //'languageCode'  : languageCode,
                    'isoCode'       : isoCode,
                    'status'        : status
                };
                fCallback = sendEdit;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }
        });
    });
    
    function loadEdit(data, message) {
        $.each(data.languageData, function(key, val) {
            if(key == 'status') {
                $('#'+key).find('input[value="'+val+'"]').attr('checked', 'checked');
            }
            else {
                $('#'+key).val(val);
            }
        });
    }
    
    function sendEdit(data, message) {
        showMessage('Language successfully updated.', 'success', 'Edit Language', 'Language', 'languageList.php');
    }
</script>

</html>