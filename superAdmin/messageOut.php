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
                                            <form id="searchMessageOut" role="form">
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="recipient ">Recepient</label>
                                                    <input type="text" class="form-control" >
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="type">Type</label>
                                                    <input type="text" class="form-control" >
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="created_at">Created at</label>
                                                    <input type="text" class="form-control" >
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="sent_at">Sent time</label>
                                                    <input type="text" class="form-control" >
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="content">Content</label>
                                                    <input type="text" class="form-control" >
                                                </div>
                                                <div class="col-sm-12">
                                                    <button id="searchMessageOutBtn" type="button" class="btn btn-primary waves-effect waves-light">Search</button>
                                                    <button id="resetMessageOut" type="button" class="btn btn-default waves-effect waves-light">Reset</button>
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
                            <!-- <a href="newLanguage.php" type="button" class="btn btn-primary waves-effect w-md waves-light m-b-20">New Language</a> -->
                            <form>
                                <div id="basicwizard" class="pull-in">
                                    <div class="tab-content b-0 m-b-0 p-t-0">
                                        <div id="messageOutMsg" class="text-center alert" style="display: none;"></div>
                                        <div id="messageOutListDiv" class="table-responsive"></div>
                                        <div class="text-center">
                                            <ul class="pagination pagination-md" id="pagerMessageOutList"></ul>
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
        var divId = 'messageOutListDiv';
        var tableId = 'messageOutListTable';
        var pagerId = 'pagerMessageOutList';
        var btnArray = Array();
        var thArray = Array('id');

        // Initialize the arguments for ajaxSend function
        var url = 'scripts/reqMessageOut.php';
        var method = 'POST';
        var debug = 0;
        var bypassBlocking = 0;
        var bypassLoading = 0;

        var fCallback = loadDefaultListing;
        var formData = {command: 'getMessageOutList'};
        $(document).ready(function() {
            // First load of this page
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);

            // Search function
            $('#searchMessageOutBtn').click(function() {
                var searchId = 'searchMessageOut';
                var searchData = buildSearchData(searchId);
                formData = {
                    command : "getMessageOutList",
                    inputData: searchData
                };
                fCallback = loadSearch;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            });

            //Reset he API search from
            $('#resetMessageOut').click(function() {
                $("#searchMessageOut")[0].reset();
            });
        });

        function loadDefaultListing(data, message) {
            console.log(data);
            if(typeof(data.messageOutList) != 'undefined') {
                $('#messageOutMsg').removeClass('alert-success').html('').hide();
                buildTable(data.messageOutList, tableId, divId, thArray, btnArray);
                paginateTable(tableId, pagerId, true, true, <?php echo $_SESSION["pagingCount"];?>);
            } else
                $('#messageOutMsg').addClass('alert-success').html('<span>'+message+'</span>').show();
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
            $('#'+divId).find('table#'+tableId).remove();
            $('#'+pagerId).find('li').remove();
            loadDefaultListing(data, message);
            $('#messageOutMsg').addClass('alert-success').html("<span>Messageout deleted successfully.</span>").show();
            setTimeout(function() {
                $('#messageOutMsg').removeClass('alert-success').html('').hide(); 
            }, 3000);
        }
    </script>
</html>