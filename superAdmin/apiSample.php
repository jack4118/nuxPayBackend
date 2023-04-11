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
                                    <li class=""><a id="apiTab">Edit API</a></li>
                                    <li class=""><a id="apiParamTab">API Parameters</a></li>
                                    <li class="active"><a data-toggle="tab" href="#apiSampleTab" id="apiSampleTab">Api Sample</a></li>
                                </ul>
                                <div class="tab-content m-b-30">
                                    <div id="apiSampleTab" class="tab-pane fade in active">
                                        <form role="form" id="apiSample" data-parsley-validate novalidate>
                                            <!-- <h4 class="header-title m-t-0 m-b-30">API</h4> -->
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="">Status</label>
                                                        <input type="text" class="form-control" id="status" name="status" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="">Code</label>
                                                        <input type="number" class="form-control" id="code" name="code" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="">StatusMsg</label>
                                                        <input type="text" class="form-control" id="statusMsg" name="statusMsg">
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="">Data (JSON string) <i id="openPopover" class="fa fa-info-circle" aria-hidden="true" data-toggle="popover" title="Input Example" data-trigger="focus" tabindex="0" data-container="body" data-placement="auto right" data-html="true"></i></label>
                                                        <textarea rows="20" cols="50" class="form-control" id="data" name="data" required></textarea>
                                                    </div>
                                                </div>
                                                <div class="form-group col-md-12 m-b-20">
                                                    <button type="button" class="btn btn-primary waves-effect waves-light" id="saveApi">Save</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- End row -->
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
                    'command': 'getApiSampleData',
                    'apiId'     : apiId
                };
                fCallback = loadData;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }

            $('#saveApi').click(function() {
                var validate = $('#apiSample').parsley().validate();
                if(validate) {
                    var status    = $('#status').val();
                    var code      = $('#code').val();
                    var statusMsg = $('#statusMsg').val();
                    var data      = $('#data').val();
                    
                    try {
                        // Convert json string to json object
                        var jsonObj = JSON.parse(data);
                    } catch (e) {
                        showMessage('Invalid JSON string for Data input.', 'warning', 'API Sample', 'file', '');
                        return false;
                    }
                    
                    var formData = { 'apiId'     : apiId,
                                     'command'   : "editApiSampleData",
                                     'status'    : status,
                                     'code'      : code,
                                     'statusMsg' : statusMsg,
                                     'data'      : jsonObj
                                    };
                    
                    fCallback = sendStatus;
                    ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
                }
            });
            
            var dataContent = 'Enter in this format.</br></br>{"objName":[{"keyA":"valueA","keyB":"valueB"},{"keyC":"valueC","keyD":"valueD"}]}</br></br>The above will be</br></br>objName:</br>[0]=>[keyA => "valueA",</br>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbspkeyB => "valueB"]</br>[1]=>[keyC => "valueC",</br>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbspkeyD => "valueD"]';
            $('#openPopover').attr('data-content', dataContent);
            
            // Documentation on using bootstrap popover
            // https://v4-alpha.getbootstrap.com/components/popovers/
            // data-trigger="focus" and tabindex="0" is for dismissable popover on next click
            // Means the popover will close when click any other stuff on that page.
            $('#openPopover').on('click', function() {
               $(this).popover('toggle'); 
            });

            //Call the Edit API Tab.
            $('#apiTab').click(function() {
                window.location.href = "editApi.php?id="+apiId;
            });
            //Call the API Parameters Tab.
            $('#apiParamTab').click(function() {
                window.location.href = "apiParametersList.php?id="+apiId;
            });
        });

    function loadData(data) {
        if(data) {
            $.each(data, function(key, val) {
                if(key == 'data') {
                    val = JSON.stringify(val);
                }
                $('#'+key).val(val);
            });
        }
    }

    function sendStatus(data, message) {
        showMessage(message, 'success', 'API Sample', 'file', '');
    }
</script>
</html>