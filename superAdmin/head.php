<head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="">
        <meta name="" content="">

        <!-- App Favicon -->
        <link rel="shortcut icon" href="images/favicon.ico">

        <!-- App title -->
        <title>T2II</title>

        <!-- DataTables -->
<!--
        <link href="plugins/datatables/jquery.dataTables.min.css" rel="stylesheet" type="text/css" />
        <link href="plugins/datatables/buttons.bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="plugins/datatables/fixedHeader.bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="plugins/datatables/responsive.bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="plugins/datatables/scroller.bootstrap.min.css" rel="stylesheet" type="text/css" />
-->

        <!-- App CSS -->
        <link href="css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="css/components.css" rel="stylesheet" type="text/css" />
        <link href="css/icons.css" rel="stylesheet" type="text/css" />
        <link href="css/pages.css" rel="stylesheet" type="text/css" />
        <link href="css/menu.css" rel="stylesheet" type="text/css" />
        <link href="css/responsive.css" rel="stylesheet" type="text/css" />
        <link href="css/custom.css" rel="stylesheet" type="text/css" />
        <?php echo '<link href="css/core.css?ts='.time().'" rel="stylesheet" type="text/css" />'; ?>

        <!-- HTML5 Shiv and Respond.js IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
        <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
        <![endif]-->

        <!-- Plugins css-->
        <link href="plugins/bootstrap-tagsinput/dist/bootstrap-tagsinput.css" rel="stylesheet" />
        <link href="plugins/multiselect/css/multi-select.css"  rel="stylesheet" type="text/css" />
        <link href="plugins/select2/dist/css/select2.css" rel="stylesheet" type="text/css">
        <link href="plugins/select2/dist/css/select2-bootstrap.css" rel="stylesheet" type="text/css">
        <link href="plugins/bootstrap-touchspin/dist/jquery.bootstrap-touchspin.min.css" rel="stylesheet" />
        <link href="plugins/switchery/switchery.min.css" rel="stylesheet" />
        <link href="plugins/timepicker/bootstrap-timepicker.min.css" rel="stylesheet">
        <link href="plugins/mjolnic-bootstrap-colorpicker/dist/css/bootstrap-colorpicker.min.css" rel="stylesheet">
        <link href="plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css" rel="stylesheet">
        <link href="plugins/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet">

        <script src="js/modernizr.min.js"></script>
</head>
<body>
<div id="canvasLoader" class="hide">
    <div id="canvasLoaderContainer">
        <div>
            <i class="fa fa-spinner fa-spin fa-5x"></i>
        </div>
    </div>
</div>

<div class="modal fade" id="canvasMessage" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">
				    <span aria-hidden="true">&times;</span>
				</button>
				<h4 class="modal-title">
				</h4>
            </div>
            <div class="modal-body">
				<div id="canvasAlertMessage" class="alert">
				</div>
            </div>
            <div class="modal-footer">
				<button id="canvasCloseBtn" type="button" class="btn btn-primary waves-effect waves-light" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>