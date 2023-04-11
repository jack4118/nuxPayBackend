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
                                        <form role="form"  id="searchMessageError">
                                            <div class="col-sm-4 form-group">
                                                <label data-th="error_code" class="control-label">Error Code</label>
                                                <select class="form-control" name ="error_code" id="error_code" dataName="errorCode" dataType="text">
                                                    <option value="">--Please Select--</option>
                                                </select>
                                            </div>
                                        </form>
                                        <div class="col-sm-12">
                                            <button type="button" class="btn btn-primary waves-effect waves-light" id="searchMessageErrorBtn">Search</button>
                                            <button type="button" class="btn btn-default waves-effect waves-light" id="resetMessageErrorBtn">Reset</button>
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
                            <div id="basicwizard" class="pull-in">
                                <div class="tab-content b-0 m-b-0 p-t-0">
                                    <div id="alertMsg" class="text-center alert" style="display: none;"></div>
                                    <div id="messageErrorListDiv" class="table-responsive"></div>
                                    <span id="paginateText"></span>
                                    <div class="text-center">
                                        <ul class="pagination pagination-md" id="pagerMessageErrorList"></ul>
                                    </div>
                                </div>
                            </div>
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
        getErrorCode();
        // Initialize all the id in this page
        var divId = 'messageErrorListDiv';
        var tableId = 'messageErrorListTable';
        var pagerId = 'pagerMessageErrorList';
        var btnArray = Array();
        var thArray = Array('ID',
                            'Content',
                            'Processor',
                            'Error Code',
                            'Error Description'
                           );

        // Initialize the arguments for ajaxSend function
        var url = 'scripts/reqMessageError.php';
        var method = 'POST';
        var debug = 0;
        var bypassBlocking = 0;
        var bypassLoading = 0;
        var pageNumber = 1;

        var fCallback = loadDefaultListing;
        var formData = {command: 'getMessageErrorList', pageNumber: pageNumber};
        $(document).ready(function() {
            // First load of this page
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);

            // Search function
            $('#searchMessageErrorBtn').click(function() {
                var searchId = 'searchMessageError';
                var searchData = buildSearchDataByType(searchId);
                formData = {
                    command : "getMessageErrorList",
                    searchData: searchData,
                    pageNumber: pageNumber
                };
                fCallback = loadSearch;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            });

            //Reset he API search from
            $('#resetMessageErrorBtn').click(function() {
                $("#searchMessageError")[0].reset();
            });
        });

        function loadDefaultListing(data, message) {
            var tableNo;
            buildTable(data.messageErrorList, tableId, divId, thArray, btnArray, message, tableNo);
            pagination(pagerId, data.pageNumber, data.totalPage, data.totalRecord, data.numRecord);
        }

        function loadSearch(data, message) {
            loadDefaultListing(data, message);
            $('#searchMsg').addClass('alert-success').html('<span>Search successful.</span>').show();
            setTimeout(function() {
                $('#searchMsg').removeClass('alert-success').html('').hide(); 
            }, 3000);
        }

        function getErrorCode() {
            showCanvas();
            $.ajax({
                type: 'POST',
                url: 'scripts/reqMessageError.php',
                data: {'command' : "getErrorCode"},
                dataType: 'text',
                encode: true
            }).done(function(data) {
                var obj = JSON.parse(data);
                hideCanvas();
                if (obj.status == 'ok') {
                    $('#error_code').find('option:not(:first-child)').remove();
                    if (typeof(obj.data.errorCode) != 'undefined') {
                        errName = obj.data.errorCode;
                        $.each(errName, function(key, val) {
                            $('#error_code').append('<option value="' + val['errorList'] + '">' + val['errorList'] + '</option>');
                        });
                    }
                    else {
                        $('#alertMsg').addClass('alert-success').html('<span>' + obj.statusMsg + '</span>').show();
                        setTimeout(function() {
                            $('#alertMsg').removeClass('alert-success').html('').hide();
                        }, 3000);
                    }
                }
                else
                    errorHandler(obj.code, obj.statusMsg);
            });
        }

        function pagingCallBack(pageNumber, fCallback){
            var searchId = 'searchMessageError';
            var searchData = buildSearchDataByType(searchId);
            if(pageNumber > 1) bypassLoading = 1;

            var formData = {
                command : "getMessageErrorList",
                searchData: searchData,
                pageNumber: pageNumber
            };
            if(!fCallback)
                fCallback = loadDefaultListing;
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }
    </script>
</html>