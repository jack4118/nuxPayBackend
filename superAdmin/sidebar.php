<?php 
    session_start();

    // Get current page name
    $thisPage = basename($_SERVER['PHP_SELF']);

    // Check the session for this page
    if(!isset ($_SESSION['access'][$thisPage]))
        echo '<script>window.location.href="accessDenied.php";</script>';

    foreach($_SESSION["permission"] as $item){
//        switch($item->name){
//            case "Users":
//                $icon = "zmdi-accounts-alt";
//            break;
//
//            case "Settings":
//                $icon = "zmdi-settings";
//            break;
//
//            case "Web Services":
//                $icon = "zmdi-collection-text";
//            break;
//
//            case "Notification":
//                $icon = "zmdi-email";
//            break;
//
//            case "Notification":
//                $icon = "zmdi-email";
//            break;
//
//            default:
//                $icon = "zmdi-collection-text";
//            break;
//        }

        
        $array[$item['id']]['type'] = $item['type'];
        $array[$item['id']]['filePath'] = $item['file_path'];
        $array[$item['id']]['parentID'] = $item['parent_id'];
        $array[$item['id']]['id'] = $item['id'];
        $array[$item['id']]['name'] = $item['name'];

        if($item['icon_class_name'] !="")
            $icon = $item['icon_class_name'];

        $array[$item['id']]['icon'] = $icon;

        if($item['file_path'] == "" && $item['type'] == 'Menu')
            $array[$item['id']]['subMenu'] = [];

    }

    foreach($array as $id => $item){
        if($item["type"]== "Menu"){
            $sideBarAry[$id] = $item;

        }else if($item["type"] == "Sub Menu"){
            // $subMenuAry[] = $item;
            array_push($sideBarAry[$item["parentID"]]["subMenu"], $item);

            unset($subMenuAry);
        }

    }
                
?>
<div class="left side-menu">

    <div class="sidebar-inner slimscrollleft">

        <!-- User -->
        <!-- <div class="user-box">
            <div class="user-img">
                <img src="images/adminPhoto.jpg" alt="user-img" title="Abcd" class="img-circle img-thumbnail img-responsive">
                <div class="user-status offline"><i class="zmdi zmdi-dot-circle"></i></div>
            </div>
            <h5><a href="#">Super Admin</a> </h5>
            <ul class="list-inline">
                 <li>
                    <a href="#" >
                        <i class="zmdi zmdi-settings"></i>
                    </a>
                </li>

                <li>
                    <a href="#" class="text-custom">
                        <i class="zmdi zmdi-power"></i>
                    </a>
                </li>
            </ul>
        </div> -->
        <!-- End User -->

        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <ul>
                <li class="text-muted menu-title">Navigation</li>

                <?php 

                    foreach($sideBarAry as $sideBar){
                        if($sideBar["type"] == "Menu" && $sideBar["filePath"] != ""){
                            echo '<li>';
                            echo '<a href="'.$sideBar["filePath"].'" class="waves-effect"><i class="zmdi '.$sideBar["icon"].'"></i> <span>'.$sideBar["name"].'</span> </a>';
                            echo '</li>';
                        }

                        if($sideBar["type"] == "Menu" && $sideBar["filePath"] == ""){
                            echo '<li class="has_sub">';
                                echo '<a href="javascript:void(0);" class="waves-effect"><i class="zmdi '.$sideBar["icon"].'"></i> <span>'.$sideBar["name"].'</span> <span class="menu-arrow"></span></a>';
                                echo '<ul class="list-unstyled" style="display: none;">';
                                foreach($sideBar["subMenu"] as $subMenu){
                                    echo '<li><a href="'.$subMenu["filePath"].'">'.$subMenu["name"].'</a></li>';
                                }
                                echo '</ul>';
                            echo '</li>';
                        }


                    }
                ?>



<!--
                 <li class="has_sub">
                    <a href="javascript:void(0);" class="waves-effect"><i class="zmdi zmdi-collection-text"></i> <span>Web Services</span> <span class="menu-arrow"></span></a>
                    <ul class="list-unstyled" style="display: none;">
                        <li><a href="webServices.php">Web Services Log</a></li>
                        <li><a href="api.php">API List</a></li>
                        <li><a href="testApi.php">Test API</a></li>
                    </ul>
                </li> 
