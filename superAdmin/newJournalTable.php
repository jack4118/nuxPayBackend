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
                         <a href="journalTables.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <h4 class="header-title m-t-0 m-b-30">Journal Table</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <form role="form" id="addnewJournal" data-parsley-validate novalidate>
                                            <div class="form-group">
                                                <label class="control-label" data-th="table_name">Table Name*</label>
                                                <select class="form-control"  required id="table_name" name="tableName"  required>
                                                    <option value="">--Please Select--</option>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label class="control-label" data-th="type">Type*</label>
                                                <select class="form-control"  required id="tableType" name="tableType"  required>
                                                    <option value="">--Please Select--</option>
                                                    <option value="Column Based">Column Based</option>
                                                    <option value="Row Based">Row Based</option>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="">Status</label>
                                                <div class="m-b-20" id="status">
                                                    <div class="radio radio-info radio-inline">
                                                        <input type="radio" id="inlineRadio1" value="0" name="disabled" checked="">
                                                        <label for="inlineRadio1"> Active </label>
                                                    </div>
                                                    <div class="radio radio-inline">
                                                        <input type="radio" id="inlineRadio2" value="1" name="disabled" checked="">
                                                        <label for="inlineRadio2"> Disabled </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 m-b-20">
                            <button id="addNewJournalTable" type="submit" class="btn btn-primary waves-effect waves-light">Confirm</button>
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
    <script src="js/journal.js"></script>
<script>
    $(document).ready(function() {
        getJournalTableNames("add");

        // Initialize the arguments for ajaxSend function
        var url = 'scripts/reqJournalTables.php';
        var method = 'POST';
        var debug = 0;
        var bypassBlocking = 0;
        var bypassLoading = 0;

        $(document).ready(function() {
            $('#addNewJournalTable').click(function() {
                var validate = $('#addnewJournal').parsley().validate();
                if(validate) {
                    var name      = $('#table_name').val();
                    var type      = $('#tableType').val();
                    var disabled  = $("input[name='disabled']:checked").val();

                    var formData = { command : "newJournalTable", 
                                     "tableName" : name, 
                                     "tableType" : type, 
                                     "disabled" : disabled};

                    var fCallback = sendNew;
                    ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
                }
            });
        });

        function sendNew(data, message) {
            showMessage('Journal Table successfully created.', 'success', 'Add New JournalTable', 'JournalTable', 'journalTables.php');
        }
    });
</script>
</html>