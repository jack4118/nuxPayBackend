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
                                        <div id="searchMsg" class="text-center alert" style="display: none;"></div>
                                        <form id="searchActivity" role="form">
                                            <div class="col-sm-4 form-group">
                                                <label for="">Date</label>
                                                <input id="activityDate" type="text" class="form-control" dataName="activityDate" dataType="singleDate">
                                            </div>

                                            <div class="col-sm-4 form-group">
                                                <label class="control-label">Created At</label>
                                                <div class="input-group">
                                                    <div>
                                                        <input id="timeFrom" type="text" class="form-control" dataName="activityTime" dataType="timeRange" dataParent="activityDate">
                                                    </div>
                                                    <span class="input-group-addon">-</span>
                                                    <div>
                                                        <input id="timeTo" type="text" class="form-control" dataName="activityTime" dataType="timeRange" dataParent="activityDate">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="hidden">
                                                <label class="control-label hidden" data-th="time"></label>
                                                <input id="wsTimeFrom" class="hidden">
                                                <input id="wsTimeTo" class="hidden">
                                            </div>

                                            <div class="col-sm-4 form-group">
                                                <label class="control-label" for="" data-th="title">Title</label>
                                                <input type="text" class="form-control" dataName="title" dataType="text">
                                            </div>

                                            <div class="col-sm-4 form-group">
                                                <label class="control-label" for="" data-th="creator_type">Creator Type</label>
                                                <select class="form-control" dataName="creatorType" dataType="select">
                                                    <option value="">All</option>
                                                    <option value="SuperAdmin">SuperAdmin</option>
                                                    <option value="Admin">Admin</option>
                                                    <option value="Member">Member</option>
                                                </select>
                                            </div>
                                        </form>
                                        <div class="col-sm-12">
                                            <button id="searchActivityBtn" type="submit" class="btn btn-primary waves-effect waves-light">Search</button>
                                            <button type="submit" id="resetBtn" class="btn btn-default waves-effect waves-light">Reset</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>

                    <div class="row">
                        <div class="modal fade" id="dataMessage" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <button type="button" class="close" data-dismiss="modal">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                        <h4 class="modal-title">
                                        </h4>
                                    </div>
                                    <div class="modal-body">
                                        <div id="dataAlertMessage" class="alert">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-primary waves-effect waves-light" data-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <form>
                                    <div id="basicwizard" class="pull-in">
                                        <div class="tab-content b-0 m-b-0 p-t-0">
                                            <div id="alertMsg" class="alert" style="display: none;"></div>
                                            <div id="activityListDiv" class="table-responsive"></div>
                                            <span id="paginateText"></span>
                                            <div class="text-center">
                                                <ul class="pagination pagination-md" id="pagerActivityList"></ul>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include("footer.php"); ?>
        </div>
    </div>
    <script>
        var resizefunc = [];
    </script>
    <?php include("shareJs.php"); ?>
    <script>
        var offsetSecs = getOffsetSecs();
        // Initialize all the id in this page
        var divId    = 'activityListDiv';
        var tableId  = 'activityListTable';
        var pagerId  = 'pagerActivityList';
        var btnArray = Array();
        var thArray  = Array(
                                'ID',
                                'Title',
                                'Description',
                                'Username',
                                'Creator Type',
                                'Created At'
                            );
            
        // Initialize the arguments for ajaxSend function
        var url            = 'scripts/reqActivity.php';
        var method         = 'POST';
        var debug          = 1;
        var bypassBlocking = 0;
        var bypassLoading  = 0;
        var pageNumber     = 1;
        var fCallback      = loadDefaultListing;
        var activityData   = [];
        var activityDate;

        $(document).ready(function() {
            setTodayDatePicker();
            var activityTimeFrom = '';
            var activityTimeTo   = '';
            var activityDateVal  = setTodayDatePicker();
            
            if(activityDateVal) {
                activityDate = dateToTimestamp(activityDateVal);
                if($('#timeFrom').val())
                    activityTimeFrom = dateToTimestamp(activityDateVal+ ' ' +$('#timeFrom').val());
                
                if($('#timeTo').val())
                    activityTimeTo = dateToTimestamp(activityDateVal+ ' ' +$('#timeTo').val());
            }
            
            var formData = {
                command          : 'getActivity',
                pageNumber       : pageNumber
            };

            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            
            $('#searchActivityBtn').click(function() {
                pagingCallBack(pageNumber, loadSearch);
            });
            
            $('#resetBtn').click(function() {
                $('#alertMsg').removeClass('alert-success').html('').hide();
                $('#searchActivity').find('input').each(function() {
                   $(this).val(''); 
                });
                setTodayDatePicker();
            });
        });
        
        function loadDefaultListing(data, message) {
            var tableNo;
            buildTable(data.activityList, tableId, divId, thArray, btnArray, message, tableNo);
            pagination(pagerId, data.pageNumber, data.totalPage, data.totalRecord, data.numRecord);
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
            
            $('#activityDate').daterangepicker({
                singleDatePicker: true,
                timePicker: false,
                locale: {
                    format: 'DD/MM/YYYY'
                }
            });
            $('#activityDate').val('');
            
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

        function pagingCallBack(pageNumber, fCallback){

            var searchId   = 'searchActivity';
            var searchData = buildSearchDataByType(searchId);
            
            if(pageNumber > 1) bypassLoading = 1;
            
            var formData = {
                command          : "getActivity",
                searchData       : searchData,
                pageNumber       : pageNumber
            };
            
            if(!fCallback)
                fCallback = loadDefaultListing;

            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }

        function loadSearch(data, message) {
            loadDefaultListing(data, message);
            $('#searchMsg').addClass('alert-success').html('<span>Search successful.</span>').show();
            setTimeout(function() {
                $('#searchMsg').removeClass('alert-success').html('').hide(); 
            }, 3000);
        }
    </script>
</html>