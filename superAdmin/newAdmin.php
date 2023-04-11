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
                            <h4 class="header-title m-t-0 m-b-30">User Profile</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <form role="form" id="newAdmin" data-parsley-validate novalidate>
                                        <div id="basicwizard" class=" pull-in">
                                            <div class="tab-content b-0 m-b-0 p-t-0">
                                                <div class="form-group">
                                                    <label for="">Full Name*</label>
                                                    <input id="fullName" type="text" class="form-control"  required/>
                                                </div>
                                                <div class="form-group">
                                                    <label for="">Username*</label>
                                                    <input id="username" type="text" class="form-control"  required/>
                                                </div>
                                                <div class="form-group">
                                                    <label for="">Email*</label>
                                                    <input id="email" type="email" class="form-control"  required/>
                                                </div>
                                                <div class="form-group">
                                                    <label for="">Password*</label>
                                                    <input id="password" type="password" class="form-control"  required/>
                                                </div>
                                                <div class="form-group">
                                                    <label class="control-label">Admin Role*</label>
                                                    <select id="roleID" class="form-control">
                                                    </select>
                                                </div>
                                                 <div class="form-group">
                                                    <label class="control-label">Disabled*</label>
                                                    <div id="status" class="m-b-20">
                                                        <div class="radio radio-info radio-inline">
                                                            <input type="radio" id="inlineRadio1" value="0" name="radioInline" checked="checked"/>
                                                            <label for="inlineRadio1"> No </label>
                                                        </div>
                                                        <div class="radio radio-inline">
                                                            <input type="radio" id="inlineRadio2" value="1" name="radioInline"/>
                                                            <label for="inlineRadio2"> Yes </label>
                                                        </div>
                                                    </div>
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
                        <button id="add" type="submit" class="btn btn-primary waves-effect waves-light">Confirm</button>
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
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;
        
    var fCallback = loadFormDropdown;
    $(document).ready(function() {
        var formData = {
            command : "getRoles",
            getActiveRoles : "getActiveRoles",
        };
        
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        
        $('#add').click(function() {
            var validate = $('#newAdmin').parsley().validate();
            if(validate) {
                showCanvas();
                var fullName = $('#fullName').val();
                var username = $('#username').val();
                var email = $('#email').val();
                var password = $('#password').val();
                var roleID = $('#roleID').find('option:selected').val();
                var status = $('#status').find('input[type=radio]:checked').val();
                
                var formData = {
                    command : "addAdmin",
                    fullName : fullName,
                    username : username,
                    email : email,
                    password : password,
                    roleID : roleID,
                    status : status
                };
                var fCallback = sendNew;
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
    
    function sendNew(data, message) {
        showMessage('New admin successfully created.', 'success', 'Add New Admin', 'admin', 'admin.php');
    }
</script>
</body>
</html>
