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
                                        <form id="searchWebservices" role="form">
                                            <div class="col-sm-4 form-group">
                                                <label for="">Date</label>
                                                <input id="webserviceDate" type="text" class="form-control" dataName="webserviceDate" dataType="singleDate">
                                            </div>

                                            <div class="col-sm-4 form-group">
                                                <label class="control-label">Created At</label>
                                                <div class="input-group">
                                                    <!-- <div> -->
                                                        <input id="timeFrom" type="text" class="form-control" dataName="webserviceTime" dataType="timeRange" dataParent="webserviceDate">
                                                    <!-- </div> -->
                                                    <span class="input-group-addon">-</span>
                                                    <!-- <div> -->
                                                        <input id="timeTo" type="text" class="form-control" dataName="webserviceTime" dataType="timeRange" dataParent="webserviceDate">
                                                    <!-- </div> -->
                                                </div>
                                            </div>

                                            <div class="hidden">
                                                <label class="control-label hidden" data-th="time"></label>
                                                <input id="wsTimeFrom" class="hidden">
                                                <input id="wsTimeTo" class="hidden">
                                            </div>
                                            <div class="col-sm-4 form-group">
                                                <label class="control-label" for="" data-th="client_username">Client Username</label>
                                                <input type="text" class="form-control" dataName="clientUsername" dataType="text">
                                            </div>
                                            <div class="col-sm-4 form-group">
                                                <label class="control-label" for="" data-th="command">Command</label>
                                                <select id="command" class="form-control" dataName="command" dataType="select">
                                                    <option value="">All</option>
                                                </select>
                                            </div>
                                            <div class="col-sm-4 form-group">
                                                <label class="control-label" for="" data-th="status">Status</label>
                                                <select class="form-control" dataName="status" dataType="select">
                                                    <option value="">All</option>
                                                    <option value="ok">Ok</option>
                                                    <option value="error">Error</option>
                                                </select>
                                            </div>
<!--
                                            <div class="col-sm-4 form-group">
                                                <label class="control-label">Source</label>
                                                <select class="form-control">
                                                    <option>All</option>
                                                    <option>Andriod</option>
                                                    <option>IOS</option>
                                                    <option>Symbian</option>
                                                    <option>Web</option>
                                                </select>
                                            </div>
