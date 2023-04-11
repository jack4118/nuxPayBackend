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
                         <a href="apiList.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <ul class="nav nav-tabs">
                                    <li class="active"><a data-toggle="tab" href="#apiTab">Edit API</a></li>
                                    <li class=""><a id="apiParamTab">API Parameters</a></li>
                                    <li class=""><a id="apiSampleTab">Api Sample</a></li>
                                </ul>
                                <div class="tab-content m-b-30">
                                    <div id="apiTab" class="tab-pane fade in active">
                                        <form role="form" id="editApi" data-parsley-validate novalidate>
                                            <!-- <div class="col-lg-12">
                                                <div class="card-box p-b-0"> -->
                                                    <h4 class="header-title m-t-0 m-b-30">API</h4>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <!-- <form role="form"> -->
                                                                <div class="form-group">
                                                                    <label for="">Command*</label>
                                                                    <input type="text" class="form-control" id="command" name="commandName" required>
                                                                </div>
                                                                <!-- <div class="form-group">
                                                                    <label for="">Description</label>
                                                                    <input type="text" class="form-control" id="description" name="description"  required>
                                                                </div> -->
                                                                <div class="form-group">
                                                                    <label for="">Duration*</label>
                                                                    <input type="number" class="form-control" id="duration" name="duration"  required>
                                                                </div>
                                                                <div class="form-group">
                                                                    <label for="">No of queries*</label>
                                                                    <input type="number" class="form-control" id="no_of_queries" name="queries"  required>
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="">Description</label>
                                                                    <textarea rows="4" cols="50" class="form-control" id="description" name="description"></textarea>
                                                                </div>
                                                                <div class="form-group">
                                                                    <label for="">Status</label>
                                                                    <div class="m-b-20" id="status">
                                                                        <div class="radio radio-info radio-inline">
                                                                            <input type="radio" id="disabled" value="0" name="apiStatus" checked="">
                                                                            <label for="inlineRadio1"> Active </label>
                                                                        </div>
                                                                        <div class="radio radio-inline">
                                                                            <input type="radio" id="disabled" value="1" name="apiStatus" checked="">
                                                                            <label for="inlineRadio2"> Disabled </label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                        </div>
                                                    
                                                    <!-- </div>
                                                </div> -->
                                                <div class="form-group col-md-12 m-b-20">
                                                    <button type="button" class="btn btn-primary waves-effect waves-light" id="saveApi">Save</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
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
    <script type="text/javascript">
        // Initialize the arguments for ajaxSend function
        var url = 'scripts/reqApi.php';
        var method = 'POST';
        var debug = 0;
        var bypassBlocking = 0;
        var bypassLoading = 0;

        $(document).ready(function(){            
            var apiId = $.urlParam('id');
            if(apiId != '') {
                var formData = {
                    'command': 'getEditApiData',
                    'apiId' : apiId
                };
                fCallback = loadEdit;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }

            $('#saveApi').click(function() {
                var validate = $('#editApi').parsley().validate();
                if(validate) {
                    var command_name = $('#command_name').val();
                    var description = $('#description').val();
                    var duration = $('#duration').val();
                    var queries = $('#no_of_queries').val();
                    var status = $("input[name='apiStatus']:checked").val();

                    var formData = { 'id' : apiId,
                                     'command' : "editApi",
                                     'commandName' : command_name,
                                     'apiStatus' : status,
                                     'description' : description,
                                      'duration' : duration,
                                       'queries' : queries
                                    };

                    fCallback = sendEdit;
                    ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
                }
            });

            //Call the API Parameters Tab.
            $('#apiParamTab').click(function() {
                window.location.href = "apiParametersList.php?id="+apiId;
            });
            //Call the API Sample Tab.
            $('#apiSampleTab').click(function() {
                window.location.href = "apiSample.php?id="+apiId;
            });
        });

    //For capturing the parameter from the URL.
    $.urlParam = function(name){
        var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
        if (results==null){
           return null;
        }
        else{
           return decodeURI(results[1]) || 0;
        }
    }

    function loadEdit(data, message) {
        $.each(data, function(key, val) {
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
        showMessage('Api successfully updated.', 'success', 'Edit Api', 'API', 'apiList.php');
    }
</script>
</html>