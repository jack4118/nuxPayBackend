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
                                            <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseOne" aria-expanded="true" aria-controls="collapseOne" class="collapse">
                                                Search
                                            </a>
                                        </h4>
                                    </div>
                                    <div id="collapseOne" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingOne" aria-expanded="true" style="">
                                        <div class="panel-body">
                                            <form id="searchLanguage" role="form">
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="language">Language</label>
                                                    <input type="text" class="form-control" dataName="language" dataType="text">

                                                </div>
                                                <!-- <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="language_code">Language Code</label>
                                                    <input type="text" class="form-control">

                                                </div> -->
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="iso_code">ISO Code</label>
                                                    <input type="text" class="form-control" dataName="isoCode" dataType="text">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label data-th="disabled" class="control-label">Status</label>
                                                    <select class="form-control" dataName="status" dataType="text">
                                                        <option value="">All</option>
                                                        <option value="0">Active</option>
                                                        <option value="1">Disabled</option>
                                                    </select>
                                                </div>

                                            <div class="col-sm-12">
                                                <button id="searchLanguageBtn" type="button" class="btn btn-primary waves-effect waves-light">Search</button>
                                                <button id="resetLanguage" type="button" class="btn btn-default waves-effect waves-light">Reset</button>
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
                                <a href="newLanguage.php" type="button" class="btn btn-primary waves-effect w-md waves-light m-b-20">New Language</a>
                                <form>
                                    <div id="basicwizard" class="pull-in">
                                            <div class="tab-content b-0 m-b-0 p-t-0">
                                            <div id="alertMsg" class="text-center alert" style="display: none;"></div>
                                            <div id="languageListDiv" class="table-responsive"></div>
                                            <span id="paginateText"></span>
                                            <div class="text-center">
                                                <ul class="pagination pagination-md" id="pagerLanguageList"></ul>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

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

</body>
    <script>
        // Initialize all the id in this page
        var divId = 'languageListDiv';
        var tableId = 'languageListTable';
        var pagerId = 'pagerLanguageList';
        var btnArray = Array('edit','delete');
        var thArray = Array('ID',
                            'Language',
                            'Language Code',
                            'ISO Code',
                            'Status',
                            'Created Date'
                           );

        // Initialize the arguments for ajaxSend function
        var url = 'scripts/reqLanguage.php';
        var method = 'POST';
        var debug = 0;
        var bypassBlocking = 0;
        var bypassLoading = 0;
        var pageNumber = 1;

        var fCallback = loadDefaultListing;
        var formData = {command: 'getLanguageList', pageNumber : pageNumber};
        $(document).ready(function() {
            // First load of this page
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);

            // Search function
            $('#searchLanguageBtn').click(function() {
                pagingCallBack(pageNumber, loadSearch);
            });

            //Reset he API search from
            $('#resetLanguage').click(function() {
                $("#searchLanguage")[0].reset();
            });
        });

        function loadDefaultListing(data, message) {
            var tableNo;
            buildTable(data.languageList, tableId, divId, thArray, btnArray, message, tableNo);
            pagination(pagerId, data.pageNumber, data.totalPage, data.totalRecord, data.numRecord);
        }

        function tableBtnClick(btnId) {
            var btnName = $('#'+btnId).attr('id').replace(/\d+/g, '');
            var tableRow = $('#'+btnId).parent('td').parent('tr');
            var tableId = $('#'+btnId).closest('table');

            if (btnName == 'edit') {
                var editId = tableRow.attr('data-th');
                var editUrl = 'editLanguage.php?id='+editId;
                window.location = editUrl;
            } else if (btnName == 'delete') {
                var canvasBtnArray = Array('Ok');
                var message = 'Are you sure you want to delete this Language.?';
                showMessage(message, '', 'Delete Language', 'trash', '', canvasBtnArray);
                $('#canvasOkBtn').click(function() {
                    var tableColVal = tableRow.attr('data-th');
                    var formData = {
                        'command': 'deleteLanguage',
                        'deleteData' : tableColVal
                    };
                    fCallback = loadDelete;
                    ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
                });
            }
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
            $('#alertMsg').addClass('alert-success').html("<span>Language deleted successfully.</span>").show();
            setTimeout(function() {
                $('#alertMsg').removeClass('alert-success').html('').hide(); 
            }, 3000);
        }

        function pagingCallBack(pageNumber, fCallback){
            var searchId = 'searchLanguage';
            var searchData = buildSearchDataByType(searchId);
            // alert("pageNumber : " + pageNumber);

            if(pageNumber > 1) bypassLoading = 1;

            var formData = {
                command : "getLanguageList",
                inputData: searchData,
                pageNumber: pageNumber
            };
            if(!fCallback)
                fCallback = loadDefaultListing;
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }
    </script>

</html>