-->


                <!-- <li class="has_sub">
                    <a href="javascript:void(0);" class="waves-effect"><i class="zmdi zmdi-account-calendar"></i> <span>Clients</span> <span class="menu-arrow"></span></a>
                    <ul class="list-unstyled" style="display: none;">
                        <li><a href="client.php">All</a></li>
                        <li><a href="#">Credit</a></li>
                        <li><a href="#">Tree</a></li>
                        <li><a href="#">Status</a></li>
                        <li><a href="#">Account Status</a></li>
                    </ul>
                </li> -->

<!--
                <li class="has_sub">
                    <a href="javascript:void(0);" class="waves-effect"><i class="zmdi zmdi-email"></i> <span>Notification</span> <span class="menu-arrow"></span></a>
                    <ul class="list-unstyled" style="display: none;">
                        <li><a href="messageCode.php">Message Code</a></li>
                        <li><a href="messageAssigned.php">Message Assigned</a></li>
                        <li><a href="messageOut.php">Message Out</a></li>
                        <li><a href="messageError.php">Message Error</a></li>
                        <li><a href="TestMessage.php">Test Message</a></li>
                    </ul>
                </li>

                <li class="has_sub">
                    <a href="javascript:void(0);" class="waves-effect"><i class="zmdi zmdi-accounts-alt"></i> <span>Users</span> <span class="menu-arrow"></span></a>
                    <ul class="list-unstyled" style="display: none;">
                        <li><a href="user.php">User List</a></li>
                        <li><a href="role.php">Roles</a></li>
                    </ul>
                </li>
                

                <li class="has_sub">
                    <a href="javascript:void(0);" class="waves-effect"><i class="zmdi zmdi-format-align-justify"></i> <span>Modules</span> <span class="menu-arrow"></span></a>
                    <ul class="list-unstyled" style="display: none;">
                        <li><a href="moduleSa.php">Super Admin</a></li>
                    </ul>
                </li>
-->


<!--
                <li class="has_sub">
                    <a href="javascript:void(0);" class="waves-effect"><i class="zmdi zmdi-window-restore"></i> <span>Logs</span> <span class="menu-arrow"></span></a>
                    <ul class="list-unstyled" style="display: none;">
                        <li><a href="systemLog.php">System Log</a></li>
                    </ul>
                </li>
-->


                <!-- <li>
                    <a href="systemStatus.php" class="waves-effect"><i class="zmdi zmdi-dock"></i> <span>System Status</span> </a>
                </li> -->

                <!-- <li>
                    <a href="systemSettings.php" class="waves-effect"><i class="zmdi zmdi-dock"></i> <span>System Settings</span> </a>
                </li> -->

<!--
                 <li class="has_sub">
                    <a href="javascript:void(0);" class="waves-effect"><i class="zmdi zmdi-translate"></i> <span>Languages</span> <span class="menu-arrow"></span></a>
                    <ul class="list-unstyled" style="display: none;">
                        <li><a href="languageList.php">Language List</a></li>
                        <li><a href="languageCode.php">Language Code</a></li>
                    </ul>
                </li>

                <li class="has_sub">
                    <a href="javascript:void(0);" class="waves-effect"><i class="zmdi zmdi-translate"></i> <span>Setting</span> <span class="menu-arrow"></span></a>
                    <ul class="list-unstyled" style="display: none;">
                        <li><a href="systemSettings.php">System Setting</a></li>
                    </ul>
                </li>
-->

<!--                 <li class="has_sub">
                    <a href="javascript:void(0);" class="waves-effect"><i class="zmdi zmdi-dock"></i> <span>Upgrades</span> <span class="menu-arrow"></span></a>
                    <ul class="list-unstyled" style="display: none;">
                        <li><a href="upgradeNew.php">New Upgrades</a></li>
                        <li><a href="upgradeHistory.php">Upgrade History</a></li>
                    </ul>
                </li> -->




<!--
                <li>
                    <a href="settings.php" class="waves-effect"><i class="zmdi zmdi-settings"></i> <span>Settings</span> </a>
                </li>
-->

            </ul>
            <div class="clearfix"></div>
        </div>
        <!-- Sidebar -->
        <div class="clearfix"></div>

    </div>

</div>