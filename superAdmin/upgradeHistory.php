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
                                            <form id="searchUpgradeHistory" role="form">
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label">TimeStamp</label>
                                                    <input type="text" class="form-control" dataName="id" dataType="text">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="">Patched On Date</label>
                                                    <div>
                                                        <input id="searchDate" type="text" class="form-control" dataName="searchDate" dataType="singleDate">
                                                    </div>
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label">Patched On Time</label>
                                                    <div class="input-group">
                                                        <div>
                                                            <input id="timeFrom" type="text" class="form-control" dataName="searchTime" dataType="timeRange" dataParent="searchDate">
                                                        </div>
                                                        <span class="input-group-addon">to</span>
                                                        <div>
                                                            <input id="timeTo" type="text" class="form-control" dataName="searchTime" dataType="timeRange" dataParent="searchDate">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label">Patched By</label>
                                                    <input type="text" class="form-control" dataName="created_by" dataType="text">
                                                </div>
                                            </form>
                                            <div class="col-sm-12">
                                                <button type="submit" id="searchUpgradeHistoryBtn" class="btn btn-primary waves-effect waves-light">Search</button>
                                                <button type="submit" id="resetBtn" class="btn btn-default waves-effect waves-light">Reset</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <form>
                                    <div id="basicwizard" class=" pull-in">

                                        <div class="tab-content b-0 m-b-0 p-t-0">
                                            <div id="alertMsg" class="table-responsive"></div>
                                            <div id="upgradeHistoryDiv" class="table-responsive"></div>
                                            <span id="paginateText"></span>
                                            <div class="text-center">
                                                <ul class="pagination pagination-md" id="pagerUpgradeHistory"></ul>
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
    var divId = 'upgradeHistoryDiv';
    var tableId = 'upgradeHistoryTable';
    var pagerId = 'pagerUpgradeHistory';
    var btnArray = Array();
    var thArray = Array('ID', 
                        'Description',
                        'Create Time',
                        'Created By'
                       );
        
    // Initialize the arguments for ajaxSend function
    var url = 'scripts/reqUpgrades.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;
    var pageNumber = 1;
        
    var fCallback = loadDefaultListing;
    var formData = {command: 'getUpgradesHistory', pageNumber : pageNumber};
    $(document).ready(function() {
        setTodayDatePicker();
        // First load of this page
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        
        $('#searchUpgradeHistoryBtn').click(function() {
            pagingCallBack(pageNumber, loadSearch);
        });
        
        $('#resetBtn').click(function() {
            $('#alertMsg').removeClass('alert-success').html('').hide();
            $('#searchUpgradeHistory').find('input').each(function() {
               $(this).val(''); 
            });
        });
    });
    
    function loadDefaultListing(data, message) {
        var tableNo;
        buildTable(data.upgradeList, tableId, divId, thArray, btnArray, message, tableNo);
        pagination(pagerId, data.pageNumber, data.totalPage, data.totalRecord, data.numRecord);
    }

    function loadSearch(data, message) {
        loadDefaultListing(data, message);
        $('#searchMsg').addClass('alert-success').html('<span>Search successful.</span>').show();
        setTimeout(function() {
            $('#searchMsg').removeClass('alert-success').html('').hide(); 
        }, 3000);
    }

    function pagingCallBack(pageNumber, fCallback){
        var searchId = 'searchUpgradeHistory';
        var searchData = buildSearchDataByType(searchId);

        if(pageNumber > 1) bypassLoading = 1;

        var formData = {
            command : "getUpgradesHistory",
            inputData: searchData,
            pageNumber: pageNumber
        };
        if(!fCallback)
            fCallback = loadDefaultListing;
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
    }

    // Set the default date which is today.
    // Set the timepicker
    function setTodayDatePicker() {
        var today = new Date();
        var dd = today.getDate();
        var mm = today.getMonth()+1;
        var yyyy = today.getFullYear();
        if(dd<10){
            dd='0'+dd;
        } 
        if(mm<10){
            mm='0'+mm;
        }
        var today = dd+'/'+mm+'/'+yyyy;
        
        $('#searchDate').daterangepicker({
            singleDatePicker: true,
            timePicker: false,
            locale: {
                format: 'DD/MM/YYYY'
            }
        });
        var dateToday = $('#searchDate').val('');

        $('#timeFrom').timepicker({
            defaultTime : '',
            showSeconds: true
        });
        $('#timeTo').timepicker({
            defaultTime : '',
            showSeconds: true
        });

        return today;
    }

</script>
</body>
</html>