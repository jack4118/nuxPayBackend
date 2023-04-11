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
                    <!-- <div class="row">
                        <div class="col-lg-12">
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
                                            <form id="searchSetting" role="form">
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="name">Name</label>
                                                    <input type="text" class="form-control">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="type">Type</label>
                                                    <input type="text" class="form-control">
                                                </div>
                                            </form>
                                            <div class="col-sm-12">
                                                <button id="searchSettingBtn" type="button" class="btn btn-primary waves-effect waves-light">Search</button>
                                                <button type="button" class="btn btn-default waves-effect waves-light" id="resetSetting">Reset</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> -->

                    <!-- <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <div id="basicwizard" class="pull-in">
                                    <div class="tab-content b-0 m-b-0 p-t-0">
                                        <div id="alerMsg" class="alert" style="display: none;"></div>
                                        <div id="systemListDiv" class="table-responsive"></div>
                                        <span id="paginateText"></span>
                                        <div class="text-center">
                                            <ul class="pagination pagination-md" id="pagerSettingList"></ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> -->
                    <!-- End row -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <div class="row">
                                    <ul class="nav nav-tabs">
                                        <li class="active"><a data-toggle="tab" href="#">System Information</a></li>
                                        <li><a href="bandwidth.php">Bandwidth</a></li>
                                    </ul>
                                    <div class="tab-content m-b-30">
                                        <div id="alerMsg" class="alert" style="display: none;"></div>
                                        <div id="systemListDiv" class="table-responsive"></div>
                                        <span id="paginateText"></span>
                                        <div class="text-center">
                                            <ul class="pagination pagination-md" id="pagerSettingList"></ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- row -->
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
    // Initialize all the id in this page
    var divId = 'systemListDiv';
    var tableId = 'systemListTable';
    var pagerId = 'pagerSettingList';
    var btnArray = Array('view');
    var thArray = Array (
        'ID',
        'Server Name',
        'Server IP',
        'Type',
        'Total CPU',
        'CPU Load',
        'Total Memory',
        'Memory Usage',
        'Total Swap',
        'Swap Usage',
        'Disk Size',
        'Disk Usage'
    );

    // Initialize the arguments for ajaxSend function
    var url = 'scripts/reqSystem.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;
    var pageNumber = 1;

    var fCallback = loadDefaultListing;
    var formData = {command: 'getSystemList', pageNumber : pageNumber};
    $(document).ready(function() {
        // First load of this page
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
    });

    function loadDefaultListing(data, message) {
        var tableNo;
        buildTable(data.apiData, tableId, divId, thArray, btnArray, message, tableNo);
        pagination(pagerId, data.pageNumber, data.totalPage, data.totalRecord, data.numRecord);
    }

    function pagingCallBack(pageNumber, fCallback) {
        if(pageNumber > 1) bypassLoading = 1;

        var formData = {
            command : "getSystemList",
            pageNumber: pageNumber
        };
        if(!fCallback)
            fCallback = loadDefaultListing;
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
    }

    function tableBtnClick(btnId) {
        var btnName = $('#'+btnId).attr('id').replace(/\d+/g, '');
        var tableRow = $('#'+btnId).parent('td').parent('tr');
        var tableId = $('#'+btnId).closest('table');
        
        if (btnName == 'view') {
            var recordId = tableRow.attr('data-th');
            var editUrl = 'viewSystemInformation.php?id='+recordId;
            window.location = editUrl;
        }
    }
</script>
</html>