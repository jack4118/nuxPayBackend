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
                        <div class="col-lg-12">
                            <div class="panel-group">
                                <div class="panel panel-default bx-shadow-none">
                                    <div class="panel-heading">
                                        <h4 class="panel-title">Import Translations.</h4>
                                    </div>
                                    <div id="collapseOne" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingOne" aria-expanded="true" style="">
                                    <div class="panel-body">
                                        <form role="form" id="importLanguages" enctype="multipart/form-data">
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <input id="file" type="file" name="fileName" required="required" accept="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel">
                                                            <input type="hidden" class="form-control" value="uploadFile" name="command">
                                                        </div>
                                                        <div class="form-group">
                                                            <button id="uploadFile" class="btn btn-primary waves-effect waves-light" type="button">Import</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>    

                <!-- <div class="row">
                    <div class="col-lg-12">
                        <div class="panel-group">
                            <div class="panel panel-default bx-shadow-none">
                                <div class="panel-heading" role="tab" id="headingOne">
                                    <h4 class="panel-title">Language Translation</h4>
                                </div>
                                <div class="panel-body">
                                    <div id="basicwizard" class="pull-in">
                                        <div class="tab-content b-0 m-b-0 p-t-0">
                                            <div id="languageCodeMsg" class="text-center alert" style="display: none;"></div>
                                            <div id="languageCodeListDiv" class="table-responsive"></div>
                                            <div class="text-center">
                                                <ul class="pagination pagination-md" id="pagerLanguageCodeList"></ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->
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

        $(document).ready(function() {
            $('#uploadFile').click(function() {
                //getting form into Jquery Wrapper Instance to enable JQuery Functions on form                    
                var form = $("#importLanguages");

                //Serializing all For Input Values (not files!) in an Array Collection so that we can iterate this collection later.
                var params = form.serializeArray();

                //Getting Files Collection
                var files = $("#file")[0].files;
                //Declaring new Form Data Instance  
                var formData = new FormData();

                //Looping through uploaded files collection in case there is a Multi File Upload. This also works for single i.e simply remove MULTIPLE attribute from file control in HTML.  
                for (var i = 0; i < files.length; i++) {
                    formData.append(files[i].name, files[i]);
                }
                //Now Looping the parameters for all form input fields and assigning them as Name Value pairs. 
                $(params).each(function (index, element) {
                    formData.append(element.name, element.value);
                });

                var fCallback = sendFrom;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 1);
            });
        });

        function sendFrom(data, message) {
            var btn = $(this);
            //Firing event if File Upload is completed!  
//            alert("Upload Completed");
            btn.prop("disabled", false);
            btn.val("Submit");
            $("#file").val("");
            showMessage(message, 'success', 'File Upload', 'upload', '');
        }

        // $('#uploadFile').on('click', function() {
        //     var file_data = $('#file').prop('files')[0];
        //     alert(file_data);
        //     var form_data = new FormData();                  
        //     form_data.append('file', file_data); 
        //     form_data.append('command', 'uploadFile');   
        //     $.ajax({
        //         type: 'POST',
        //         url: 'scripts/reqLanguageCode.php',
        //         data: form_data,
        //         dataType: 'text',
        //         encode: true,
        //         contentType: false,
        //         processData: false,
        //         success: function(php_script_response){
        //             alert(php_script_response); // display response from the PHP script, if any
        //         }
        //     });
        // });
    </script>
</html>