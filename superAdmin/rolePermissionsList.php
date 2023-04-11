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
                            <div class="card-box p-b-0">
                                <!-- <a href="newRolePermission.php" title="New Role Permission" type="button" class="btn btn-primary waves-effect w-md waves-light m-b-20">New Role Permission</a> -->
                                <form>
                                    <div id="basicwizard" class="pull-in">
                                        <div class="tab-content b-0 m-b-0 p-t-0">
                                            <div id="rolePermissionMsg" class="alert" style="display: none;"></div>
                                            <div id="rolePermissionDiv" class="table-responsive"></div>
                                            <div class="text-center">
                                                <ul class="pagination pagination-md" id="rolePermissionPager"></ul>
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
    </div>
    <!-- END wrapper -->
    <script>
        var resizefunc = [];
    </script>

    <!-- jQuery  -->
    <?php include("shareJs.php"); ?>
    <script>
        $(document).ready(function() {
            showCanvas();
            $.ajax({
                type: 'POST',
                url: 'scripts/reqUsers.php',
                data: {'command' : "getRoles"},
                dataType: 'text',
                encode: true
            }).done(function(data) {
                var obj = JSON.parse(data);
                hideCanvas();
                if(obj.status == 'ok'){
                    var divId = 'rolePermissionDiv';
                    var tableId = 'rolePermissionTable';
                    
                    var btnArray = Array('edit', 'delete');
                    var thArray = Array('id');
                    
                    buildTable(obj.data.roleList, tableId, divId, thArray, btnArray);
                    var pagerId = 'rolePermissionPager';
                    paginateTable(tableId, pagerId, true, true, <?php echo $_SESSION["pagingCount"];?>);
                } else {
                    errorHandler(obj.code, obj.statusMsg);
                }
            });

            $('#rolePermissionSearch').click(function() {
                var searchId = 'rolePermissionHistory';
                var searchData = buildSearchData(searchId);
                
                var formData = {
                    command : "rolePermissionHistory",
                    inputData: searchData
                };
                showCanvas();
                $.ajax({
                    type: 'POST',
                    url: 'scripts/reqPermission.php',
                    data: formData,
                    dataType: 'text',
                    encode: true
                }).done(function(data) {
                    var obj = JSON.parse(data);
                    hideCanvas();
                    if(obj.status == 'ok'){
                        var divId = 'rolePermissionDiv';
                        var tableId = 'rolePermissionTable';
                        var btnArray = Array('edit','delete');
                        var thArray = Array('id');

                        $('#'+divId).find('table#'+tableId).remove();
                        buildTable(obj.data, tableId, divId, thArray, btnArray);
                        var pagerId = 'rolePermissionPager';
                        $('#'+pagerId).find('li').remove();
                        paginateTable(tableId, pagerId, true, true, <?php echo $_SESSION["pagingCount"];?>);
                    } else {
                        errorHandler(obj.code, obj.statusMsg);
                    }
                });
            });
        });

        function tableBtnClick(btnId) {
            var btnName = $('#'+btnId).attr('id').replace(/\d+/g, '');
            var tableRow = $('#'+btnId).parent('td').parent('tr');
            var tableId = $('#'+btnId).closest('table');

            if (btnName == 'edit') {
                var editId = tableRow.find('td:first-child').text();
                var editUrl = 'editRolePermission.php?id='+editId;
                window.location = editUrl;
            } else if (btnName == 'delete') {
                var userId = tableRow.find('td:first-child').text();
                var canvasBtnArray = Array('Ok');
                var message = 'Are you sure you want to delete this Role Permission.';
                showMessage(message, '', 'Delete Api Role Permission', 'trash', '', canvasBtnArray);

                $('#canvasOkBtn').click(function() {
                    showCanvas(); 
                    var tableColVal = tableRow.find('td:first-child').text();
                    var formData = {
                        'command': 'deleteRolePermission',
                        'deleteData' : tableColVal
                    };
                    $.ajax({
                        type: 'POST',
                        url: 'scripts/reqPermission.php',
                        data: formData,
                        dataType: 'text',
                        encode: true
                    }).done(function(data) {
                        hideCanvas();
                        var obj = JSON.parse(data);
                        if (obj.status == 'ok') {
                            var divId = 'rolePermissionDiv';
                            var tableId = 'rolePermissionTable';

                            var btnArray = Array('edit', 'delete');
                            var thArray = Array('id');

                            $('#'+divId).find('table#'+tableId).remove();
                            buildTable(obj.data, tableId, divId, thArray, btnArray);
                            var pagerId = 'rolePermissionList';
                            $('#'+pagerId).find('li').remove();
                            paginateTable(tableId, pagerId, true, true, <?php echo $_SESSION["pagingCount"];?>);
                            $('#rolePermissionMsg').addClass('alert-success').html("Role Permission deleted successfully.").show();
                            setTimeout(function() {
                                $('#rolePermissionMsg').removeClass('alert-success').html("").hide();
                            }, 5000);
                        } else {
                            $('#rolePermissionMsg').addClass('alert-danger').html("Failed to delete.").show();
                            setTimeout(function() {
                                $('#rolePermissionMsg').removeClass('alert-danger').html("").hide();
                            }, 5000);
                        }
                    });
                });
            }
        }
    </script>
</html>