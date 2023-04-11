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

                        </div><!-- end col -->
                    </div>
                    <form role="form" id="formTestAPI" data-parsley-validate novalidate>

                        <div class="row">
                            <div class="col-lg-12">
                                <div class="card-box p-b-0">
                                    <h4 class="header-title m-t-0 m-b-30">Test API</h4>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <input type="radio" name="role" value="superAdmin" checked>&nbsp;SuperAdmin
                                                <input type="radio" name="role" value="admin">&nbsp;Admin
                                                <input type="radio" name="role" value="client">&nbsp;Client
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="control-label">API Name</label>
                                                <select class="form-control" id="api_id" name="api_id"  required>
                                                    <option value="">--Please Select--</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="control-label">User ID (based on site)</label>
                                                <input id="userID" type="text" class="form-control" required/>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div id="apiParamsContainer" class="col-md-6">
                                            <!-- Append dynamic contents here -->
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="control-label">Data Out</label>
                                                <textarea id="dataOut" name="dataOut" rows="25" cols="50" class="form-control" readonly></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12 m-b-20">
                                <button type="button" id="btnTestAPI" class="btn btn-primary waves-effect waves-light">Test API</button>
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
    <!-- END wrapper -->

    <script>
        var resizefunc = [];
    </script>

    <!-- jQuery  -->
    <?php include("shareJs.php"); ?>
    <script src="js/api.js"></script>
    
<script>
    // Initialize the arguments for ajaxSend function
    var url = 'scripts/reqApi.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;
    var apiList = {};

    $(document).ready(function() {
 
        getApiName();

        $('input[type=radio][name=role]').change(function() {
            if (this.value == 'superAdmin') {
                $("#apiParamsContainer").empty();
                $('#api_id option').remove();
                $('#api_id').append('<option value="">--Please Select--</option>');
                var api = apiList.superAdmin;
                $.each(api.apiCommand, function(key, val) {
                    $('#api_id').append('<option value="' + api.apiID[key] + '" name="'+api.apiCommand[key]+'" site="'+api.apiSite[key]+'">' + val + '</option>');
                });
            }
            else if (this.value == 'admin') {
                $("#apiParamsContainer").empty();
                $('#api_id option').remove();
                $('#api_id').append('<option value="">--Please Select--</option>');
                var api = apiList.admin;
                $.each(api.apiCommand, function(key, val) {
                    $('#api_id').append('<option value="' + api.apiID[key] + '" name="'+api.apiCommand[key]+'" site="'+api.apiSite[key]+'">' + val + '</option>');
                });
            }
            else {
                $("#apiParamsContainer").empty();
                $('#api_id option').remove();
                $('#api_id').append('<option value="">--Please Select--</option>');
                var api = apiList.client;
                $.each(api.apiCommand, function(key, val) {
                    $('#api_id').append('<option value="' + api.apiID[key] + '" name="'+api.apiCommand[key]+'" site="'+api.apiSite[key]+'">' + val + '</option>');
                });
            }
        });
        
        $('#api_id').change(function() {
            getAPIParams();
        });
                      
        $('#btnTestAPI').click(function() {
                                             
            var validate = $('#formTestAPI').parsley().validate();
                                            
            if(validate) {
                                             
                var formData = {'command' : "testAPI",
                                'testCommand' : $("#api_id option:selected").attr("name"),
                                'userID' : $("#userID").val(),
                                'site' : $("#api_id option:selected").attr("site"),
                                };
                                             
                $('#apiParamsContainer').find('input').each(function() {
                    var key = $(this).attr("id");
                    var val = $(this).val();
                    formData[key] = val;
                });
                                             
                console.log(formData);
                  
                fCallback = showAPIDataOut;
                ajaxSend(url, formData, method, fCallback, 2, bypassBlocking, bypassLoading, 0);
            }
        });           
    });

    function getAPIParams() {
        
        var apiID = $("#api_id option:selected").val();
        if (apiID == '--Please Select--') {
            apiID = "";
        }
        
        var formData = {
                            'command' : "getAPIParams",
                            'apiID'   : apiID
                        };
        
        fCallback = showAPIParams;
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        
    }

    function showAPIParams(data) {
        console.log(data);
        
        $("#apiParamsContainer").empty();
        $("#dataOut").empty();
        if (data != null) {
            $.each(data.apiParams, function(key, val) {
               
               //console.log("key = " + key);
               //console.log("val = " + val);
                   
               str = '<div class="form-group"><label for="">'+val.paramsName+' ('+val.paramsType+')</label><input id="'+ val.paramsName +'" type="'+val.webInputType+'" class="form-control" name="'+val.paramsName+'" '+((val.compulsory == 1)? 'required': '')+'></div>';
               $( "#apiParamsContainer" ).append(str);
            });
        }
    }

    function showAPIDataOut(data) {
        var str = recursiveDisplay(data, 0);
        
        $("#dataOut").empty();
        $("#dataOut").append(str);
    }

    function recursiveDisplay(data, count) {
        
        var str = "";
        console.log("Count:"+count+", data:"+data);
        
        $.each(data, function(key, val) {
           
            if (count > 0) {
                var tabstr = "";
                for (i=1; i<=count; i++){
                    tabstr += "\t";
                }
                str += tabstr;
            }
           
            if( Object.prototype.toString.call( val ) === '[object Object]' || Object.prototype.toString.call( val ) === '[object Array]' ) {
           
                str += key + " :\n";
                str += recursiveDisplay(val, (count+1));
               
            }
            else {
                str += key + " : " + val + "\n";
            }
               
        });
    
        return str;
    }

    function returnApiList(returnApiList) {
        apiList = returnApiList;
        var api = apiList.superAdmin;
        $.each(api.apiCommand, function(key, val) {
            $('#api_id').append('<option value="' + api.apiID[key] + '" name="'+api.apiCommand[key]+'" site="'+api.apiSite[key]+'">' + val + '</option>');
        });
    }
</script>
</html>