-->                                         
                                        </form>
                                        <div class="col-sm-12">
                                            <button id="searchWebservicesBtn" type="submit" class="btn btn-primary waves-effect waves-light">Search</button>
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
                                            <div id="webservicesListDiv" class="table-responsive"></div>
                                            <span id="paginateText"></span>
                                            <div class="text-center">
                                                <ul class="pagination pagination-md" id="pagerWebservicesList"></ul>
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
    // Get the UTC offset in seconds
    var offsetSecs = getOffsetSecs();
    // Initialize all the id in this page
    var divId = 'webservicesListDiv';
    var tableId = 'webservicesListTable';
    var pagerId = 'pagerWebservicesList';
    var btnArray = Array();
    var thArray = Array('ID',
                        'Created At',
                        'Completed At',
                        'Client Username',
                        'Command',
                        'Data In',
                        'Data Out',
                        'Source',
                        'Source Version',
                        'User Agent',
                        'Type',
                        'Site',
                        'Status',
                        'Duration',
                        'IP',
                        'No of queries'
                       );
        
    // Initialize the arguments for ajaxSend function
    var url = 'scripts/reqWebservices.php';
    var method = 'POST';
    var debug = 1;
    var bypassBlocking = 0;
    var bypassLoading = 0;
    var pageNumber = 1;
    var webserviceDate;
        
    var fCallback = loadDefaultListing;
    $(document).ready(function() {
        var webserviceTimeFrom = '';
        var webserviceTimeTo = '';
        var wsDateVal = setTodayDatePicker();
        
        if(wsDateVal) {
            webserviceDate = dateToTimestamp(wsDateVal);
            if($('#timeFrom').val())
                webserviceTimeFrom = dateToTimestamp(wsDateVal+ ' ' +$('#timeFrom').val());
            
            if($('#timeTo').val())
                webserviceTimeTo = dateToTimestamp(wsDateVal+ ' ' +$('#timeTo').val());
        }
        
        var formData = {
            command: 'getWebservices',
            pageNumber : pageNumber
        };
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        
        $('#searchWebservicesBtn').click(function() {
            pagingCallBack(pageNumber, loadSearch);
        });
        
        $('#resetBtn').click(function() {
            $('#alertMsg').removeClass('alert-success').html('').hide();
            $('#searchWebservices').find('input').each(function() {
               $(this).val(''); 
            });
            $('#searchWebservices').find('select').val('');
            setTodayDatePicker();
        });
    });
    
    function loadDefaultListing(data, message) {
        var tableNo;
        buildTable(data.webserviceList, tableId, divId, thArray, btnArray, message, tableNo);
        pagination(pagerId, data.pageNumber, data.totalPage, data.totalRecord, data.numRecord);
        if(typeof(data.webserviceList) != 'undefined') {
            var selectCommand = $('#searchWebservices #command').val();
            // Set the Command dropdown in Search section
            var searchCommandDropdown = data.commandList;
            setSearchCommand(searchCommandDropdown, selectCommand);

            dataIn = [];
            dataOut = [];
            userAgent = [];
            $.each(data.hiddenData, function(key, val) {
                $.each(val, function(key, val) {
                    if(key == 'dataIn')
                        storeDataIn(val);
                    else if(key == 'dataOut')
                        storeDataOut(val);
                    else if (key == 'userAgent')
                        storeUserAgent(val);
                });
            });
            
            // Append clickable icons to td columns for hidden datas
            $('#'+divId).find('tr').each(function() {
                var tr = $(this);
                var trID = tr.attr('id');
                var tdDataInID = 'dataIn'+trID;
                var tdDataOutID = 'dataOut'+trID;
                var tdUserAgentID = 'userAgent'+trID;
                
                tr.find('td:eq(5)').addClass('text-center').text('').append('<i class="fa fa-file-text fa-2"></i>');
                tr.find('td:eq(5) i').attr('id', tdDataInID);
                tr.find('td:eq(5) i').addClass('cursorPointer');
                tr.find('td:eq(5) i').on('click tap', function() {
                    showData(this.id);
                });
                
                tr.find('td:eq(6)').addClass('text-center').text('').append('<i class="fa fa-file-text fa-2"></i>');
                tr.find('td:eq(6) i').attr('id', tdDataOutID);
                tr.find('td:eq(6) i').addClass('cursorPointer');
                tr.find('td:eq(6) i').on('click tap', function() {
                    showData(this.id);
                });
                
                tr.find('td:eq(9)').addClass('text-center').text('').append('<i class="fa fa-file-text fa-2"></i>');
                tr.find('td:eq(9) i').attr('id', tdUserAgentID);
                tr.find('td:eq(9) i').addClass('cursorPointer');
                tr.find('td:eq(9) i').on('click tap', function() {
                    showData(this.id);
                });
            });
        }
    }
    
    // Build the hidden canvas for table data that is too huge
    function showData(id) {
        var trID = $('#'+id).closest('tr').attr('id');
        var tdName = $('#'+id).attr('id').replace(/\d+/g, '');
        var dataID = $('#'+trID).find('td:eq(0)').text();
        
        var message;
        if (tdName == 'dataIn') {
            title = "Data In for Webservices ID(" + dataID + ")";
            message = dataIn[trID];
        }
        else if (tdName == 'dataOut'){
            title = "Data Out for Webservices ID(" + dataID + ")";
            message = dataOut[trID];
        }
        else if (tdName == 'userAgent'){
            title = "User Agent for Webservices ID(" + dataID + ")";
            message = userAgent[trID];
        }
        
        $('#dataMessage').modal('toggle');
        $('#dataMessage').find('h4').html('<i class="fa fa-3x fa-file"></i><span>'+title+'</span>')
        $('#dataAlertMessage').html('<span>'+message+'</span>');
        $('#dataMessage').on('hidden.bs.modal', function() {
            $('#dataAlertMessage').empty();
        });
    }
    
    function loadSearch(data, message) {
        loadDefaultListing(data, message);
        $('#searchMsg').addClass('alert-success').html('<span>Search successful.</span>').show();
        setTimeout(function() {
            $('#searchMsg').removeClass('alert-success').html('').hide(); 
        }, 3000);
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
        
        $('#webserviceDate').daterangepicker({
            singleDatePicker: true,
            timePicker: false,
            locale: {
                format: 'DD/MM/YYYY'
            }
        });
        $('#webserviceDate').val('');
        
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
    
    // Set the command dropdown in the search part
    function setSearchCommand(data, searchVal) {
        $('#searchWebservices #command').find('option:not(:first-child)').remove();
        $.each(data, function(key, val) {
            $('#searchWebservices #command').append('<option value="' + val['command'] + '">' + val['command'] + '</option>');
        });
        if (searchVal != ' ')
            $('#searchWebservices #command').val(searchVal);
    }
    
    // Global variables for hidden data
    var dataIn = [];
    var dataOut = [];
    var userAgent = [];
    function storeDataIn(data) {
        dataIn.push(data);
    }
    function storeDataOut(data) {
        dataOut.push(data);
    }
    function storeUserAgent(data) {
        userAgent.push(data);
    }

    function pagingCallBack(pageNumber, fCallback){

        var searchId = 'searchWebservices';
        var searchData = buildSearchDataByType(searchId);
        
        if(pageNumber > 1) bypassLoading = 1;
        
        var formData = {
            command : "getWebservices",
            searchData: searchData,
            pageNumber : pageNumber
        };
        
        if(!fCallback)
            fCallback = loadDefaultListing;
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
    }
</script>
</body>
</html>