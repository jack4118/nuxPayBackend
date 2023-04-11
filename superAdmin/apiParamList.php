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
                        <div class="col-md-12">
                            <div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
                                <div class="panel panel-default bx-shadow-none">
                                    <div class="panel-heading" role="tab" id="headingOne">
                                        <h4 class="panel-title">
                                            <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseOne" aria-expanded="false" aria-controls="collapseOne" class="collapse">
                                                Search
                                            </a>
                                        </h4>
                                    </div>
                                    <div id="collapseOne" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingOne" aria-expanded="true">
                                        <div class="panel-body">
                                        <div id="searchMsg" class="text-center alert" style="display: none;"></div>
                                            <form role="form" id="searchParamHistory">
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="params_name">Parameter Name</label>
                                                    <input type="text" class="form-control" name="commandName" id="commandName">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" data-th="api_id">Api Name</label>
                                                    <select class="form-control"  required id="apiName" name="apiName">
                                                        <option value="">--Please Select--</option>
                                                    </select>
                                                </div>
                                                <div class="col-sm-12">
                                                    <button type="button" class="btn btn-primary waves-effect waves-light" id="apiParamSearch">Search</button>
                                                    <button type="button" class="btn btn-default waves-effect waves-light" id="apiParamReset">Reset</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <a href="newApiParam.php" type="button" class="btn btn-primary waves-effect w-md waves-light m-b-20">New Api Parameter</a>
                                <form>
                                    <div id="basicwizard" class="pull-in">
                                        <div class="tab-content b-0 m-b-0 p-t-0">
                                            <div id="apiMsg" class="text-center alert" style="display: none;"></div>
                                            <div id="apiParamsListDiv" class="table-responsive"></div>
                                            <span id="paginateText"></span>
                                            <div class="text-center">
                                                <ul class="pagination pagination-md" id="apiPager"></ul>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- End row -->
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
        getApiName();
        showCanvas();
        // Initialize all the id in this page
        var divId = 'apiParamsListDiv';
        var tableId = 'apiParamsListTable';
        var pagerId = 'apiPager';
        var btnArray = Array('edit','delete');
        var thArray = Array('id');

        // Initialize the arguments for ajaxSend function
        var url = 'scripts/reqApi.php';
        var method = 'POST';
        var debug = 0;
        var bypassBlocking = 0;
        var bypassLoading = 0;
        var pageNumber = 1;

        var fCallback = loadDefaultListing;
        var formData = {command: 'getApiParamData', pageNumber : pageNumber};
        $(document).ready(function() {
            // First load of this page
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);

            // Search function
            $('#apiParamSearch').click(function() {
                pagingCallBack(pageNumber);
                $('#searchMsg').addClass('alert-success').html('<span>Search successful.</span>').show();
                setTimeout(function() {
                    $('#searchMsg').removeClass('alert-success').html('').hide(); 
                }, 3000);
            });

            $('#apiParamReset').click(function() {
                $("#searchParamHistory")[0].reset();
            });
        });

        function pagingCallBack(pageNumber) {
            var searchId = 'searchParamHistory';
            var searchData = buildSearchData(searchId);

            if(pageNumber > 1) bypassLoading = 1;

            var formData = {
                command : "getApiParamData",
                inputData: searchData,
                pageNumber: pageNumber
            };
            fCallback = loadSearch;
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }

        function loadDefaultListing(data, message) {
            buildTable(data.apiParam, tableId, divId, thArray, btnArray);
            pagination(pagerId, data.pageNumber, data.totalPage, data.totalRecord, data.numRecord);
            if(typeof(data.apiParam) != 'undefined') {
                $('#apiMsg').removeClass('alert-success').html('').hide();
//                buildTable(data.apiParam, tableId, divId, thArray, btnArray);
//                pagination(pagerId, data.pageNumber, data.totalPage, data.totalRecord, data.numRecord);
            } else
                $('#apiMsg').addClass('alert-success').html('<span>'+message+'</span>').show();
        }

        function tableBtnClick(btnId) {
            var btnName = $('#'+btnId).attr('id').replace(/\d+/g, '');
            var tableRow = $('#'+btnId).parent('td').parent('tr');
            var tableId = $('#'+btnId).closest('table');

            if (btnName == 'edit') {
                var editId = tableRow.find('td:first-child').text();
                var editUrl = 'editApiParam.php?id='+editId;
                window.location = editUrl;
            } else if (btnName == 'delete') {
                var canvasBtnArray = Array('Ok');
                var message = 'Are you sure you want to delete this API Parameter?';
                showMessage(message, '', 'Delete API Parameter', 'trash', '', canvasBtnArray);
                $('#canvasOkBtn').click(function() {
                    var tableColVal = tableRow.find('td:first-child').text();
                    var formData = {
                        'command': 'deleteApiParam',
                        'deleteData' : tableColVal
                    };
                    fCallback = loadDelete;
                    ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
                });
            }
        }

        function loadSearch(data, message) {
            $('#'+divId).find('table#'+tableId).remove();
            $('#'+pagerId).find('li').remove();
            loadDefaultListing(data, message);
        }

        function loadDelete(data, message) {
            $('#'+divId).find('table#'+tableId).remove();
            $('#'+pagerId).find('li').remove();
            loadDefaultListing(data, message);
            $('#apiMsg').addClass('alert-success').html("<span>Api Parameter deleted successfully.</span>").show();
            setTimeout(function() {
                $('#apiMsg').removeClass('alert-success').html('').hide(); 
            }, 3000);
        }
    </script>
</html>