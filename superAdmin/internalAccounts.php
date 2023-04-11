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
                                    <div id="collapseOne" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingOne" aria-expanded="true">
                                        <div class="panel-body">
                                        <div id="searchMsg" class="text-center alert" style="display: none;"></div>
                                            <form id="searchInternalAccount" role="form">
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="username">Username</label>
                                                    <input type="text" class="form-control" dataName="username" dataType="text">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="name">Name</label>
                                                    <input type="text" class="form-control" dataName="name" dataType="text">
                                                </div>
                                                <!-- <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="type"> Type </label>
                                                    <select id="type" class="form-control" name="type">
                                                        <option value="">--Please Select--</option>
                                                        <option value="internal" selected="selected">Internal</option>
                                                    </select>
                                                </div>-->
                                            </form>
                                            <div class="col-sm-12">
                                                <button id="searchInternalAccountBtn" type="button" class="btn btn-primary waves-effect waves-light">Search</button>
                                                <button type="button" class="btn btn-default waves-effect waves-light" id="resetInternalAccount">Reset</button>
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
                                <a href="newInternalAccount.php" type="button" class="btn btn-primary waves-effect w-md waves-light m-b-20" title="New Internal Account">New</a>
                                <form>
                                    <div id="basicwizard" class="pull-in">
                                        <div class="tab-content b-0 m-b-0 p-t-0">
                                            <div id="alertMsg" class="text-center alert" style="display: none;"></div>
                                            <div id="clientListDiv" class="table-responsive"></div>
                                            <span id="paginateText"></span>
                                            <div class="text-center">
                                                <ul class="pagination pagination-md" id="pagerInternalAccountList"></ul>
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
    showCanvas();
        // Initialize all the id in this page
        var divId = 'clientListDiv';
        var tableId = 'clientListTable';
        var pagerId = 'pagerInternalAccountList';
        var btnArray = Array('edit','delete');
        var thArray = Array('ID',
                            'Username',
                            'Name',
                            'Remark'
                           );

        // Initialize the arguments for ajaxSend function
        var url = 'scripts/reqInternalAccounts.php';
        var method = 'POST';
        var debug = 0;
        var bypassBlocking = 0;
        var bypassLoading = 0;
        var pageNumber = 1;

        var fCallback = loadDefaultListing;
        $(document).ready(function() {
            // First load of this page
            var formData = {
                command : "getInternalAccountsList"
            };
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);

            // Search function
            $('#searchInternalAccountBtn').click(function() {
                pagingCallBack(pageNumber, loadSearch);
            });

            $('#resetInternalAccount').click(function() {
                $("#searchInternalAccount")[0].reset();
            });
        });

        function loadSearch(data, message) {
            loadDefaultListing(data, message);
            $('#searchMsg').addClass('alert-success').html('<span>Search successful.</span>').show();
            setTimeout(function() {
                $('#searchMsg').removeClass('alert-success').html('').hide(); 
            }, 3000);
        }
    
        function loadDefaultListing(data, message) {
            var tableNo;
            buildTable(data.internalAccList, tableId, divId, thArray, btnArray, message, tableNo);
            pagination(pagerId, data.pageNumber, data.totalPage, data.totalRecord, data.numRecord);
        }

        function tableBtnClick(btnId) {
            var btnName = $('#'+btnId).attr('id').replace(/\d+/g, '');
            var tableRow = $('#'+btnId).parent('td').parent('tr');
            var tableId = $('#'+btnId).closest('table');

            if (btnName == 'edit') {
                var editId = tableRow.attr('data-th');
                var editUrl = 'editInternalAccount.php?id='+editId;
                window.location = editUrl;
            } else if (btnName == 'delete') {
                var canvasBtnArray = Array('Ok');
                var message = 'Are you sure you want to delete Internal Account?';
                showMessage(message, '', 'Delete InternalAccount', 'trash', '', canvasBtnArray);
                $('#canvasOkBtn').click(function() {
                    var tableColVal = tableRow.attr('data-th');
                    var formData = {
                        'command': 'deleteInternalAccount',
                        'deleteData' : tableColVal
                    };
                    fCallback = loadDelete;
                    ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
                });
            }
        }

        function loadDelete(data, message) {
            loadSearch(data, message);
            $('#alertMsg').addClass('alert-success').html("<span>InternalAccount deleted successfully.</span>").show();
            setTimeout(function() {
                $('#alertMsg').removeClass('alert-success').html('').hide(); 
            }, 3000);
        }

    function pagingCallBack(pageNumber, fCallback) {
        var searchId = 'searchInternalAccount';
        var searchData = buildSearchDataByType(searchId);

        if(pageNumber > 1) bypassLoading = 1;
        var formData = {
            command : "getInternalAccountsList",
            inputData: searchData,
            pageNumber: pageNumber
        };
        if(!fCallback)
            fCallback = loadDefaultListing;
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
    }
</script>
</html>
