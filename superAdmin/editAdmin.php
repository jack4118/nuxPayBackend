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
                         <a href="admin.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <h4 class="header-title m-t-0 m-b-30">Edit Profile</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <form id="editUser" role="form" data-parsley-validate novalidate>
                                            <div class="form-group" hidden>
                                                <label for="">ID*</label>
                                                <input id="id" type="text" class="form-control" disabled  required/>
                                            </div>
                                            <div class="form-group">
                                                <label for="">Full Name*</label>
                                                <input id="name" type="text" class="form-control" required/>
                                            </div>
                                            <div class="form-group">
                                                <label for="">Username*</label>
                                                <input id="username" type="text" class="form-control"  required/>
                                            </div>
                                            <div class="form-group">
                                                <label for="">Email*</label>
                                                <input id="email" type="text" class="form-control"  required/>
                                            </div>
                                            <div class="form-group">
                                                <label class="control-label">Admin Role*</label>
                                                <select id="roleID" class="form-control">
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="">Disabled*</label>
                                                <div id="status" class="m-b-20">
                                                    <div class="radio radio-info radio-inline">
                                                        <input type="radio" id="inlineRadio1" value="0" name="radioInline"/>
                                                        <label for="inlineRadio1"> No </label>
                                                    </div>
                                                    <div class="radio radio-inline">
                                                        <input type="radio" id="inlineRadio2" value="1" name="radioInline"/>
                                                        <label for="inlineRadio2"> Yes </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 m-b-20">
                            <!-- <button type="submit" class="btn btn-default waves-effect waves-light">Cancel</button> -->
                            <button id="edit" type="submit" class="btn btn-primary waves-effect waves-light">Confirm</button>
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
    var url = 'scripts/reqAdmin.php';
    var method = 'POST';
    var debug = 1;
    var bypassBlocking = 0;
    var bypassLoading = 0;
    var role = [];

    var fCallback = "";
    $(document).ready(function() {
        var formData = {
            command : "getRoles",
            getActiveRoles : "getActiveRoles",
        };

        fCallback = loadFormDropdown;
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        
        editId = "<?php echo $_GET['id']; ?>";

        if(editId) {
            var formData = {
                'command': 'getAdminDetails',
                'id' : editId
            };
            fCallback = loadEdit;
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }else{
            // console.log("12312312312321 : " + editId);

            window.location = 'admin.php';
        }
        
        $('#edit').click(function() {
            var validate = $('#editUser').parsley().validate();
            if(validate) {
                showCanvas();
                var id = $('input#id').val();
                var fullName = $('input#name').val();
                var username = $('input#username').val();
                var email = $('input#email').val();
                var roleID = $('#roleID option:selected').val();
                var status = $('#status').find('input[type=radio]:checked').val();
                
                var formData = {
                    'command': 'editAdmin',
                    'id' : id,
                    'fullName'   : fullName,
                    'username'   : username,
                    'email'  : email,
                    'roleID' : roleID,
                    'status' : status
                };
                
                fCallback = sendEdit;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }
        }); 
    });
    
    function loadFormDropdown(data, message) {
        roleData = data.roleList;
        $.each(roleData, function(key) {
            $('#roleID').append('<option value="' + roleData[key]['id'] + '">' + roleData[key]['name'] + '</option>');
        });
    }
    
    function loadEdit(data, message) {
        $.each(data.adminDetail, function(key, val) {
            if(key == 'status') {
                $('#'+key).find('input[value="'+val+'"]').attr('checked', 'checked');
            }
            else {
                $('#'+key).val(val);
            }
        });
    }
    
    function sendEdit(data, message) {
        showMessage('Admin data successfully updated.', 'success', 'Edit admin', 'admin', 'admin.php');
    }
</script>
</body>
</html>
