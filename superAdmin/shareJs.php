<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script> 
<script src="js/detect.js"></script>
<script src="js/fastclick.js"></script>
<script src="js/jquery.slimscroll.js"></script>
<script src="js/jquery.blockUI.js"></script>
<script src="js/waves.js"></script>
<script src="js/wow.min.js"></script>
<script src="js/jquery.nicescroll.js"></script>
<script src="js/jquery.scrollTo.min.js"></script>

<!-- Datatables-->
<!--
<script src="plugins/datatables/jquery.dataTables.min.js"></script>
<script src="plugins/datatables/dataTables.bootstrap.js"></script>
<script src="plugins/datatables/dataTables.buttons.min.js"></script>
<script src="plugins/datatables/buttons.bootstrap.min.js"></script>
<script src="plugins/datatables/jszip.min.js"></script>
<script src="plugins/datatables/pdfmake.min.js"></script>
<script src="plugins/datatables/vfs_fonts.js"></script>
<script src="plugins/datatables/buttons.html5.min.js"></script>
<script src="plugins/datatables/buttons.print.min.js"></script>
<script src="plugins/datatables/dataTables.fixedHeader.min.js"></script>
<script src="plugins/datatables/dataTables.keyTable.min.js"></script>
<script src="plugins/datatables/dataTables.responsive.min.js"></script>
<script src="plugins/datatables/responsive.bootstrap.min.js"></script>
<script src="plugins/datatables/dataTables.scroller.min.js"></script>
-->

<!-- Datatable init js -->
<!--<script src="pages/datatables.init.js"></script>-->

<!-- App js -->
<script src="js/jquery.core.js"></script>
<script src="js/jquery.app.js"></script>

<!-- Plugins Js -->
<script src="plugins/switchery/switchery.min.js"></script>
<script src="plugins/bootstrap-tagsinput/dist/bootstrap-tagsinput.min.js"></script>
<script type="text/javascript" src="plugins/multiselect/js/jquery.multi-select.js"></script>
<script type="text/javascript" src="plugins/jquery-quicksearch/jquery.quicksearch.js"></script>
<script src="plugins/select2/dist/js/select2.min.js" type="text/javascript"></script>
<script src="plugins/bootstrap-touchspin/dist/jquery.bootstrap-touchspin.min.js" type="text/javascript"></script>
<script src="plugins/bootstrap-inputmask/bootstrap-inputmask.min.js" type="text/javascript"></script>
<script src="plugins/moment/moment.js"></script>
<script src="plugins/timepicker/bootstrap-timepicker.min.js"></script>
<script src="plugins/mjolnic-bootstrap-colorpicker/dist/js/bootstrap-colorpicker.min.js"></script>
<script src="plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>
<script src="plugins/bootstrap-daterangepicker/daterangepicker.js"></script>
<script src="plugins/bootstrap-maxlength/bootstrap-maxlength.min.js" type="text/javascript"></script>

<!-- Validation js (Parsleyjs) -->
<script type="text/javascript" src="plugins/parsleyjs/dist/parsley.min.js"></script>

<!-- T2ii custom standard js -->
<script src="js/logout.js"></script>
<script src="js/search.js"></script>
<!-- <script src="js/general.js"></script> -->
<?php echo '<script src="js/general.js?ts='.time().'"></script>'; ?>
<?php echo '<script src="js/table.js?ts='.time().'"></script>'; ?>

<script>
        // Date Picker
//            jQuery('#datepicker').datepicker();
//            jQuery('#datepicker-autoclose').datepicker({
//                autoclose: true,
//                todayHighlight: true
//            });
//            jQuery('#datepicker-inline').datepicker();
//            jQuery('#datepicker-multiple-date').datepicker({
//                format: "mm/dd/yyyy",
//                clearBtn: true,
//                multidate: true,
//                multidateSeparator: ","
//            });
//            jQuery('#date-range').datepicker({
//                toggleActive: true
//            });

            // Time Picker
            jQuery('#timepicker').timepicker({
                defaultTIme : false
            });
            jQuery('#timepicker2').timepicker({
                defaultTIme : false
//                showMeridian : false
            });
            jQuery('#timepicker3').timepicker({
                minuteStep : 15
            });
</script>

<script type="text/javascript">
    $(document).ready(function() {
        <?php
            $sessionTimeOut = isset($_SESSION['sessionTimeOut'])?$_SESSION['sessionTimeOut']:time();
            $sessionExpireTime = isset($_SESSION['sessionExpireTime'])?$_SESSION['sessionExpireTime']:0;
        ?>
        
        window.ajaxEnabled = true;
        
        if((pageName == 'pageLogin.php') || (pageName == 'accessDenied.php'))
            return true;
        
        var pageName = "<?php echo basename($_SERVER['PHP_SELF']);?>";
        var currentTime = "<?php echo time();?>";
        var sessionTimeOut = "<?php echo $sessionTimeOut;?>";
        var sessionExpireTime = "<?php echo $sessionExpireTime;?>";
        
        if((currentTime - sessionTimeOut) > sessionExpireTime) {
            $.ajax({
                type: 'POST',
                url: 'scripts/reqLogin.php',
                data: {type : "logout"},
                success	: function(result) {
                },
                error	: function(result) {
                    alert("Error!");
                }
            });
            errorHandler(3, 'Session expired.');
            window.ajaxEnabled = false;
        }
        else {
            <?php $_SESSION["sessionTimeOut"] = time(); //Reset session ?>
        }
    });
</script>