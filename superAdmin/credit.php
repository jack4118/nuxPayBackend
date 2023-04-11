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
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <a href="newCredit.php" type="button" class="btn btn-primary waves-effect w-md waves-light m-b-20">New Credit</a>
                                <form>
                                    <div id="basicwizard" class="pull-in">
                                        <div class="tab-content b-0 m-b-0 p-t-0">
                                            <div id="alertMsg" class="alert" style="display: none;"></div>
                                            <div id="creditListDiv" class="table-responsive"></div>
                                            <span id="paginateText"></span>
                                            <div class="text-center">
                                                <ul class="pagination pagination-md" id="pagerCreditList"></ul>
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
    var divId = 'creditListDiv';
    var tableId = 'creditListTable';
    var pagerId = 'pagerCreditList';
    var btnArray = Array('edit','delete');
    var thArray = Array('ID',
                        'Name',
                        'Description',
                        'Translation Code',
                        'Priority',
                        'Created At',
                        'Updated At'
                       );
        
    // Initialize the arguments for ajaxSend function
    var url = 'scripts/reqCredit.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;
    var pageNumber = 1;
        
    var fCallback = loadDefaultListing;
    var formData = {command: 'getCredits', pageNumber : pageNumber};
    $(document).ready(function() {
        // First load of this page
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        
        $('#resetBtn').click(function() {
            $('#alertMsg').removeClass('alert-success').html('').hide();
            $('#searchCredit').find('input').each(function() {
               $(this).val(''); 
            });
        });
        
        $('#searchCreditBtn').click(function() {
            pagingCallBack(pageNumber, loadSearch);
        }); 
    });
    
    function loadDefaultListing(data, message) {
        var tableNo;
        buildTable(data.creditList, tableId, divId, thArray, btnArray, message, tableNo);
        pagination(pagerId, data.pageNumber, data.totalPage, data.totalRecord, data.numRecord);
    }

    function loadSearch(data, message) {
        loadDefaultListing(data, message);
        $('#searchMsg').addClass('alert-success').html('<span>Search successful.</span>').show();
        setTimeout(function() {
            $('#searchMsg').removeClass('alert-success').html('').hide(); 
        }, 3000);
    }

    function loadDelete(data, message) {
        loadDefaultListing(data, message);
        $('#alertMsg').addClass('alert-success').html("<span>Delete successful.</span>").show();
        setTimeout(function() {
            $('#alertMsg').removeClass('alert-success').html('').hide(); 
        }, 3000);
    }
    
    function tableBtnClick(btnId) {
        var btnName = $('#'+btnId).attr('id').replace(/\d+/g, '');
        var tableRow = $('#'+btnId).parent('td').parent('tr');
        var tableId = $('#'+btnId).closest('table');
        
        if (btnName == 'edit') {
            var editId = tableRow.attr('data-th');
            var editUrl = 'editCredit.php?id='+editId;
            window.location = editUrl;
        }
        else if (btnName == 'delete') {
            var canvasBtnArray = Array('Ok');
            var message = 'Are you sure you want to delete this Credit?';
            showMessage(message, '', 'Delete Credit', 'trash', '', canvasBtnArray);
            $('#canvasOkBtn').click(function() {
                var tableColVal = tableRow.attr('data-th');
                var formData = {
                    'command': 'deleteCredit',
                    'deleteData' : tableColVal
                };
                fCallback = loadDelete;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            });
        }
    }

    function pagingCallBack(pageNumber, fCallback){
        var searchId = 'searchCredit';
        var searchData = buildSearchDataByType(searchId);

        if(pageNumber > 1) bypassLoading = 1;

        var formData = {
            command : "getCredits",
            inputData: searchData,
            pageNumber: pageNumber
        };
        if(!fCallback)
            fCallback = loadDefaultListing;
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
    }
</script>
</body>
</html>