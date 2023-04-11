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
                    <div class="col-sm-4">
                         <a href="permission.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <h4 class="header-title m-t-0 m-b-30">Permission</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <form role="form" id="newPermission" data-parsley-validate novalidate>
                                            <div class="form-group">
                                                <label for="">Name*</label>
                                                <input id="name" type="text" class="form-control"  required>
                                            </div>
                                            <div class="form-group">
                                                <label for="">Type*</label>
                                                <input id="type" type="text" class="form-control"  required>
                                            </div>
                                            <div class="form-group">
                                                <label for="">Description</label>
                                                <textarea rows="4" cols="50" class="form-control" id="description" name="description"></textarea>
                                            </div>
                                            <!-- <div class="form-group">
                                                <label for="">Description</label>
                                                <textarea id="description" class="form-control" maxlength="225" rows="2" placeholder="Description.."></textarea>
                                            </div> -->
                                            <div class="form-group">
                                                <label class="control-label">Parent</label>
                                                <select id="parent" class="form-control">
                                                    <option>--Please Select--</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="">File Path*</label>
                                                <input id="filePath" type="text" class="form-control"  required>
                                            </div>
                                            <div class="form-group">
                                                <label class="control-label">Priority*</label>
                                                <select id="priority" class="form-control"  required>
                                                    <option value="">--Please Select--</option>
                                                    <option value="1">1</option>
                                                    <option value="2">2</option>
                                                    <option value="3">3</option>
                                                    <option value="4">4</option>
                                                    <option value="5">5</option>
                                                    <option value="6">6</option>
                                                    <option value="7">7</option>
                                                    <option value="8">8</option>
                                                    <option value="9">9</option>
                                                    <option value="10">10</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="">Icon Class name</label>
                                                <input id="iconClass" type="text" class="form-control">
                                            </div>
                                            <div class="form-group">
                                                <label for="">Disabled</label>
                                                <div class="m-b-20" id="status">
                                                    <div class="radio radio-info radio-inline">
                                                        <input type="radio" id="disabled" value="0" name="disabled" checked="">
                                                        <label for="inlineRadio1"> On </label>
                                                    </div>
                                                    <div class="radio radio-inline">
                                                        <input type="radio" id="disabled" value="1" name="disabled" checked="">
                                                        <label for="inlineRadio2"> Off </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 m-b-20">
                            <button id="addNewPermission" type="submit" class="btn btn-primary waves-effect waves-light">Confirm</button>
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
    // Initialize the arguments for ajaxSend function
    var url            = 'scripts/reqPermission.php';
    var method         = 'POST';
    var debug          = 0;
    var bypassBlocking = 0;
    var bypassLoading  = 0;

    var fCallback      = loadFormDropdown;

    $(document).ready(function() {
        var formData = {
            command : "getPermissionTree"
        };

        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);

        $('#addNewPermission').click(function() {
            var validate = $('#newPermission').parsley().validate();
            if(validate) {
                showCanvas();
                var name        = $('#name').val();
                var type        = $('#type').val();
                var description = $('#description').val();
                var parent      = $('#parent').find('option:selected').val();
                var filePath    = $('#filePath').val();
                var priority    = $('#priority').find('option:selected').val();
                var iconClass   = $('#iconClass').val();
                var disabled    = $('#disabled').val();

                if(parent == '--Please Select--'){
                    parent = 0;
                }
                
                var formData = {command : "newPermission", "name" : name, "type" : type, "description" : description, "parent" : parent, "filePath" : filePath, "priority" : priority, "iconClass" : iconClass, "disabled" : disabled};
                
                var fCallback = sendNew;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }
        });

        showCanvas();
        $.ajax({
            type: 'POST',
            url: 'scripts/reqPermission.php',
            data: {"command" : "getPermissionTree"},
            dataType: 'text',
            encode: true
        }).done(function(data) {
            hideCanvas();
            var obj = JSON.parse(data);
            if (obj.status == 'ok') {
                permissionName = obj.data.permissionTree;
                $.each(permissionName['name'], function(key, val) {
                    if(typeof permissionName.id !== "undefined" && typeof permissionName.name !== "undefined") {
                        var permissionID = permissionName['id'][key];
                        $('#parent').append('<option value="' + permissionID + '">' + val + '</option>');
                    }
                });
            } else {
                errorHandler(obj.code, obj.statusMsg);
            }
        });
    });

    function loadFormDropdown(data, message) {
        permissionName = data.permissionTree;
        $.each(permissionName['name'], function(key, val) {
            if(typeof permissionName.id !== "undefined" && typeof permissionName.name !== "undefined") {
                var permissionID = permissionName['id'][key];
                $('#parent').append('<option value="' + permissionID + '">' + val + '</option>');
            }
        });
    }

    function sendNew(data, message) {
        showMessage('Permission successfully created.', 'success', 'Add New Permission', 'Permission', 'permission.php');
    }
</script>
</html>