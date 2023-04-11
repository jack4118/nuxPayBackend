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
                            <div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
                                <div class="panel panel-default bx-shadow-none">
                                    
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <div class="row">
                                    <ul class="nav nav-tabs">
                                        <li><a href="systemInformation.php">System Information</a></li>
                                        <li class="active"><a data-toggle="tab" href="#">Bandwidth</a></li>
                                    </ul>

                                    <div class="tab-content m-b-30">
                                        <!-- <div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
                                            <div class="panel panel-default bx-shadow-none">
                                                <div class="panel-heading" role="tab" id="headingOne">
                                                    <h4 class="panel-title">
                                                        <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseOne" aria-expanded="false" aria-controls="collapseOne" class="collapse">
                                                            Search
                                                        </a>
                                                    </h4>
                                                </div>
                                                <div id="collapseOne" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingOne">
                                                    <div class="panel-body">
                                                        <div id="searchMsg" class="text-center alert" style="display: none;"></div>
                                                        <form id="searchCredit" role="form">
                                                            <div class="col-sm-4 form-group">
                                                                <label class="control-label">Credit Name</label>
                                                                <input type="text" class="form-control" dataName="name" dataType="text">
                                                            </div>
                                                            <div class="col-sm-4 form-group">
                                                                <label class="control-label">Translation Code</label>
                                                                <input type="text" class="form-control" dataName="translation_code" dataType="text">
                                                            </div>
                                                            <div class="col-sm-4 form-group">
                                                                <label class="control-label">Priority</label>
                                                                <input type="text" class="form-control" dataName="priority" dataType="text">
                                                            </div>
                                                        </form>
                                                        <div class="col-sm-12">
                                                            <button id="searchCreditBtn" type="submit" class="btn btn-primary waves-effect waves-light">Search</button>
                                                            <button type="submit" id="resetBtn" class="btn btn-default waves-effect waves-light">Reset</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div> -->

                                        <div id="alerMsg" class="alert" style="display: none;"></div>
                                        <div id="listingDiv" class="table-responsive"></div>
                                        <span id="paginateText"></span>
                                        <div class="text-center">
                                            <ul id="listingPager" class="pagination pagination-md"></ul>
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
        <!-- ============================================================== -->
        <!-- End Right content here -->
        <!-- ============================================================== -->
    </div>
    <!-- END wrapper -->

    <script>var resizefunc = [];</script>

    <!-- jQuery  -->
    <?php include("shareJs.php"); ?>

<script>
    // Initialize all the id in this page
    var divId = 'listingDiv';
    var tableId = 'listingTable';
    var pagerId = 'listingPager';
    var btnArray = Array();
    var thArray = Array (
        'Host',
        'Minimal Receive Rate',
        'Minimal Transmit Rate',
        'Maximum Receive Rate',
        'Maximum Transmit Rate',
        'Average Receive Rate',
        'Average Transmit Rate'
    );

    // Initialize the arguments for ajaxSend function
    var url            = 'scripts/reqSystem.php';
    var method         = 'POST';
    var debug          = 0;
    var bypassBlocking = 0;
    var bypassLoading  = 0;
    var pageNumber     = 1;

    $(document).ready(function() {
        
        var formData = {
            command: 'getSystemBandwidth',
            pageNumber : pageNumber
        };
        var fCallback = loadDefaultListing;
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
    });

    function loadDefaultListing(data, message) {
        var tableNo;
        buildTable(data.bandwidthList, tableId, divId, thArray, btnArray, message, tableNo);
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

    // function tableBtnClick(btnId) {
    //     var btnName = $('#'+btnId).attr('id').replace(/\d+/g, '');
    //     var tableRow = $('#'+btnId).parent('td').parent('tr');
    //     var tableId = $('#'+btnId).closest('table');
        
    //     if (btnName == 'view') {
    //         var recordId = tableRow.attr('data-th');
    //         var editUrl = 'viewSystemInformation.php?id='+recordId;
    //         window.location = editUrl;
    //     }
    // }
</script>
</html>