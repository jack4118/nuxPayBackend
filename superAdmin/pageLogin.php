<!DOCTYPE html>
<html>
    <?php include("head.php"); ?>

        <div class="account-pages"></div>
        <div class="clearfix"></div>
        <div class="wrapper-page">
            <div class="text-center">
                <a href="pageLogin.php" class="logo"><span>T2<span>II</span></span></a>
                <h5 class="text-muted m-t-0 font-600">Super Admin</h5>
            </div>
        	<div class="m-t-40 card-box text-center">
                <div id="loginError" class="alert alert-danger" style="display: none;">
                </div>
                <div class="text-center">
                    <h4 class="text-uppercase font-bold m-b-0">Sign In</h4>
                </div>
                <div class="panel-body">
                    <form class="form-horizontal m-t-20" action="">

                        <div class="form-group ">
                            <div class="col-xs-12">
                                <input id="userName" class="form-control" type="text" required="" placeholder="Username">
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-xs-12">
                                <input id="password" class="form-control" type="password" required="" placeholder="Password">
                            </div>
                        </div>

<!--
                        <div class="form-group text-center m-t-30">
                            <div class="col-xs-12">
                                <button class="" >Sign In</button>
                            </div>
                        </div>
-->

                        <!-- <div class="form-group m-t-30 m-b-0">
                            <div class="col-sm-12">
                                <a href="pageRecoverPw.php" class="text-muted"><i class="fa fa-lock m-r-5"></i> Forgot your password?</a>
                            </div>
                        </div> -->
                    </form>
                    <div class="form-group text-center m-t-30">
                        <div class="col-xs-12">
                            <button id="pageLoginBtn" class="btn btn-primary waves-effect w-md waves-light m-b-20" onclick="validateForm();" >Sign In</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- end card-box-->

        </div>
        <!-- end wrapper page -->
        
    	<script>
            var resizefunc = [];
        </script>

    <!-- jQuery  -->
    <?php include("shareJs.php"); ?>
    
    <script type="text/javascript">
        $('.wrapper-page').keypress(function(e){
            if(e.which == 13){//Enter key pressed
                $('#pageLoginBtn').click();//Trigger enter button click event
            }
        });
        function validateForm() {
            showCanvas();
            $('#loginError').css('display', 'none');
            var userName = $('#userName').val();
            var password = $('#password').val();
            var formData = {
				'command': 'superAdminLogin',
                'username': userName,
                'password': password
            };
            
            $.ajax({
				type: 'POST', // define the type of HTTP verb we want to use (POST for our form)
				url: 'scripts/reqLogin.php', // the url where we want to POST
				data: formData, // our data object
				dataType: 'text', // what type of data do we expect back from the server
				encode: true
            })
            .done(function(data) {
                var obj = JSON.parse(data);
                hideCanvas();
				if(obj.status == "ok") {
                    $('#loginError').html('').css('display', 'none');
                    window.location.href = "webServices.php";
				}
                else {
                    var message = 'Invalid credentials';
                    if (obj.statusMsg != '')
                        message = obj.statusMsg;
                    $('#loginError').html('<span>' + message + '</span>').css('display', 'block');
                }
            });
        }
    </script>
</html>
