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
                             <a href="client.php" class="btn btn-primary btn-md waves-effect waves-light m-b-20"><i class="md md-add"></i>Back</a>
                        </div><!-- end col -->
                    </div>

                     <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box p-b-0">
                                <div class="row">
                                    <ul class="nav nav-tabs">
                                        <li><a href="clientDetails.php?id=<?php echo $_GET['id']; ?>" id="clientDetailsTab">Details</a></li>
                                        <li><a href="clientSetting.php?id=<?php echo $_GET['id']; ?>" >Settings</a></li>
                                        <li class="active"><a href="sponsorTree.php?id=<?php echo $_GET['id']; ?>">Sponsor Tree</a></li>
                                    </ul>
                                    <div class="tab-content m-b-30">
                                        <div id="settings" class="tab-pane fade in active">
                                            <div class="row">
                                                <div role="form">

                                                    <div class=" form-group search-box col-md-4">
                                                        <input type="text" id="searchInput" class="form-control product-search" autocomplete="off" placeholder="Search by Username" >
                                                        <button class="btn-search" id="targetSearch"><i class="fa fa-search"></i></button>
                                                    </div>
                                                    <!-- alert box -->
                                                    <div id="settings" class="tab-pane fade in active col-md-12">
                                                        <div class="row">
                                                            <form role="form">
                                                                <div id="clientSettingList" class="tab-content b-0 m-b-0 p-t-0">
                                                                    <div id="alertMsg" class="alert" style="display:none;"></div>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>

                                                    <!-- start vertical box -->
                                                    <div class="col-md-12">

                                                        <table class="table m-0 table-centered">
                                                        <header role="banner">
                                                          <!-- Start First Row -->
                                                            <nav class="nav" role="navigation">
                                                                <ul id="navListTreeView">

                                                                    <!-- Start Group 1 -->
                                                                    <input id="group-1" type="checkbox" hidden />
                                                                    <label class="targetVertical" id="mainListVertical" for="group-1">
                                                                    </label>

                                                                    <ul class="group-list">
                                                                        <label>
                                                                            <ul id="subListVertical" class="noList">
                                                                            </ul>
                                                                        </label>
                                                                    </ul>
                                                                </ul>
                                                            </nav>
                                                        </header>
                                                        </table>
                                                    </div>
                                                    <!-- start vertical box -->
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
    // Get the UTC offset in seconds
    var offsetSecs = getOffsetSecs();
    // Initialize the arguments for ajaxSend function
    var url = 'scripts/reqClient.php';
    var method = 'POST';
    var debug = 0;
    var bypassBlocking = 0;
    var bypassLoading = 0;
    var clientId = $.urlParam('id');
        
    var fCallback = defaultLoad;
    $(document).ready(function() {

        if(clientId) {
            var formData = {
                'command': 'getSponsorTree',
                'clientId': clientId,
                'viewType' : "vertical",
                'offsetSecs' : offsetSecs
            };
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading);
        }

        $('button#targetSearch').click(function() {
            var targetUsername = $('#searchInput').val();
            getSponsorTree("", clientId, targetUsername);

        });
        
    });


    function defaultLoad(data, message) {
        var content = "";

        if(data.target){    
            $('#alertMsg').removeClass('alert-success').html('').hide();
            
            if(!data.targetID){
                var date = data.target["attr"].createdAt;
                content += ' <strong> '+data.target["attr"].username+'</strong> <span class="text-muted m-l-10">&nbsp'+date+'</span>';
                $("#mainListVertical").empty().append(content);
            }
            var downlineCount = data.downline.length;
            var content = "";
            for (var i = 0; i < downlineCount; i++) {
                var date = data.downline[i]["attr"].createdAt;
                var targetID = data.downline[i]["attr"].id;
                var targetUsername = data.downline[i]["attr"].username;
                var disabled = data.downline[i]["attr"].disabled;
                var suspended = data.downline[i]["attr"].suspended;
                var freezed = data.downline[i]["attr"].freezed;
                var popOverContent = 'Disabled : ' + disabled + '</br>Suspended : ' + suspended + '</br>Freezed : ' + freezed +'</br>';
                
                content += '<li>';
                if(data.downline[i]["attr"].downlineCount > 0)
                    content += '<i id="icon-' + targetID + '" class="m-r-10 fa fa-arrow-right" onclick="getSponsorTree('+ targetID +' , ' + clientId + ');" targetID="' + targetID + '"></i>';
                
                // Documentation on using bootstrap popover
                // https://v4-alpha.getbootstrap.com/components/popovers/
                // data-trigger="focus" and tabindex="0" is for dismissable popover on next click
                // Means the popover will close when click any other stuff on that page.
                content += '<a id="popover-' + targetID + '" onclick="openPopover(' + targetID + ')" title="Status" data-toggle="popover" tabindex="0" data-trigger="focus" data-placement="auto bottom" data-container="body" data-html="true" data-content="' + popOverContent + '" class="greyText"><strong> ' + targetUsername + ' ' + targetID + '</strong></a> <span class="text-muted m-l-10">&nbsp'+date+'</span>';
                content += '</li>';

                content += '<ul id="' + targetID + '" class="noList">';
                content += '</ul>';
            };
                if(!data.targetID) var listId = "subListVertical";
                else var listId = data.targetID;
                $("#" + listId).empty().append(content);

        }else
            $('#alertMsg').addClass('alert-success').html('<span>'+message+'</span>').show();

    }

    function getSponsorTree(targetId, clientId, targetUsername){
        bypassLoading = 1;
        // To toggle the show/hide of downline
        if($("#" + targetId).find('ul').length) {
            $("#icon-" + targetId).removeClass('rotate90');
            $("#" + targetId).empty();
        }else{
            $("#icon-" + targetId).addClass('rotate90');
            var formData = {
                    'command': 'getSponsorTree',
                    'clientId': clientId,
                    'targetId': targetId,
                    'targetUsername': targetUsername,
                    'viewType' : "vertical",
                    offsetSecs : offsetSecs
                };
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading);
        }

    }
</script>
</body>
</html>