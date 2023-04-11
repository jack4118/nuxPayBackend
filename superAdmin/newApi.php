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
                         <a href="apiList.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                    </div><!-- end col -->
                </div>
                    <div class="row">
                        <form role="form" id="addApi" data-parsley-validate novalidate>
                            <div class="col-lg-12">
                                <div class="card-box p-b-0">
                                    <h4 class="header-title m-t-0 m-b-30">API</h4>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="">Command*</label>
                                                <input type="text" class="form-control" id="command" name="commandName"  required>
                                            </div>
                                            <div class="form-group">
                                                <label for="">Duration*</label>
                                                <input type="number" class="form-control" id="duration" name="duration"  required>
                                            </div>
                                            <div class="form-group">
                                                <label for="">No of queries*</label>
                                                <input type="number" class="form-control" id="queries" name="queries"  required>
                                            </div>

                                            <div class="form-group">
                                                <label for="">Description</label>
                                                <textarea rows="4" cols="50" class="form-control" id="description" name="description"></textarea>
                                            </div>

                                            <div class="form-group">
                                                <label for="">Status</label>
                                                <div class="m-b-20" id="status">
                                                    <div class="radio radio-info radio-inline">
                                                        <input type="radio" id="inlineRadio1" value="0" name="apiStatus" checked="">
                                                        <label for="inlineRadio1"> Active </label>
                                                    </div>
                                                    <div class="radio radio-inline">
                                                        <input type="radio" id="inlineRadio2" value="1" name="apiStatus" checked="">
                                                        <label for="inlineRadio2"> Disabled </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- <div class="card-box p-b-0">
                                    <h4 class="header-title m-t-0 m-b-30">Parameters</h4>
                                    <a href="javascript:void(0)" onClick="addApiParameter(0)" class="m-b-20 btn btn-primary waves-effect waves-light">Add Parameter</a>
                                    <div class="row">
                                        <div class="col-md-12 card-box kanban-box">
                                            <div class="col-sm-12 form-group">
                                                <label for="">Parameters Name</label>
                                                <input type="text" class="form-control" name="apiParam[0][paramName]">
                                            </div>
                                            <div class="col-sm-12">
                                                <div class="radio radio-info radio-inline">
                                                    <input type="radio" id="inlineRadio1" value="alphanumeric" name="apiParam[0][paramVal]" checked="">
                                                    <label for="inlineRadio1"> Alphanumberic </label>
                                                </div>
                                                <div class="radio radio-info radio-inline">
                                                    <input type="radio" id="inlineRadio1" value="numeric" name="apiParam[0][paramVal]" checked="">
                                                    <label for="inlineRadio2"> Numeric </label>
                                                </div>
                                                <div class="radio radio-info radio-inline">
                                                    <input type="radio" id="inlineRadio1" value="blob" name="apiParam[0][paramVal]" checked="">
                                                    <label for="inlineRadio3"> Blob </label>
                                                </div>
                                            </div>
                                        </div>
                                   </div>
                                   <input type="hidden" value="1" id="apiParamTabs0Cnt">
                                    <div style="margin-left:0px" id="apiParamTabs0"></div>
                                </div> -->
                            </div>
                            <div class="col-md-12 m-b-20">
                                <button type="button" class="btn btn-primary waves-effect waves-light" id="saveApi">Confirm</button>
                            </div>
                        </form>
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
    <script type="text/javascript">
        // Initialize the arguments for ajaxSend function
        var url = 'scripts/reqApi.php';
        var method = 'POST';
        var debug = 0;
        var bypassBlocking = 0;
        var bypassLoading = 0;

        $(document).ready(function() {
            $('#saveApi').click(function() {
                var validate = $('#addApi').parsley().validate();
                if(validate) {
                    var command_name = $('#command').val();
                    var description = $('#description').val();
                    var duration = $('#duration').val();
                    var queries = $('#queries').val();
                    var status = $("input[name='apiStatus']:checked").val();

                    var formData = {'command' : "newApi",
                                    'commandName' : command_name, 
                                    'apiStatus' : status, 
                                    'description' : description, 
                                    'duration' : duration, 
                                    'queries' : queries};

                    var fCallback = sendNew;
                    ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
                }
            });
        });

        function sendNew(data, message) {
            showMessage('Api successfully created.', 'success', 'Add New Api', 'Api', 'apiList.php');
        }

        // function addApiParameter(count){
        //     var apiCnt = $("#apiParamTabs"+(count)+"Cnt").val();

        //     var phonevar = '<div class="col-md-12 card-box kanban-box"><div class="col-sm-12 form-group"><label for="">Parameters Name </label>'+
        //     '<input name="apiParam['+apiCnt+'][paramName]" class="form-control"></input>'+
        //     '<div class="col-sm-12">'+
        //     '</div><div class="col-md-2"><span class="radio radio-info">'+
        //     '<input type="radio" name="apiParam['+apiCnt+'][paramVal]" value="alphanumeric" id="'+count+'apiParam'+apiCnt+'Alphanumberic" ><label class="radio-inline">Alphanumberic'+
        //     '</label></span></div><div class="col-md-2"><span class="radio radio-info">'+
        //     '<input type="radio" name="apiParam['+apiCnt+'][paramVal]" value="numeric" id="'+count+'apiParam'+apiCnt+'Numeric" ><label class="radio-inline">Numeric'+
        //     '</label></span></div><div class="col-md-2"><span class="radio radio-info">'+
        //     '<input type="radio" name="apiParam['+apiCnt+'][paramVal]" value="blob" id="'+count+'apiParam'+apiCnt+'Blob" ><label class="radio-inline">Blob'+
        //     '</label></span></div><a style="float:right; margin-top: 10px;" class="m-t-5 btn btn-icon waves-effect waves-light btn-primary zmdi zmdi-delete" onClick="deleteParam('+count+','+apiCnt+')" id="'+count+'" href="javascript:void(0)" title="Delete Phone"></a></span>'+
        //     '</div></div>';

        //     $('#apiParamTabs'+count).append('<div id="ptabs-'+count+'-'+apiCnt+'" class="row">'+phonevar+'</div>');

        //     var cnt = ++apiCnt;
        //     $("#apiParamTabs"+(count)+"Cnt").val(cnt);
        // }

        // function deleteParam(count,phoneCnt) {
        //     $('#ptabs-'+count+'-'+phoneCnt).remove();
        // }
    </script>
</html>