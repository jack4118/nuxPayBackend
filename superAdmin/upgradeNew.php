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
                    
<!--
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
                                    <div id="collapseOne" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingOne" aria-expanded="false" style="height: 0px;">
                                        <div class="panel-body">
                                            <form role="form">
                                                <div class="col-sm-4 form-group">
                                                    <label for="">Full Name</label>
                                                    <input id="" type="text" class="form-control">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label for="">Email</label>
                                                    <input type="text" class="form-control">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label">Role</label>
                                                    <select class="form-control">
                                                        <option>All</option>
                                                        <option>Project Leader</option>
                                                        <option>Project Team</option>
                                                    </select>
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label">Status</label>
                                                    <select class="form-control">
                                                        <option>All</option>
                                                        <option>Active</option>
                                                        <option>Disabled</option>
                                                    </select>
                                                </div>
                                                <div class="col-sm-12">
                                                    <button type="submit" class="btn btn-primary waves-effect waves-light">Search</button>
                                                    <button type="submit" class="btn btn-default waves-effect waves-light">Reset</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
-->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <form>
                                    <div id="basicwizard" class=" pull-in">
                                        <div class="tab-content b-0 m-b-0 p-t-0">
                                            <div id="alertMsg" class="alert" style="display: none;"></div>
                                            <div id="upgradeNewDiv" class="table-responsive"></div>
                                            <div class="text-center">
                                                <ul class="pagination pagination-md" id="pagerUpgradeNew"></ul>
                                            </div>
                                            <div>
                                                <input id="upgradeAll" class="btn btn-primary" type="button" value="Update All" style="display: none;"/>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                
                            </div>
                        </div>

                    </div>
                    <!-- End row -->
                    
                    <div class="row" style="display: none;">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <form>
                                    <div id="basicwizard" class=" pull-in">
                                        <div class="tab-content b-0 m-b-0 p-t-0">
                                            <div id="upgradeErrorDiv" class="table-responsive"></div>
                                            <div class="text-center">
                                                <ul class="pagination pagination-md" id="pagerUpgradeError"></ul>
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
    var divId = 'upgradeNewDiv';
    var tableId = 'newUpgradeTable';
    var pagerId = 'pagerUpgradeNew';
    var btnArray = Array();
    var thArray = Array('Timestamp',
                        'Description',
                        'Status'
                       );
    
    var errorDivId = 'upgradeErrorDiv';
    var errorTableId = 'errorUpgradeTable';
    var errorPagerId = 'pagerUpgradeError';
        
    // Initialize the arguments for ajaxSend function
    var url = 'scripts/reqUpgrades.php';
    var method = 'POST';
    var debug = 1;
    var bypassBlocking = 0;
    var bypassLoading = 0;
        
    var fCallback = loadDefaultListing;
    var formData = {command: 'getNewUpgrades'};
    $(document).ready(function() {
        // First load of this page
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        
        $('#upgradeAll').click(function() {
            $('#alertMsg').removeClass('alert-success').html('').hide();
            var canvasBtnArray = Array('OK');
            var message = 'You are about to commit changes to the database. Please wait for the updating process to complete before proceeding to any other actions. Click OK to proceed...';
            showMessage(message, '', 'Run Upgrades', 'trash', '', canvasBtnArray);
            $('#canvasOKBtn').click(function() {
                $('#'+errorDivId).closest('div.row').hide();
                var formData = {
                    'command': 'updateAllUpgrades'
                };
                
                var fCallback = loadUpgrade;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            });
        });
    });
        
    function loadDefaultListing(data, message) {
        if(typeof(data.upgradeList) != 'undefined') {
            var tableNo;
            $('#alertMsg').removeClass('alert-success').html('').hide();
            buildTable(data.upgradeList, tableId, divId, thArray, btnArray, message, tableNo);
            paginateTable(tableId, pagerId, true, true, <?php echo $_SESSION["pagingCount"];?>);
            $('#upgradeAll').show();
        }
        else
            $('#alertMsg').addClass('alert-success').html('<span>'+message+'</span>').show();
    }
    
    function loadUpgrade(data, message) {
        $('#upgradeAll').hide();
        $('#'+errorDivId).closest('div.row').hide();
        $('#'+divId).find('table').remove();
        $('#'+pagerId).find('li').remove();
        if(typeof(data) != 'undefined') {
            var tableNo;
            if (typeof(data.errorMsg) != 'undefined') {
                thArray = Array('Timestamp', 'Message');
                buildTable(data.errorMsg, errorTableId, errorDivId, thArray, btnArray, message, tableNo);
                paginateTable(errorTableId, errorPagerId, true, true, <?php echo $_SESSION["pagingCount"];?>);
                $('#'+errorDivId).closest('div.row').show();
            }
            if (typeof(data.upgradeList) != 'undefined') {
                thArray = Array('Timestamp', 'Description', 'Status');
                buildTable(data.upgradeList, tableId, divId, thArray, btnArray, message, tableNo);
                paginateTable(tableId, pagerId, true, true, <?php echo $_SESSION["pagingCount"];?>);
                $('#upgradeAll').show();
            }
            else
                $('#alertMsg').addClass('alert-success').html('<span>'+message+'</span>').show();
        }
        else
            $('#alertMsg').addClass('alert-success').html('<span>'+message+'</span>').show();
    }
</script>
</body>
</html>


