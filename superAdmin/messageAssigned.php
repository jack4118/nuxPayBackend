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
                                            <form role="form" id="searchMessageHistory">
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="code">Code</label>
                                                    <input type="text" class="form-control" dataName="code" dataType="text">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label for="" data-th="recipient" class="control-label">Recipient</label>
                                                    <input type="text" class="form-control" dataName="recipient" dataType="text">
                                                </div>
                                            </form>
                                            <div class="col-sm-12">
                                                <button type="button" class="btn btn-primary waves-effect waves-light" id="messageAssignedSearch">Search</button>
                                                <button type="button" class="btn btn-default waves-effect waves-light" id="messageAssignedReset">Reset</button>
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
                                <a href="newMessageAssigned.php" type="button" class="btn btn-primary waves-effect w-md waves-light m-b-20">New Message Assigned</a>
                                <form>
                                    <div id="basicwizard" class=" pull-in">
                                        <div class="tab-content b-0 m-b-0 p-t-0">
                                            <div id="alertMsg" class="text-center alert" style="display: none;"></div>
                                            <div id="messageAssignedList" class="table-responsive"></div>
                                            <span id="paginateText"></span>
                                            <div class="text-center">
                                                <ul class="pagination pagination-lg" id="messageAssignedPager"></ul>
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
<!-- jQuery  -->
<?php include("shareJs.php"); ?>
<script>
    var resizefunc = [];
    
    // Initialize all the id in this page
    var divId = 'messageAssignedList';
    var tableId = 'messageAssignedTable';
    var pagerId = 'messageAssignedPager';
    var btnArray = Array('edit','delete');
    var thArray = Array('ID',
                        'Code',
                        'Recipient',
                        'Type'
                       );

    // Initialize the arguments for ajaxSend function
    var url = 'scripts/reqMessage.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;
    var pageNumber = 1;

    var fCallback = loadDefaultListing;
    var formData = {command: 'messageAssignedList'};
    $(document).ready(function() {
        // First load of this page
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);

        // Search function
        $('#messageAssignedSearch').click(function() {
            pagingCallBack(pageNumber, loadSearch);
        });

        $('#messageAssignedReset').click(function() {
            $("#searchMessageHistory")[0].reset();
        });
    });

    function loadDefaultListing(data, message) {
        var tableNo;
        buildTable(data.messageAssignedList, tableId, divId, thArray, btnArray, message, tableNo);
        pagination(pagerId, data.pageNumber, data.totalPage, data.totalRecord, data.numRecord);
    }

    function tableBtnClick(btnId) {
        var btnName = $('#'+btnId).attr('id').replace(/\d+/g, '');
        var tableRow = $('#'+btnId).parent('td').parent('tr');
        var tableId = $('#'+btnId).closest('table');

        if (btnName == 'edit') {
            var editId = tableRow.attr('data-th');
            var editUrl = 'editMessageAssigned.php?id='+editId;
            window.location = editUrl;
        } else if (btnName == 'delete') {
            var canvasBtnArray = Array('Ok');
            var message = 'Are you sure you want to delete this Message Assigned?';
            showMessage(message, '', 'Delete Message Assigned', 'trash', '', canvasBtnArray);
            $('#canvasOkBtn').click(function() {
                var tableColVal = tableRow.attr('data-th');
                var formData = {
                    'command': 'deleteMessageAssigned',
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
        $('#searchMsg').addClass('alert-success').html('<span>Search successful.</span>').show();
        setTimeout(function() {
            $('#searchMsg').removeClass('alert-success').html('').hide(); 
        }, 3000);
    }

    function loadDelete(data, message) {
        loadDefaultListing(data, message);
        $('#alertMsg').addClass('alert-success').html("<span>Message Assigned deleted successfully.</span>").show();
        setTimeout(function() {
            $('#alertMsg').removeClass('alert-success').html('').hide(); 
        }, 3000);
    }

    function pagingCallBack(pageNumber, fCallback){
        var searchId = 'searchMessageHistory';
        var searchData = buildSearchDataByType(searchId);

        if(pageNumber > 1) bypassLoading = 1;

        var formData = {
            command : "getMessageSearchData",
            searchData: searchData,
            pageNumber: pageNumber
        };
        if(!fCallback)
            fCallback = loadDefaultListing;
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
    }
</script>
</html>