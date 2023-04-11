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
                         <a href="role.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <ul class="nav nav-tabs">
                                    <li><a id="roleInfo">Role Details</a></li>
                                    <li class="active"><a data-toggle="tab" href="#rolePermissions">Role Permissions</a></li>
                                </ul>
                                <div class="tab-content m-b-30">
                                    <div id="rolePermissions" class="tab-pane fade in active">
                                        <div class="row">
                                            <div class="col-md-10">
                                                <form role="form" id="editRolePermission">
                                                    <div class="row">
                                                        <div class="form-group form-horizontal">
                                                            <label class="col-md-2 control-label">Role Name :</label>
                                                            <div class="col-md-3">
                                                                <!-- <span class="col-md-3 control-label textAlign" id="roleName"></span> -->
                                                                <select id="roleName" class="form-control" name="roleName" disabled="disabled">
                                                                    <option>--Please Select--</option>
                                                                </select>
                                                                <input type="hidden" id="roleValue" value="" name="roleName">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">

                                                    <!-- <div class="form-group">
                                                        <label for="">Role Name</label>
                                                        <select id="roleName" class="form-control" name="roleName" disabled="disabled">
                                                            <option>--Please Select--</option>
                                                        </select>
                                                    </div> -->
                                                    <input type="hidden" class="form-control" value="editRolePermission" name="command">
                                                        <div class="form-group">
                                                            <div class="col-sm-offset-0 col-sm-5" id="permissionsList">
                                                                
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 m-b-20">
                            <button id="editNewPermission" type="submit" class="btn btn-primary waves-effect waves-light">Confirm</button>
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

    $(document).ready(function() {
        getRoleNames();
        getPermissions();

        $('#editNewPermission').click(function() {

            var formData = $('#editRolePermission').serialize();
            var fCallback = sendEdit;
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        });

        $('#roleInfo').click(function() {
            var roleParamId = $.urlParam('id');
            window.location.href = "editRole.php?id="+roleParamId;
        });
    });

    // Submit the Edit Form.
    function sendEdit(data, message) {
        showMessage('Role Permission successfully updated.', 'success', 'Role Permission', 'RolePermission', 'role.php');
    }

    //############ Load the Permissions Tree. ############//
    //get the Permissions List.
    function getPermissions() {
        var formData  = { "command" : "getPermissionNames" };
        var fCallback = permissionsTree;
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
    }

    function permissionsTree(data, message) {
        permissionNames = data.permissionNames;

        $.each(permissionNames['name'], function(key, val) {
            var parentId      = permissionNames['parent_id'][key];
            var permissonName = permissionNames['name'][key];
            var permissonId   = permissionNames['id'][key];

            if(parentId == 0){
                var mainMenu = '<div class="checkbox checkbox-primary" id="parent_' + permissonId + '">'+
                                    '<input id="permisson_' + permissonId + '" type="checkbox" onclick="parentFunc(this.id, ' + permissonId + ');"  name = "permissions[]" value="'+permissonId+'">'+
                                        '<label for="checkbox2">'+
                                            permissonName
                                        '</label>'+
                                    '</div>';
            }

            if(parentId != 0) {
                var childMenu = '<div class="checkbox checkbox-primary">'+
                                    '<input class="parent-' + parentId + '" id="permisson_' + permissonId + '" type="checkbox" name = "permissions[]" value="'+permissonId+'">'+
                                        '<label for="checkbox2">'+
                                            permissonName
                                        '</label>'+
                                    '</div>';
                $('#parent_'+parentId).append(childMenu);
            }
            $('#permissionsList').append(mainMenu);
        });
        getEditParamData(data);
    }
    //############ End of Loading the Permissions Tree. ############//

    //############ Load the Role name Dropdown. ############//
    //Load the Role name Dropdown.
    function getRoleNames() {
        var formData  = { "command" : "getRoleNames" };
        var fCallback = loadRoleNamesDropdown;
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
    }

    function loadRoleNamesDropdown(data, message) {
        roleNames = data.roleNames;
        $.each(roleNames['name'], function(key, val) {
            var roleID = roleNames['id'][key];
            $('#roleName').append('<option value="' + roleID + '">' + val + '</option>');
        });
    }
    //############ End Role name Dropdown loading. ############//

    //############ Load saved data in the form ############//
    //Load the Permission data in the Menu
    function getEditParamData(permissionsList) {
        var roleParamId = $.urlParam('id');
        var formData  = {'command' : "getRolePermissionData", 'roleParamId' : roleParamId };
        var fCallback = loadEditData;

        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
    }

    function loadEditData(data, message) {
        var roleData = data.rolePermissionData;
        $('#roleName').val(roleData.roleId);
        $('#roleValue').val(roleData.roleId);

        savedPermissionNames = data.rolePermissionData;
        $.each(permissionNames['name'], function(key, val) {
            var permissonsId    = permissionNames['id'][key];
            $.each(savedPermissionNames['permissions'], function(keys, vals) {
                var permissions = savedPermissionNames['permissions'][keys];
                if($("#permisson_"+permissonsId).val() == permissions) {
                    $("#permisson_"+permissonsId).attr('checked', 'checked');
                }
            });
        });
    }
    //############ End of Loading saved data in the form ############//

    //Validation for Parent - Child checkboxes selection.
    function parentFunc(id, pid) {
        if($("#"+id).is(':checked')) {
            $('.parent-'+pid).prop("checked", true);
        } else {
            $('.parent-'+pid).prop("checked", false);
        }
    }

    //For capturing the parameter from the URL.
    $.urlParam = function(name){
        var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
        if (results==null){
           return null;
        }
        else{
           return decodeURI(results[1]) || 0;
        }
    }
</script>
</html>