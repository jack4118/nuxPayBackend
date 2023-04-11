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
                                    <div id="collapseOne" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingOne" aria-expanded="true">
                                        <div class="panel-body">
                                        <div id="searchMsg" class="text-center alert" style="display: none;"></div>
                                            <form id="searchMessageSent" role="form">
                                                <div class="col-sm-4 form-group">
                                                    <label for="">Date</label>
                                                    <input id="tblDate" type="text" class="form-control" dataName="tblDate" dataType="singleDate">
                                                </div>

                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label">Created At</label>
                                                    <div class="input-group">
                                                        <div>
                                                            <input id="timeFrom" type="text" class="form-control" dataName="tblTime" dataType="timeRange" dataParent="tblDate">
                                                        </div>
                                                        <span class="input-group-addon">-</span>
                                                        <div>
                                                            <input id="timeTo" type="text" class="form-control" dataName="tblTime" dataType="timeRange" dataParent="tblDate">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="recipient ">Recepient</label>
                                                    <input type="text" class="form-control" dataName="recipient" dataType="text">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="type">Type</label>
                                                    <input type="text" class="form-control" dataName="type" dataType="text">
                                                </div>
                                               
                                                <!--  <div class="col-sm-4 form-group">
                                                <label class="control-label" for="" data-th="status">date</label>
                                                <select id="tblDate" class="form-control">
                                                    <option value="2017-08-10">2017-08-10</option>
                                                    <option value="2017-08-14">2017-08-14</option>
                                                </select>
                                                </div> -->
                                        </form>
                                        <div class="col-sm-12">
                                            <button id="searchMessageSentBtn" type="button" class="btn btn-primary waves-effect waves-light">Search</button>
                                            <button id="resetMessageSent" type="button" class="btn btn-default waves-effect waves-light">Reset</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- pop out msg -->
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
                    <!-- end of pop out msg -->
                    <div class="col-lg-12">
                        <div class="card-box p-b-0">
                            <!-- <a href="newLanguage.php" type="button" class="btn btn-primary waves-effect w-md waves-light m-b-20">New Language</a> -->
                            <form>
                                <div id="basicwizard" class="pull-in">
                                    <div class="tab-content b-0 m-b-0 p-t-0">
                                        <div id="alertMsg" class="text-center alert" style="display: none;"></div>
                                        <div id="messageSentListDiv" class="table-responsive"></div>
                                        <span id="paginateText"></span>
                                        <div class="text-center">
                                            <ul class="pagination pagination-md" id="pagerMessageSentList"></ul>
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
        var divId = 'messageSentListDiv';
        var tableId = 'messageSentListTable';
        var pagerId = 'pagerMessageSentList';
        var btnArray = Array();
        var thArray = Array('ID',
                            'Recipient',
                            'Type',
                            'Content',
                            'Subject',
                            'Sent At',
                            'Scheduled At',
                            'Processor',
                            'Error Count'
                           );

        // Initialize the arguments for ajaxSend function
        var url = 'scripts/reqMessage.php';
        var method = 'POST';
        var debug = 0;
        var bypassBlocking = 0;
        var bypassLoading = 0;
        var pageNumber = 1;

        //get selected option
        // var e = document.getElementById("tblDate");
        // var tblDate = e.options[e.selectedIndex].value;

        var fCallback = loadDefaultListing;
        $(document).ready(function() {
            var tblDate = setTodayDatePicker();
            setTimePicker();
            
            var messageDate = dateToTimestamp(tblDate);
            
            var timeFrom = $('#timeFrom').val();
            var timeTo = $('#timeTo').val();

            var formData = {command: 'getMessageSentList', tblDate: messageDate, timeFrom: timeFrom, timeTo: timeTo, pageNumber : pageNumber};

            // First load of this page
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);

            // Search function
            $('#searchMessageSentBtn').click(function() {
                pagingCallBack(pageNumber, loadSearch);
            });

            //Reset he API search from
            $('#resetMessageSent').click(function() {
                $("#searchMessageSent")[0].reset();
                setTodayDatePicker();
                setTimePicker();

            });
        });

        function pagingCallBack(pageNumber, fCallback){
            var searchId = 'searchMessageSent';
            var searchData = buildSearchDataByType(searchId);
            
            if(pageNumber > 1) bypassLoading = 1;

            var formData = {
                command : "getMessageSentList",
                searchData: searchData,
                pageNumber: pageNumber
            };
            if(!fCallback)
                fCallback = loadDefaultListing;
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }

        function loadDefaultListing(data, message) {
            var tableNo;
            buildTable(data.msgSentList, tableId, divId, thArray, btnArray, message, tableNo);
            pagination(pagerId, data.pageNumber, data.totalPage, data.totalRecord, data.numRecord);
            if(data.msgSentList) {
                $.each(data.hiddenData, function(key, val) {
                    $.each(val, function(key, val) {
                        if(key == "Content") storeContent(val);
                    });
                });

                // Append clickable icons to td columns for hidden datas
                $('#'+divId).find('tr').each(function() {
                    var tr = $(this);
                    var trID = tr.attr('id');
                    var tdDataInID = 'content'+trID;

                    tr.find('td:eq(3)').addClass('text-center').text('').append('<i class="fa fa-file-text fa-2"></i>');
                    tr.find('td:eq(3) i').attr('id', tdDataInID);
                    tr.find('td:eq(3) i').addClass('cursorPointer');
                    tr.find('td:eq(3) i').on('click tap', function() {
                        showData(this.id);
                    });
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

    function showData(id) {
        var trID = $('#'+id).closest('tr').attr('id');
        var tdName = $('#'+id).attr('id').replace(/\d+/g, '');
        var dataID = $('#'+trID).find('td:eq(0)').text();
        
        var message;
        if (tdName == 'content') {
            title = "Content for ID(" + dataID + ")";
            message = content[trID];
        }
        
        $('#dataMessage').modal('toggle');
        $('#dataMessage').find('h4').html('<i class="fa fa-3x fa-file"></i><span>'+title+'</span>')
        $('#dataAlertMessage').html('<span>'+message+'</span>');
        $('#dataMessage').on('hidden.bs.modal', function() {
            $('#dataAlertMessage').empty();
        });
    }

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
        
        $('#tblDate').daterangepicker({
            singleDatePicker: true,
            timePicker: false,
            locale: {
                format: 'DD/MM/YYYY'
            }
        });
        $('#tblDate').val('');
        return today;
    }

    function setTimePicker() {
        $('#timeFrom').timepicker({
            defaultTime : ''
        });
        $('#timeTo').timepicker({
            defaultTime : ''
        });
    }

    var content = [];
    function storeContent(data){
        content.push(data);
    }
    </script>
</html>