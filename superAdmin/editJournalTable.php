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
                                <h4 class="header-title m-t-0 m-b-30">JournalTable</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <form role="form" id="editJournalTables" data-parsley-validate novalidate>
                                            <div class="form-group">
                                                <label class="control-label" data-th="table_name">Table Name</label>
                                                <!-- <select class="form-control"  required id="table_name" name="tableName"  required>
                                                    <option value="">--Please Select--</option>
                                                </select> -->
                                                <span class="form-control form-border-0" id="table_name"></span>
                                            </div>

                                            <div class="form-group">
                                                <label class="control-label" data-th="type">Type</label>
                                                <select class="form-control"  required id="type" name="tableType"  required>
                                                    <option value="">--Please Select--</option>
                                                    <option value="Column Based">Column Based</option>
                                                    <option value="Row Based">Row Based</option>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="">Status</label>
                                                <div class="m-b-20" id="status">
                                                    <div class="radio radio-info radio-inline">
                                                        <input id="statusRadio1" type="radio" name="statusRadio" value="0">
                                                        <label for="statusRadio1"> Active </label>
                                                    </div>
                                                    <div class="radio radio-inline">
                                                        <input id="statusRadio2" type="radio" name="statusRadio" value="1">
                                                        <label for="statusRadio2"> Disabled </label>
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
                            <button id="editJournalTable" type="submit" class="btn btn-primary waves-effect waves-light">Confirm</button>
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
    var url             = 'scripts/reqJournalTables.php';
    var method          = 'POST';
    var debug           = 1;
    var bypassBlocking  = 0;
    var bypassLoading   = 0;

    $(document).ready(function() {
        var journalTableId = window.location.search.substr(1).split("=");
        journalTableId = journalTableId[1];

        if(journalTableId != '') {
            var formData = {
                'command'        : 'getJournalTableData',
                'journalTableId' : journalTableId
            };

            fCallback = loadEdit;
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }
        
        $('#editJournalTable').click(function() {
            var validate = $('#editJournalTables').parsley().validate();
            if(validate) {
                var type      = $('#type').val();
                var disabled  = $('#status').find('input:checked').val();
                
                var formData = {
                    'command'        : 'editJournalTableData',
                    'journalTableId' : journalTableId,
                    'type'           : type,
                    'disabled'       : disabled
                };

                fCallback = sendEdit;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }
        });
    });

    function loadEdit(data, message) {
        $.each(data.journalsTable, function(key, val) {
            if(key == 'table_name') {
                $('#'+key).text(val);
            }
            else if(key == 'disabled') {
                console.log(key+': '+val);
                if(val == 1)
                    $('#statusRadio2').attr('checked', 'checked');
                else
                    $('#statusRadio1').attr('checked', 'checked');
            }else {
                $('#'+key).val(val);
            }
        });
    }

    function sendEdit(data, message) {
        showMessage('Journal Table successfully updated.', 'success', 'Edit Journal Table', 'JournalTable', 'journalTables.php');
    }
</script>
</html>