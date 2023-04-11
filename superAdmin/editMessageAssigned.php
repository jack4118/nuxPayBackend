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
                         <a href="messageAssigned.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>
                <form role="form" id="editMessageAssigned" data-parsley-validate novalidate>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <h4 class="header-title m-t-0 m-b-30">Message Assigned</h4>
                                <div class="row">
                                    <div class="col-sm-4">
                                        <div class="form-group">
                                            <label class="control-label">Code*</label>
                                            <select id="code" class="form-control"  required>
                                                <option value="">--Please Select--</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-sm-4">
                                        <div class="form-group">
                                            <label class="control-label">Type*</label>
                                            <select id="type" class="form-control"  required>
                                                <option value="">--Please Select--</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-sm-4">
                                        <div class="form-group">
                                            <label for="">Recipient*</label>
                                            <input id="recipient" type="text" class="form-control"  required>
                                        </div>
                                    </div>
                                </div>
<!--
                                <div class="row">
                                    <div class="col-sm-4">
                                        <div class="form-group">
                                            <label class="control-label">Code</label>
                                            <select disabled class="form-control" data-parsley-group="second" required id="messageCode" name="messageCode">
                                                <option>--Please Select--</option>>
                                            </select>
                                            <ul class="parsley-errors-list filled" id="msgCodeError" style="display: none;"><li class="parsley-required">Please select message code.</li></ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-4">
                                        <div class="form-group">
                                            <label class="control-label">Type</label>
                                            <select data-parsley-group="second" required class="form-control" id="messageType" name="messageParam[0][messageType]" >
                                                <option>--Please Select--</option>>
                                            </select>
                                            <ul class="parsley-errors-list filled" id="msgTypeError" style="display: none;"><li class="parsley-required">Please select message
                                            type.</li></ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-4">
                                        <div class="form-group">
                                            <label for="">Receipient</label>
                                            <input class="form-control" name="messageParam[0][recipient]" type="email" name="email" data-parsley-group="second" required id="messageRecipient">
                                            <ul class="parsley-errors-list filled" id="msgRecipientError" style="display: none;"><li class="parsley-required">Please select message
                                            recipient.</li></ul>
                                        </div>
                                    </div>
                                </div>
-->
                            </div>
                        </div>
                        <div class="col-md-12 m-b-20">
                            <!-- <button type="submit" class="btn btn-default waves-effect waves-light">Cancel</button> -->
                            <button type="button" id="saveMessage" class="btn btn-primary waves-effect waves-light">Confirm</button>
                        </div>
                    </div>
                    <!-- End row -->
                    </form>
                </div> <!-- container -->
            </div> <!-- content -->
            <?php include("footer.php"); ?>
        </div>
        <!-- End content-page -->
        <!-- ============================================================== -->
        <!-- End Right content here -->
        <!-- ============================================================== -->
    </div>

<!-- jQuery  -->
    <?php include("shareJs.php"); ?>
    <script type="text/javascript">
        var resizefunc = [];
        // Initialize the arguments for ajaxSend function
        var url = 'scripts/reqMessage.php';
        var method = 'POST';
        var debug = 0;
        var bypassBlocking = 0;
        var bypassLoading = 0;

        var fCallback = loadFormDropdown;
        $(document).ready(function() {
            var formData = {
                command : 'getMessageCode',
            };
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);

            var reqFile = 'scripts/reqProvider.php';
            var formDatas = {
                command : 'getMessageType',
            };
            var fCallbacks = loadMessageType;
            ajaxSend(reqFile, formDatas, method, fCallbacks, debug, bypassBlocking, bypassLoading, 0);
            
            var editId = window.location.search.substr(1).split("=");
            editId = editId[1];
            if(editId != '') {
                var formData = {
                    command : 'getEditMessageAssignedData',
                    id : editId
                };
                fCallback = loadEdit;
                ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
            }
            
            $('#saveMessage').click(function() {
                var validate = $('#editMessageAssigned').parsley().validate();
                if(validate) {
                    var messageCode = $('#code option:selected').val();
                    var code        = messageCode.split("-");
                    var type        = $("#type").val();
                    var recipient   = $("#recipient").val();

                    var formData = {
                        command: 'editMessageAssigned',
                        id        : editId,
                        code      : code[0],
                        type      : type,
                        recipient : recipient
                    };

                    fCallback = sendEdit;
                    ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
                }
            });

            //Append the text box properties according to the Type.
            $('#type').change(function() {
                 var messageType = $('#type').val();
                  if(messageType == "email"){
                        $("#recipient").attr('type', 'email');
                        //$("#recipient").val("");
                  } if(messageType == "mail"){
                        $("#recipient").attr('type', 'email');
                        //$("#recipient").val("");
                  } else if(messageType == "sms" || messageType == "xun"){
                        $("#recipient").attr('type', 'number');
                        $("#recipient").attr('minlength', '8');
                        //$("#recipient").val("");
                  }
            });
        });
        
        function loadFormDropdown(data, message) {
            var msgDesc  = data.messageData;
            var msgCode = msgDesc.messageCode;

            $.each(msgDesc['messageDesc'], function(key, val) {
                $('#code').append('<option value="' + msgCode[key] + '">' + val + '</option>');
            });
        }

        function loadMessageType(data, message) {
            var type = data.messageData;
            $.each(type['messageType'], function(key, val) {
                $('#type').append('<option value="' + val + '">' + val + '</option>');
            });
        }

        function loadEdit(data, message) {
            $.each(data.messageAssignedData, function(key, val) {
                if(key == "type"){
                    $("#type").val(val).change();
                } else {
                    $('#'+key).val(val);
                }
            });
        }

        function sendEdit(data, message) {
            showMessage('Message Assigned updated successfully.', 'success', 'Edit Message Assigned', 'MessageAssigned', 'messageAssigned.php');
        }
    </script>
</html>