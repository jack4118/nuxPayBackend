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
                    <form role="form" id="addMessageAssigned" data-parsley-validate novalidate>
                    <!-- <input type="hidden" class="form-control" value="newMessageAssigned" name="command"> -->
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="card-box p-b-0">
                                    <h4 class="header-title m-t-0 m-b-30">Message Assigned</h4>
                                    <div class="row">
                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label class="control-label">Code*</label>
                                                <select class="form-control" id="messageCode" name="messageCode"  required>
                                                    <option value="">--Please Select--</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label class="control-label">Type*</label>
                                                <select  required class="form-control" id="messageType" name="messageType"  required>
                                                    <option value="">--Please Select--</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label for="">Receipient*</label>
                                                <input class="form-control" name="messageRecipient" type="email" name="email" id="messageRecipient"  required>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- <div class="card-box p-b-0">
                                    <h4 class="header-title m-t-0 m-b-30">Assign to</h4>
                                    <a href="javascript:void(0)" onClick="addReceipient(0)" class="m-b-20 btn btn-primary waves-effect waves-light">Add Receipient</a>
                                    <div class="row">
                                        <div class="col-md-12 card-box kanban-box">
                                            <div class="col-sm-4 form-group">
                                                <label class="control-label">Type</label>
                                                <select  required class="form-control" id="messageType" name="messageParam[0][messageType]" >
                                                    <option>--Please Select--</option>>
                                                </select>
                                            </div>
                                            <div class="col-sm-8 form-group">
                                                <label for="">Receipient</label>
                                                <input class="form-control" name="messageParam[0][recipient]" type="email" name="email"  required id="messageRecipient">
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" value="1" id="messageParamTabs0Cnt">
                                    <div style="margin-left:0px" id="messageParamTabs0"></div>
                                </div> -->
                            </div>
                            <div class="col-md-12 m-b-20">
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
    </div>
    <!-- END wrapper -->

    <!-- jQuery  -->
    <?php include("shareJs.php"); ?>
    <script type="text/javascript">
        var resizefunc = [];
        // Initialize the arguments for ajaxSend function
        var url            = 'scripts/reqMessage.php';
        var method         = 'POST';
        var debug          = 0;
        var bypassBlocking = 0;
        var bypassLoading  = 0;
        messageTypeData    = new Array();

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

            $('#saveMessage').click(function() {
                var validate = $('#addMessageAssigned').parsley().validate();
                if(validate) {
                    var msgCode      = $("#messageCode").val();
                    var msgType      = $("#messageType").val();
                    var msgRecipient = $("#messageRecipient").val();

                    var formData = {'command'      : "newMessageAssigned",
                                    'msgCode'      : msgCode, 
                                    'msgType'      : msgType, 
                                    'msgRecipient' : msgRecipient};

                    var fCallback = sendNew;
                    ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
                }
            });

            //Append the text box properties according to the Type.
            $('#messageType').change(function() {
                 var messageType = $('#messageType').val();
                  if(messageType == "email"){
                        $("#messageRecipient").attr('type', 'email');
                        $("#messageRecipient").val("");
                  } if(messageType == "mail"){
                        $("#messageRecipient").attr('type', 'email');
                        $("#messageRecipient").val("");
                  } else if(messageType == "sms" || messageType == "xun"){
                        $("#messageRecipient").attr('type', 'number');
                        $("#messageRecipient").attr('minlength', '8');
                        $("#messageRecipient").val("");
                  }
            });
        });

        // function loadFormDropdown(data, message) {
        //     var code = data.messageData;
        //     $.each(code['messageCode'], function(key, val) {
        //         $('#messageCode').append('<option value="' + val + '">' + val + '</option>');
        //     });
        // }
        function loadFormDropdown(data, message) {
            var msgDesc  = data.messageData;
            var msgCode = msgDesc.messageCode;

            $.each(msgDesc['messageDesc'], function(key, val) {
                $('#messageCode').append('<option value="' + msgCode[key] + '">' + val + '</option>');
            });
        }

        function loadMessageType(data, message) {
            var type = data.messageData;
            $.each(type['messageType'], function(key, val) {
                $('#messageType').append('<option value="' + val + '">' + val + '</option>');
            });
        }

        function sendNew(data, message) {
            showMessage('Message Assigned successfully created.', 'success', 'New Message Assigned', 'MessageAssigned', 'messageAssigned.php');
        }
    </script>
</html>