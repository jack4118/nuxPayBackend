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
                         <!-- <a href="messageAssigned.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a> -->
                    </div><!-- end col -->
                </div>
                    <form role="form" id="testMessage" data-parsley-validate novalidate>
                    <!-- <input type="hidden" class="form-control" value="newMessageAssigned" name="command"> -->
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="card-box p-b-0">
                                    <h4 class="header-title m-t-0 m-b-30">Message Code</h4>
                                    <div class="row">
                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label class="control-label">Code</label>
                                                <select class="form-control" id="messageCode" name="messageCode"  required>
                                                    <option value="">--Please Select--</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label class="control-label">Type</label>
                                                <select class="form-control" id="messageType" name="messageParam[0][messageType]"  required>
                                                    <option value="">--Please Select--</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label for="">Receipient</label>
                                                <input class="form-control" name="messageParam[0][recipient]" type="email" name="email" id="messageRecipient"  required>
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
                                <button type="button" id="sendMessage" class="btn btn-primary waves-effect waves-light">Confirm</button>
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

<!--     <div id="errModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                    <h4 class="modal-title" id="myModalLabel">Message Assigned</h4>
                </div>
                <div class="modal-body">
                    <p>Yooola!... Duplicate data found, Please change the Message type and Recipient!!.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Ok</button>
                </div>
            </div>
        </div>
    </div>

    <div id="savedModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                    <h4 class="modal-title" id="myModalLabel">Message Assigned</h4>
                </div>
                <div class="modal-body">
                    <p>Congratulations!... Message assiagned saved successfully!!.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Ok</button>
                </div>
            </div>
        </div>
    </div> -->

    <!-- jQuery  -->
    <?php include("shareJs.php"); ?>
    <script src="js/messageAssigned.js"></script>
    <script type="text/javascript">
        var resizefunc = [];
        var resizefunc = [];
        // Initialize the arguments for ajaxSend function
        var url            = 'scripts/reqMessage.php';
        var method         = 'POST';
        var debug          = 0;
        var bypassBlocking = 0;
        var bypassLoading  = 0;

        var fCallback = loadFormDropdown;
        $(document).ready(function() {
            var formData = {
                command : 'getMessageCode',
            };
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);

            $('#sendMessage').click(function() {
                var validate = $('#testMessage').parsley().validate();
                if(validate) {
                    var msgCode      = $("#messageCode").val();
                    var msgType      = $("#messageType").val();
                    var msgRecipient = $("#messageRecipient").val();

                    var formData = {'command'      : "sendMessage",
                                    'msgCode'      : msgCode, 
                                    'msgType'      : msgType, 
                                    'msgRecipient' : msgRecipient};

                    var fCallbacks = sendNew;
                    ajaxSend(url, formData, method, fCallbacks, debug, bypassBlocking, bypassLoading, 0);
                }
            });

            var reqFile = 'scripts/reqProvider.php';
            var formDatas = {
                command : 'getMessageType',
            };

            var fCallbacks = loadMessageType;
            ajaxSend(reqFile, formDatas, method, fCallbacks, debug, bypassBlocking, bypassLoading, 0);
        });

        function loadFormDropdown(data, message) {
            var msgDesc  = data.messageData;
            var msgCode = msgDesc.messageCode;

            $.each(msgDesc['messageDesc'], function(key, val) {
                $('#messageCode').append('<option value="' + msgCode[key] + '">' + val + '</option>');
            });
        }

        // $('#messageType').change(function() {
        //      var messageType = $('#messageType').val();
        //       if(messageType == "E-Mail"){
        //             $("#messageRecipient").attr('type', 'email');
        //       }else if(messageType == "SMS" || messageType == "XUN"){
        //             $("#messageRecipient").attr('type', 'number');
        //       }
        // });

        function sendNew(data, message) {
            showMessage('message sent successfully', 'success', 'Message Sent', 'Test Message', 'TestMessage.php');
        }

        function loadMessageType(data, message) {
            var type = data.messageData;
            $.each(type['messageType'], function(key, val) {
                $('#messageType').append('<option value="' + val + '">' + val + '</option>');
            });
        }
    </script>
</html>