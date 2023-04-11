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
                                            <form id="searchForm" role="form">
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="name">Role Name</label>
                                                    <input type="text" class="form-control">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" data-th="disabled">Status</label>
                                                    <select class="form-control">
                                                        <option value="0">Active</option>
                                                        <option value="1">Disabled</option>
                                                    </select>
                                                </div>
                                            </form>
                                            <div class="col-sm-12">
                                                <button id="searchBtn" type="submit" class="btn btn-primary waves-effect waves-light">Search</button>
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
                                <a href="newRole.php" type="button" class="btn btn-primary waves-effect w-md waves-light m-b-20">New Role</a>
                                <form>
                                    <div id="basicwizard" class="pull-in">
                                        <div class="tab-content b-0 m-b-0 p-t-0">
                                            <div id="alertMsg" class="alert" style="display: none;"></div>
                                            <div id="roleListDiv" class="table-responsive"></div>
                                            <span id="paginateText"></span>
                                            <div class="text-center">
                                                <ul class="pagination pagination-md" id="pagerRoleList"></ul>
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
    var divId = 'roleListDiv';
    var tableId = 'roleListTable';
    var pagerId = 'pagerRoleList';
    var btnArray = Array('edit','delete');
    var thArray = Array('ID',
                        'Name',
                        'Description',
                        'Site',
                        'Status'
                       );
        
    // Initialize the arguments for ajaxSend function
    var url = 'scripts/reqUsers.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;
    var pageNumber = 1;
        
    var fCallback = loadDefaultListing;
    $(document).ready(function() {
        // First load of this page
        var formData = {
            command : "getRoles"
        };
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        
        // Search function
        $('#searchBtn').click(function() {
            pagingCallBack(pageNumber, loadSearch);
        });
        
        $('#resetBtn').click(function() {
            $("#searchForm")[0].reset();

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
        buildTable(data.roleList, tableId, divId, thArray, btnArray, message, tableNo);
        pagination(pagerId, data.pageNumber, data.totalPage, data.totalRecord, data.numRecord);
    }
    
    function tableBtnClick(btnId) {
        var btnName = $('#'+btnId).attr('id').replace(/\d+/g, '');
        var tableRow = $('#'+btnId).parent('td').parent('tr');
        var tableId = $('#'+btnId).closest('table');
        
        if (btnName == 'edit') {
            var editId = tableRow.attr('data-th');
            var editUrl = 'editRole.php?id='+editId;
            window.location = editUrl;
        }
        else if (btnName == 'delete') {
            var canvasBtnArray = Array('Ok');
            var message = 'Are you sure you want to delete this role?';
            showMessage(message, '', 'Delete Role', 'trash', '', canvasBtnArray);
            $('#canvasOkBtn').click(function() {
                var tableColVal = tableRow.attr('data-th');
                var formData = {
                    'command': 'deleteRole',
                    'deleteData' : tableColVal
                };
                fCallback = loadDelete;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            });
        }
    }

    function loadDelete(data, message) {
        loadDefaultListing(data, message);
        $('#alertMsg').addClass('alert-success').html("<span>Delete successful.</span>").show();
        setTimeout(function() {
            $('#alertMsg').removeClass('alert-success').html('').hide(); 
        }, 3000);
    }
    function pagingCallBack(pageNumber, fCallback){
        var searchId = 'searchForm';
        var searchData = buildSearchData(searchId);

        if(pageNumber > 1) bypassLoading = 1;

        var formData = {
            command : "getRoles",
            inputData: searchData,
            pageNumber: pageNumber,
        };
        if(!fCallback)
            fCallback = loadDefaultListing;
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
    }
</script>
</body>
</html>
