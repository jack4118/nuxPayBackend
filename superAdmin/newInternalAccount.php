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
                         <a href="internalAccounts.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <h4 class="header-title m-t-0 m-b-30">Internal Account</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <form role="form" id="newInternalAccount" data-parsley-validate novalidate>
                                        <input type="hidden" name="command" value="newInternalAccount">
                                            <div class="form-group">
                                                <label for="">Username*</label>
                                                <input id="username" type="text" class="form-control" name="username"   required>
                                            </div>

                                            <div class="form-group">
                                                <label for="">Name*</label>
                                                <input id="name" name="name" type="text" class="form-control"   required>
                                            </div>
                                            <!-- <div class="form-group">
                                                <label for="">Description</label>
                                                <input id="description" type="textarea" cols="3" rows="3" name="description" class="form-control" placeholder="Description.">
                                            </div> -->
                                            <div class="form-group">
                                                <label for="">Description*</label>
                                                <select id="description" class="form-control" name="description"  required>
                                                    <option value="">--Please Select--</option>
                                                    <option value="earnings">Earnings</option>
                                                    <option value="expenses">Expenses</option>
                                                    <option value="suspense">Suspense</option>
                                                </select>
                                            </div>

                                            <!-- <div class="form-group">
                                                <label for="">Password</label>
                                                <input id="password" type="password" name="password" class="form-control" placeholder="Password.">
                                            </div>

                                            <div class="form-group">
                                                <label for="">Transaction Password</label>
                                                <input id="transaction_password" type="password" name="transaction_password" class="form-control" placeholder="Transaction Password.">
                                            </div>

                                            <div class="form-group">
                                                <label for="">Type</label>
                                                <input id="type" type="text" name="type" class="form-control" placeholder="Type.">
                                            </div>

                                            <div class="form-group">
                                                <label for="">Description</label>
                                                <input id="description" type="textarea" cols="3" rows="3" name="description" class="form-control" placeholder="Description.">
                                            </div>

                                            <div class="form-group">
                                                <label for="">Email</label>
                                                <input id="email" type="email" name="email" class="form-control" placeholder="Email.">
                                            </div>

                                            <div class="form-group">
                                                <label for="">Phone</label>
                                                <input id="phone" type="number" name="phone" class="form-control" placeholder="Phone.">
                                            </div>

                                            <div class="form-group">
                                                <label for="">Address</label>
                                                <input id="address" type="text" name="address" class="form-control" placeholder="Address.">
                                            </div>

                                            <div class="form-group">
                                                <label for="">Country</label>
                                                <input id="country_id" type="text" name="country_id" class="form-control" placeholder="Country.">
                                            </div>

                                            <div class="form-group">
                                                <label for="">State</label>
                                                <input id="state_id" type="text" name="state_id" class="form-control" placeholder="State.">
                                            </div>

                                            <div class="form-group">
                                                <label for="">County</label>
                                                <input id="county_id" type="text" name="county_id" class="form-control" placeholder="County.">
                                            </div>

                                            <div class="form-group">
                                                <label for="">City</label>
                                                <input id="city_id" type="text" name="city_id" class="form-control" placeholder="City.">
                                            </div>

                                            <div class="form-group">
                                                <label for="">Sponser</label>
                                                <input id="sponsor_id" type="text" name="sponsor_id" class="form-control" placeholder="Sponser.">
                                            </div>

                                            <div class="form-group">
                                                <label for="">Placement</label>
                                                <input id="placement_id" type="text" name="placement_id" class="form-control" placeholder="Placement.">
                                            </div>

                                            <div class="form-group">
                                                <label for="">Status</label>
                                                <div class="m-b-20" id="status">
                                                    <div class="radio radio-info radio-inline">
                                                        <input type="radio" id="inlineRadio1" value="0" name="disabled" checked="">
                                                        <label for="inlineRadio1"> On </label>
                                                    </div>
                                                    <div class="radio radio-inline">
                                                        <input type="radio" id="inlineRadio2" value="1" name="disabled" checked="">
                                                        <label for="inlineRadio2"> Off </label>
                                                    </div>
                                                </div>
                                            </div> -->
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 m-b-20">
                            <button id="addNewInternalAccount" type="submit" class="btn btn-primary waves-effect waves-light">Confirm</button>
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
    var url = 'scripts/reqInternalAccounts.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;

    $(document).ready(function() {
        $('#addNewInternalAccount').click(function() {
            var validate = $('#newInternalAccount').parsley().validate();
                if(validate) {
                    var fCallback = sendNew;
                    var formData  = $('#newInternalAccount').serialize();
                    ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
                }
        });
    });

    function sendNew(data, message) {
        showMessage('InternalAccount successfully created.', 'success', 'Add New InternalAccount', 'InternalAccount', 'internalAccounts.php');
    }
</script>
</html>