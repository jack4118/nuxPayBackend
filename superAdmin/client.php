<?php 
    session_start();

    // Get current page name
    $thisPage = basename($_SERVER['PHP_SELF']);

    // Check the session for this page
    if(!isset ($_SESSION['access'][$thisPage]))
        echo '<script>window.location.href="accessDenied.php";</script>';
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
                                            <form id="searchClient" role="form">
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="username">Username</label>
                                                    <input type="text" class="form-control" dataName="username" dataType="text">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="name">Name</label>
                                                    <input type="text" class="form-control" dateName="name" dataType="text">
                                                </div>
                                                <!-- <div class="col-sm-4 form-group hidden">
                                                    <label class="control-label" for="" data-th="type">Type</label>
                                                    <input type="text" class="form-control" value="Member">
                                                </div> -->
                                                <!-- <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="email">Email</label>
                                                    <input type="text" class="form-control">
                                                </div> -->
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" data-th="country_id">Country</label>
                                                    <select id="countryName" class="form-control" dataName="country_id" dataType="text">
                                                        <option value="">All</option>
                                                    </select>
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="sponsor_username">Sponsor Username</label>
                                                    <input type="text" class="form-control" dataName="sponsor" dataType="text">
                                                </div>
                                                <!-- <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="placement_username">Placement Name</label>
                                                    <input type="text" class="form-control">
                                                </div> -->
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" data-th="disabled">Disabled</label>
                                                    <select class="form-control" dataName="disabled" dataType="text">
                                                        <option value="">All</option>
                                                        <option value="1">Yes</option>
                                                        <option value="0">No</option>
                                                    </select>
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" data-th="suspended">Suspended</label>
                                                    <select class="form-control" dataName="suspended" dataType="text">
                                                        <option value="">All</option>
                                                        <option value="1">Yes</option>
                                                        <option value="0">No</option>
                                                    </select>
                                                </div>
                                                <!-- <div class="col-sm-4 form-group">
                                                    <label class="control-label" data-th="deleted">Deleted</label>
                                                    <select class="form-control">
                                                        <option value="">All</option>
                                                        <option value="0">Ok</option>
                                                        <option value="1">Deleted</option>
                                                    </select>
                                                </div> -->
                                                <!-- <div class="col-sm-4 form-group">
                                                    <label class="control-label" data-th="">Last Login</label>
                                                    <input id="lastLoginDate" type="text" class="form-control">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" data-th="">Last Activity</label>
                                                    <input id="lastActivityDate" type="text" class="form-control">
                                                </div> -->
                                            </form>
                                            <div class="col-sm-12">
                                                <button id="searchClientBtn" type="submit" class="btn btn-primary waves-effect waves-light">Search</button>
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
                                <form>
                                    <div id="basicwizard" class="pull-in">
                                        <div class="tab-content b-0 m-b-0 p-t-0">
                                            <div id="alertMsg" class="text-center alert" style="display: none;"></div>
                                            <div id="clientListDiv" class="table-responsive"></div>
                                            <span id="paginateText"></span>
                                            <div class="text-center">
                                                <ul class="pagination pagination-md" id="pagerClientList"></ul>
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
    var divId = 'clientListDiv';
    var tableId = 'clientListTable';
    var pagerId = 'pagerClientList';
    var btnArray = Array('edit');
    var thArray = Array('ID',
                        'Username',
                        'Name',
                        'Sponsor Username',
                        'Country',
                        'Disabled',
                        'Suspended',
                        'Freezed',
                        'Last login',
                        'Created At'
                       );
    
    // Timestamps for lastLogin lastActivity
    var tsLoginFrom, tsLoginTo, tsActivityFrom, tsActivityTo;
        
    // Initialize the arguments for ajaxSend function
    var url = 'scripts/reqCountries.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;
    var pageNumber = 1;
        
    var fCallback = loadCountryDropdown;
    var formData = {command: 'getCountriesList', pagination: 'No'};
    $(document).ready(function() {
        // First load of this page
        // setDateRange();
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        url = 'scripts/reqClient.php';
        formData = {
            command : "getClients"
        };
        fCallback = loadDefaultListing;
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            
        $('#resetBtn').click(function() {
            $('#alertMsg').removeClass('alert-success').html('').hide();
            $('#searchClient').find('input').each(function() {
               $(this).val(''); 
            });
            $('#searchClient').find('select').each(function() {
                $(this).val('');
            });
            // setDateRange();
        });
        
        $('#searchClientBtn').click(function() {
            pagingCallBack(pageNumber, loadSearch);
        });
    });
    
    function setDateRange() {
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
        var today = dd+'-'+mm+'-'+yyyy;
        
        $('#lastLoginDate').daterangepicker({
            timePicker: true,
            timePickerIncrement: 30,
            autoclose: true,
            orientation: 'top auto',
            defaultViewDate: 'today',
            locale: {
                format: 'DD-MM-YYYY h:mm A'
            },
            startDate: '01-01-2017',
            endDate: today
        });
        
        $('#lastActivityDate').daterangepicker({
            timePicker: true,
            timePickerIncrement: 30,
            autoclose: true,
            orientation: 'top auto',
            defaultViewDate: 'today',
            locale: {
                format: 'DD-MM-YYYY h:mm A'
            },
            startDate: '01-01-2017',
            endDate: today
        });
        updateDateRange();
    }
    
    function updateDateRange() {
        var res, dateRange, datePart, fromDate, toDate, fromDateTime, toDateTime;
        dateRange = $('#lastLoginDate').val();
        res = dateRange.split(' ');
        
        datePart = res[0].split('-');
        fromDate = datePart[1]+'/'+datePart[0]+'/'+datePart[2];
        datePart = res[4].split('-');
        toDate = datePart[1]+'/'+datePart[0]+'/'+datePart[2];
        
        fromDateTime = fromDate+' '+res[1]+' '+res[2];
        toDateTime = toDate+' '+res[5]+' '+res[6];
        tsLoginFrom = Date.parse(fromDateTime)/1000;
        tsLoginTo = Date.parse(toDateTime)/1000;
        
        dateRange = $('#lastActivityDate').val();
        res = dateRange.split(' ');
        
        datePart = res[0].split('-');
        fromDate = datePart[1]+'/'+datePart[0]+'/'+datePart[2];
        datePart = res[4].split('-');
        toDate = datePart[1]+'/'+datePart[0]+'/'+datePart[2];
        
        fromDateTime = fromDate+' '+res[1]+' '+res[2];
        toDateTime = toDate+' '+res[5]+' '+res[6];
        tsActivityFrom = Date.parse(fromDateTime)/1000;
        tsActivityTo = Date.parse(toDateTime)/1000;
    }
    
    function loadCountryDropdown(data, message) {
        if(typeof(data.countriesList) != 'undefined') {
            var countryData = data.countriesList;
            $.each(countryData['Name'], function(key, val) {
                var countryID = countryData['ID'][key];
                var country = countryData['Name'][key];
                $('#countryName').append('<option value="' + countryID + '">' + country + '</option>');
            });
        }
    }
    
    function loadDefaultListing(data, message) {
        var tableNo;
        buildTable(data.clientList, tableId, divId, thArray, btnArray, message, tableNo);
        pagination(pagerId, data.pageNumber, data.totalPage, data.totalRecord, data.numRecord);
    }
    
    function loadSearch(data, message) {
        loadDefaultListing(data, message);
        $('#searchMsg').addClass('alert-success').html('<span>Search successful.</span>').show();
        setTimeout(function() {
            $('#searchMsg').removeClass('alert-success').html('').hide(); 
        }, 3000);
    }
    
    function tableBtnClick(btnId) {
        var btnName = $('#'+btnId).attr('id').replace(/\d+/g, '');
        var tableRow = $('#'+btnId).parent('td').parent('tr');
        var tableId = $('#'+btnId).closest('table');
        
        if (btnName == 'edit') {
            var editId = tableRow.attr('data-th');
            var editUrl = 'clientDetails.php?id='+editId;
            window.location = editUrl;
        }
    }

    function pagingCallBack(pageNumber, fCallback){
        var searchId = 'searchClient';
        var searchData = buildSearchDataByType(searchId);
        // updateDateRange();
        if(pageNumber > 1) bypassLoading = 1;
            
        var formData = {
            command : "getClients",
            inputData : searchData,
            // tsLoginFrom : tsLoginFrom,
            // tsLoginTo : tsLoginTo,
            // tsActivityFrom : tsActivityFrom,
            // tsActivityTo : tsActivityTo,
            pageNumber : pageNumber
        };
        if(!fCallback)
            fCallback = loadDefaultListing;
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
    }
</script>
</body>
</html>
