<?php 
    session_start();
    include_once($_SERVER["DOCUMENT_ROOT"].'/include/PHPExcel.php');
    include_once($_SERVER["DOCUMENT_ROOT"].'/include/PHPExcel/Writer/Excel2007.php');
    // Get current page name
    $thisPage = basename($_SERVER['PHP_SELF']);

    // Check the session for this page
    if(!isset ($_SESSION['access'][$thisPage]))
        echo '<script>window.location.href="accessDenied.php";</script>';
    else
        $_SESSION['lastVisited'] = $thisPage;

    //Download the Language Codes.
    if (isset($_SESSION['language']['data']) && !empty($_SESSION['language']['data'])) {
            $objPHPExcel = new PHPExcel(); 
            // Set the active Excel worksheet to sheet 0
            $objPHPExcel->setActiveSheetIndex(0);
            if($_SESSION['language']['code'] == 1) {
                // Set the Headers.
                $alpha = 'A';
                for ($i = 0; $i < 83; $i++) {
                    $objPHPExcel->getActiveSheet()->getCell($alpha.'1')->setValueExplicit($_SESSION['language']['data'][$i], PHPExcel_Cell_DataType::TYPE_STRING);
                    $alpha++;
                }
            } elseif ($_SESSION['language']['code'] == 0) {
                $rowCount = 2;
                foreach($_SESSION["language"]['data'] as $rows) {
                   $col = 0;
                   $headers = array_keys($rows);

                    // Set the Headers.
                    $alpha = 'A';
                    for ($i = 0; $i < 83; $i++) {
                        $objPHPExcel->getActiveSheet()->getCell($alpha.'1')->setValueExplicit($headers[$i], PHPExcel_Cell_DataType::TYPE_STRING);
                        $alpha++;
                    }

                    // Set the Data in Excel File.
                    foreach($rows as $key=>$value) {
                        $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col, $rowCount, $value);
                        $col++;
                    }
                    $rowCount++;
                }
            }
            //Delete the Session.
            unset($_SESSION['language']);
            $fileName = 'language_code_'.date("Y-m-d_H-i-s").'.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename=' . $fileName);
            header('Cache-Control: max-age = 0');
            $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
            ob_end_clean();
            $objWriter->save('php://output');
            exit();
    }
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
                        <div class="col-lg-12">
                            <div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
                                <div class="panel panel-default bx-shadow-none">
                                    <div class="panel-heading" role="tab" id="headingOne">
                                        <h4 class="panel-title">
                                            <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseOne" aria-expanded="true" aria-controls="collapseOne" class="collapse">
                                                Search
                                            </a>
                                        </h4>
                                    </div>
                                    <div id="collapseOne" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingOne" aria-expanded="true" style="">
                                        <div class="panel-body">
                                            <form id="searchLanguageCode" role="form">
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="code">Content Code</label>
                                                    <input type="text" class="form-control" dataName="code" dataType="text">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="language">Language </label>
                                                    <input type="text" class="form-control" dataName="language" dataType="text">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="module">Module</label>
                                                    <input type="text" class="form-control" dataName="module" dataType="text">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="site">Site</label>
                                                    <input type="text" class="form-control" dataName="site" dataType="text">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="category">Category</label>
                                                    <input type="text" class="form-control" dataName="category" dataType="text">
                                                </div>
                                                <div class="col-sm-4 form-group">
                                                    <label class="control-label" for="" data-th="content">Content</label>
                                                    <input type="text" class="form-control" dataName="content" dataType="text">
                                                </div>
                                                <div class="col-sm-12">
                                                    <button id="searchLanguageCodeBtn" type="button" class="btn btn-primary waves-effect waves-light">Search</button>
                                                    <button id="resetLanguageCode" type="button" class="btn btn-default waves-effect waves-light">Reset</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <!-- End row -->

                <!-- <div class="row">
                    <div class="col-lg-12">
                        <div class="panel-group">
                            <div class="panel panel-default bx-shadow-none">
                                <div class="panel-heading">
                                    <h4 class="panel-title">Import</h4>
                                </div>
                                <div id="collapseOne" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingOne" aria-expanded="true" style="">
                                <div class="panel-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <input id="file" type="file" name="file">
                                                </div>
                                                <div class="form-group">
                                                    <button id="upload_excel" class="btn btn-primary waves-effect waves-light">Import</button>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <a id="exportLanguageCodes" class="btn btn-primary waves-effect w-md waves-light m-b-20">Export Translation</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div> -->


            <div class="row">
                <div class="col-lg-12">
                    <div class="card-box p-b-0">
                        <a href="newLanguageCode.php" type="button" class="btn btn-primary waves-effect w-md waves-light m-b-20" title="New Language Translation">New</a>
                        <a id="exportLanguageCodes" type="button" class="btn btn-primary waves-effect w-md waves-light m-b-20" title="Export Translations">Export
                        <span style="padding-top: 0px;"><i class="fa fa-file-excel-o"></i></span></a>

                        <form>
                            <div id="basicwizard" class="pull-in">
                                <div class="tab-content b-0 m-b-0 p-t-0">
                                    <div id="alertMsg" class="text-center alert" style="display: none;"></div>
                                    <div id="languageCodeListDiv" class="table-responsive"></div>
                                    <span id="paginateText"></span>
                                    <div class="text-center">
                                        <ul class="pagination pagination-md" id="pagerLanguageCodeList"></ul>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- <div class="row">
                <div class="col-lg-12">
                    <div class="panel-group">
                        <div class="panel panel-default bx-shadow-none">
                            <div class="panel-heading" role="tab" id="headingOne">
                                <div class="pull-right">
                                    <a href="newLanguageCode.php" class="btn btn-primary waves-effect w-md waves-light m-b-20" style="padding-top: 0px;" title="New Translation">New</a>

                                    <a id="exportLanguageCodes" class="btn btn-primary waves-effect w-md waves-light m-b-20" style="padding-top: 0px;" title="Export Translations">Export
                                    <span style="padding-top: 0px;"><i class="fa fa-file-excel-o"></i></span></a>
                                </div>
                                <h4 class="panel-title">Language Translation</h4>
                            </div>
                            <div class="panel-body">
                                <div id="basicwizard" class="pull-in">
                                    <div class="tab-content b-0 m-b-0 p-t-0">
                                        <div id="languageCodeMsg" class="text-center alert" style="display: none;"></div>
                                        <div id="languageCodeListDiv" class="table-responsive"></div>
                                        <div class="text-center">
                                            <ul class="pagination pagination-md" id="pagerLanguageCodeList"></ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div> -->
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
</body>
    <script>
        // Initialize all the id in this page
        var divId = 'languageCodeListDiv';
        var tableId = 'languageCodeListTable';
        var pagerId = 'pagerLanguageCodeList';
        var btnArray = Array('edit','delete');
        var thArray = Array('ID',
                            'Content Code',
                            'Language',
                            'Module',
                            'Site',
                            'Category',
                            'Content'
                           );

        // Initialize the arguments for ajaxSend function
        var url = 'scripts/reqLanguageCode.php';
        var method = 'POST';
        var debug = 0;
        var bypassBlocking = 0;
        var bypassLoading = 0;
        var pageNumber = 1;

        var fCallback = loadDefaultListing;
        var formData = {command: 'getLanguageCodeList', pageNumber : pageNumber};

        $(document).ready(function() {
            // First load of this page
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);

            // Search function
            $('#searchLanguageCodeBtn').click(function() {
                pagingCallBack(pageNumber, loadSearch);
            });

            //Reset he API search from
            $('#resetLanguageCode').click(function() {
                $("#searchLanguageCode")[0].reset();
            });
        });

        function loadDefaultListing(data, message) {
            var tableNo;
            buildTable(data.languageCodeList, tableId, divId, thArray, btnArray, message, tableNo);
            pagination(pagerId, data.pageNumber, data.totalPage, data.totalRecord, data.numRecord);
        }

        function tableBtnClick(btnId) {
            var btnName = $('#'+btnId).attr('id').replace(/\d+/g, '');
            var tableRow = $('#'+btnId).parent('td').parent('tr');
            var tableId = $('#'+btnId).closest('table');

            if (btnName == 'edit') {
                var editId = tableRow.attr('data-th');
                var editUrl = 'editLanguageCode.php?id='+editId;
                window.location = editUrl;
            } else if (btnName == 'delete') {
                var canvasBtnArray = Array('Ok');
                var message = 'Are you sure you want to delete this Language Code.?';
                showMessage(message, '', 'Delete API', 'trash', '', canvasBtnArray);
                $('#canvasOkBtn').click(function() {
                    var tableColVal = tableRow.attr('data-th');
                    var formData = {
                        'command': 'deleteLanguageCode',
                        'deleteData' : tableColVal
                    };
                    fCallback = loadDelete;
                    ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
                });
            }
        }

        function loadSearch(data, message) {
            loadDefaultListing(data, message);
            $('#searchMsg').addClass('alert-success').html('<span>Search successful.</span>').show();
            setTimeout(function() {
                $('#searchMsg').removeClass('alert-success').html('').hide(); 
            }, 3000);
        }

        function loadDelete(data, message) {
            loadDefaultListing(data, message);
            $('#alertMsg').addClass('alert-success').html("<span>Language Code deleted successfully.</span>").show();
            setTimeout(function() {
                $('#alertMsg').removeClass('alert-success').html('').hide(); 
            }, 3000);
        }

        $('#exportLanguageCodes').on('click', function() {
            $.ajax({
                type: 'POST',
                url: 'scripts/reqLanguageCode.php',
                data: {command : "exportLanguageCodes"},
                dataType: 'text',
                success: function(data){
                    if (data) {
                        location.reload();
                    }
                }
            });
        });

        function pagingCallBack(pageNumber, fCallback){
            var searchId = 'searchLanguageCode';
            var searchData = buildSearchDataByType(searchId);

            if(pageNumber > 1) bypassLoading = 1;

            var formData = {
                command : "getLanguageCodeList",
                inputData: searchData,
                pageNumber: pageNumber
            };
            if(!fCallback)
                fCallback = loadDefaultListing;
            ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
        }
    </script>
</html>
