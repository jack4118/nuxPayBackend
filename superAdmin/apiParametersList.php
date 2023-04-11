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
                                <div class="row">
                                    <ul class="nav nav-tabs">
                                        <li><a id="editApi">Edit API</a></li>
                                        <li class="active"><a data-toggle="tab" href="#apiParameters">API Parameters</a></li>
                                        <li class=""><a id="apiSample">API Sample</a></li>
                                    </ul>
                                    <div class="tab-content m-b-30">
                                        <div id="apiParameters" class="tab-pane fade in active">
                                            <div class="row">
                                                <form role="form">
                                                    <div id="apiParamsListDiv" class="tab-content b-0 m-b-0 p-t-0">
                                                        <div id="alertMsg" class="alert" style="display:none;"></div>
                                                        <div class="text-center">
                                                            <ul class="pagination pagination-md" id="apiPager"></ul>
                                                        </div>
                                                    </div>
                                                </form>
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
    </div>
    <!-- END wrapper -->
    <script>
        var resizefunc = [];
    </script>

    <!-- jQuery  -->
    <?php include("shareJs.php"); ?>
    <script src="js/api.js"></script>

    <script>
        editId = $.urlParam('id');
        showCanvas();
        // Initialize all the id in this page
        var divId = 'apiParamsListDiv';
        var tableId = 'apiParamsListTable';
        var pagerId = 'apiPager';
        var btnArray = Array('edit');
        var thArray = Array('ID',
                            'Name',
                            'Type'
                           );

        // Initialize the arguments for ajaxSend function
        var url = 'scripts/reqApi.php';
        var method = 'POST';
        var debug = 0;
        var bypassBlocking = 0;
        var bypassLoading = 0;

        var fCallback = loadDefaultListing;
        var formData = {command: 'getApiParameterData', apiId : editId};
        $(document).ready(function() {
            editId = $.urlParam('id');
            // First load of this page
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);

            $('#editApi').click(function() {
                window.location.href = "editApi.php?id="+editId;
            });
            $('#apiSample').click(function() {
                window.location.href = "apiSample.php?id="+editId;
            });
        });

        function loadDefaultListing(data, message) {
            var tableNo;
            buildTable(data.apiParam, tableId, divId, thArray, btnArray, message, tableNo);
            paginateTable(tableId, pagerId, true, true, <?php echo $_SESSION["pagingCount"];?>);
        }

        function tableBtnClick(btnId) {
            var btnName = $('#'+btnId).attr('id').replace(/\d+/g, '');
            var tableRow = $('#'+btnId).parent('td').parent('tr');
            var tableId = $('#'+btnId).closest('table');

            if (btnName == 'edit') {
                var editId = tableRow.attr('data-th');
                var editUrl = 'editApiParam.php?id='+editId;
                window.location = editUrl;
            } else if (btnName == 'delete') {
                var canvasBtnArray = Array('Ok');
                var message = 'Are you sure you want to delete this API Parameter?';
                showMessage(message, '', 'Delete API Parameter', 'trash', '', canvasBtnArray);
                $('#canvasOkBtn').click(function() {
                    var tableColVal = tableRow.attr('data-th');
                    var formData = {
                        'command': 'deleteApiParam',
                        'deleteData' : tableColVal
                    };
                    fCallback = loadDelete;
                    ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
                });
            }
        }
    </script>
</html>