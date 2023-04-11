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
                             <a href="credit.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                        </div><!-- end col -->
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <div class="row">
                                    <ul class="nav nav-tabs">
                                        <li><a id="editCreditTab">Details</a></li>
                                        <li class="active"><a data-toggle="tab" href="#settings">Settings</a></li>
                                    </ul>
                                    <div class="tab-content m-b-30">
                                        <div id="settings" class="tab-pane fade in active">
                                            <div class="row">
                                                <div id="creditDetails" class="tab-content b-0 m-b-0 p-t-0">
                                                    <div id="" class="tab-content b-0 m-b-0 p-t-0">
                                                        <h4 class="header-title m-t-0 m-b-30">Credit Setting Details</h4>
                                                        <div id="creditSettingList" class="tab-content b-0 m-b-0 p-t-0">
                                                            <div id="editCreditMsg" class="alert" style="display: none;"></div>
                                                        </div>
                                                        <div class="col-md-12 m-b-20">
                                                            <button id="editCreditBtn" type="submit" class="btn btn-primary waves-effect waves-light">Save</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> <!-- row -->

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
    var editId = window.location.search.substr(1).split("=");
    editId = editId[1];
    // Initialize the arguments for ajaxSend function
    var url = 'scripts/reqCredit.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;
        
    var fCallback = loadEdit;
    $(document).ready(function() {
        
        if(editId != '') {
            var formData = {
                command: 'getCreditSettingDetails',
                editId : editId
            };
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }
        
        $('#editCreditTab').click(function() {
            window.location.href = "editCredit.php?id="+editId;
        });
        
        $('#editCreditBtn').click(function() {
            var creditID = $('input#id').val();
            var id = [];
            var values = [];
            
            $('#creditSettingList input:not(:first)').each(function() {
                id.push(this.id);
                values.push(this.value);
            });
            var formData = {
                command : 'editCreditSetting',
                creditID : creditID,
                id: id,
                values : values
            };
            fCallback = sendEdit;
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }); 
    });
    
    function loadEdit(data, message) {
        var creditData = data.creditSetting;
        if(typeof(creditData) != 'undefined') {
            $('#editCreditMsg').removeClass('alert-success').html('').hide();
            $('#creditSettingList').append('<div class="form-group" hidden><label class="control-label">Credit ID</label><input id="id" type="text" class="form-control" value="' + editId + '"></div>');
            $.each(creditData['name'], function(key, val) {
                var id = creditData['creditSettingID'][key];
                var value = creditData['value'][key];
                $('#creditSettingList').append('<div class="form-group"><label class="control-label">' + val + '</label><input id="' + id + '" type="text" class="form-control" value="' + value + '"></div>');
            });
        }
        else
            $('#editCreditMsg').addClass('alert-success').html('<span>'+message+'</span>').show();
    }
    
    function sendEdit(data, message) {
        showMessage('Credit Setting successfully updated.', 'success', 'Edit credit Setting', 'credit-card', 'creditSetting.php');
    }
</script>
</body>
</html>