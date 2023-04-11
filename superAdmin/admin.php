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
                                    <div id="collapseOne" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingOne">
                                        <div class="panel-body">
                                            <div id="searchMsg" class="text-center alert" style="display: none;"></div>
                                            <form id="searchForm" role="form">
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="name">Name</label>
                                                    <input type="text" class="form-control" dataName="name" dataType="text">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="username">Username</label>
                                                    <input type="text" class="form-control" dataName="username" dataType="text">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="email">Email</label>
                                                    <input type="text" class="form-control" dataName="email" dataType="text">
                                                </div>
                                                <!-- <div class="col-sm-4 form-group">
                                                    <label class="control-label" data-th="role_id">Role Name</label>
                                                    <select id="roleName" class="form-control">
                                                    </select>
                                                </div> -->
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" data-th="disabled">Disabled</label>
                                                    <select class="form-control" dataName="disabled" dataType="text">
                                                        <option value="">All</option>
                                                        <option value="1">Yes</option>
                                                        <option value="0">No</option>
                                                    </select>
                                                </div>
                                                <!-- <div class="col-sm-4 form-group">
                                                    <label class="control-label" data-th="">Created At</label>
                                                    <input id="createdAt" type="text" class="form-control dateRangePicker">
                                                </div> -->
                                            </form>
                                            <!-- hidden -->
                                           <!--  <form id="adminType" role="form" style="display:none;">
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="name">Admin Type</label>
                                                    <input type="text" class="form-control" value="Admin">
                                                </div>
                                            </form> -->

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


                                <a href="newAdmin.php" type="button" class="btn btn-primary waves-effect w-md waves-light m-b-20">New Admin</a>


                                <form>
                                    <div id="basicwizard" class="pull-in">
                                        <div class="tab-content b-0 m-b-0 p-t-0">
                                            <div id="alertMsg" class="text-center alert" style="display: none;"></div>
                                            <div id="userListDiv" class="table-responsive"></div>
                                            <span id="paginateText"></span>
                                            <div class="text-center">
                                                <ul class="pagination pagination-md" id="pagerUserList"></ul>
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
    var divId = 'userListDiv';
    var tableId = 'userListTable';
    var pagerId = 'pagerUserList';
    var btnArray = Array('edit');
    var thArray = Array('ID',
                        'Username',
                        'Name',
                        'Email',
                        'Role Name',
                        'Disabled',
                        'Created At',
                        'Last login'
                       );
    var searchId = 'searchForm';
        
    // Initialize the arguments for ajaxSend function
    var url = 'scripts/reqAdmin.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 1;
    var bypassLoading = 0;
    var pageNumber = 1;
    var formData = "";
    var searchData = buildSearchData("adminType");
        
    var fCallback = "";
    $(document).ready(function() {
        setDateRange();

        fCallback = loadSearchDropdown;
        formData = {command: 'getRoles', inputData: searchData, getActiveRoles: "getActiveRoles"};
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);

        fCallback = loadDefaultListing;
        formData = {command: 'getAdmins', inputData: buildSearchData(searchId)};
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        
        //reset to default search
        $('#resetBtn').click(function() {
            $('#alertMsg').removeClass('alert-success').html('').hide();
            $("#searchForm")[0].reset();

        });
        
        $('#searchBtn').click(function() {
            updateDateRange();
            pagingCallBack(pageNumber, loadSearch);
        }); 
    });

    
    function setDateRange() {
        var today = new Date();
        var dd = today.getDate()+1;
        var mm = today.getMonth()+1;
        var yyyy = today.getFullYear();
        if(dd<10){
            dd='0'+dd;
        } 
        if(mm<10){
            mm='0'+mm;
        }
        var tmrDay = dd+'-'+mm+'-'+yyyy;
        
        $('#createdAt').daterangepicker({
            timePicker: true,
            timePickerIncrement: 30,
            autoclose: true,
            orientation: 'top auto',
            // defaultViewDate: 'today',
            locale: {
                format: 'DD-MM-YYYY h:mm A'
            },
            startDate: '01-01-2017',
            endDate: tmrDay
        });

        updateDateRange();
    }
    var mySearch = [];
    var tsFromTo = [];
        function updateDateRange() {
        var res, dateRange, datePart, fromDate, toDate, fromDateTime, toDateTime;
        mySearch = [];

        if($('#createdAt').val()){
            dateRange = $('#createdAt').val();
            res = dateRange.split(' ');
            tsFromTo = [];
            datePart = res[0].split('-');
            fromDate = datePart[1]+'/'+datePart[0]+'/'+datePart[2];
            datePart = res[4].split('-');
            toDate = datePart[1]+'/'+datePart[0]+'/'+datePart[2];
            
            fromDateTime = fromDate+' '+res[1]+' '+res[2];
            toDateTime = toDate+' '+res[5]+' '+res[6];
            tsFrom = Date.parse(fromDateTime)/1000;
            tsTo = Date.parse(toDateTime)/1000;
            // tsFromTo.push({startTs: tsFrom, endTs: tsTo});
            mySearch.push({created_at : {startTs: tsFrom, endTs: tsTo}});

        }

    }

    function pagingCallBack(pageNumber, fCallback){
            var searchData = buildSearchDataByType(searchId);
            if(pageNumber > 1) bypassLoading = 1;
            
            var formData = {
                command : "getAdmins",
                inputData: searchData,
                searchDate : mySearch,
                pageNumber: pageNumber
            };
            if(!fCallback)
                fCallback = loadDefaultListing;
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
    }
    
    function loadSearchDropdown(data, message) {
        if(typeof(data.roleList) != 'undefined') {
            roleData = data.roleList;
            $.each(roleData['Name'], function(key, val) {
                var roleID = roleData['ID'][key];
                $('#roleName').append('<option value="' + roleID + '">' + val + '</option>');
            });
        }
    }
    
    function loadDefaultListing(data, message) {
        var tableNo;
        buildTable(data.adminList, tableId, divId, thArray, btnArray, message, tableNo);
        pagination(pagerId, data.pageNumber, data.totalPage, data.totalRecord, data.numRecord);
    }
    
    function tableBtnClick(btnId) {
        var btnName = $('#'+btnId).attr('id').replace(/\d+/g, '');
        var tableRow = $('#'+btnId).parent('td').parent('tr');
        var tableId = $('#'+btnId).closest('table');
        
        if (btnName == 'edit') {
            var editId = tableRow.attr('data-th');
            var editUrl = 'editAdmin.php?id='+editId;
            window.location = editUrl;
        }
        else if (btnName == 'delete') {
            var canvasBtnArray = Array('Ok');
            var message = 'Are you sure you want to delete this admin?';
            showMessage(message, '', 'Delete admin', 'trash', '', canvasBtnArray);
            $('#canvasOkBtn').click(function() {
                var id = tableRow.attr('data-th');
                console.log("del id : " + id);
                var formData = {
                    'command': 'deleteAdmin',
                    'id' : id
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
        $('#alertMsg').addClass('alert-success').html("<span>Delete successful.</span>").show();
        setTimeout(function() {
            $('#alertMsg').removeClass('alert-success').html('').hide(); 
        }, 3000);
    }
   
</script>
</body>
</html>
