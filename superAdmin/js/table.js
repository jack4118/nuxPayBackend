/**
 * Javascript functions for tables
 * 
 **/

/** 
*   This function is to create a table
*   data should be in this format
*   {th1:[array],th2:[array],th3:[a,b,c,d,e]}
*   tableId - ID for your table
*   divId - ID of the div which will wrap your table
*   thArray - The column name of your table in db
*   btnArray - The amount of buttons you want to have in this table
*   message - The message to show if there is no data
*   tableNo - If this is set, replace ID with table numbering.
* 
**/
function buildTable(data, tableId, divId, thArray, btnArray, message, tableNo) {
    $('#'+divId).find('table#'+tableId).remove();
    $('#'+divId).prev().removeClass('alert-success').html('').hide();

    if(!data){
        $('#'+divId).prev().addClass('alert-success').html('<span>'+message+'</span>').show();
        return;
    }

    $('#'+divId).append('<table id="'+tableId+'" class="table table-striped table-bordered no-footer m-0"></table>');
    $('#'+divId).find('table#'+tableId).append('<thead><tr></tr></thead>');
    $('#'+divId).find('table#'+tableId).append('<tbody></tbody>');
    
    var objKey;
    objKey = Object.keys(data);
    
    // Remove this block of code once all listing done changing
    if(thArray.length < 2) {
        thArray = [];
        objKey = Object.keys(data);
        $.each(objKey, function(key, val) {
           thArray.push(val.split('_').join(' ')); 
        });
    }
    // Till here
    
    // Loop to insert table headers
    $.each(thArray, function(key, val) {
        $('#'+tableId).find('thead tr').append('<th>'+val+'</th>');
    });
    
    if(btnArray.length > 0) {
        $('#'+tableId).find('thead tr').append('<th></th>');
    }
    
    //Counter for iconArray
    var k = 0;
    iconArray = [];
    $.each(btnArray, function(key, val) {
        if (val == 'edit')
            iconArray.push('edit');
        else if (val == 'delete')
            iconArray.push('trash');
        else if (val == 'view')
            iconArray.push('eye');
    });
    
    var j = 0;
    while(j < data.length) {
        $('#'+tableId).find('tbody').append('<tr id='+j+'></tr>');
        
        var col = Object.keys(data[j]);
        for(var i=0; i<col.length; i++) {
            $('#'+tableId).find('tr#'+j).append('<td>'+data[j][col[i]]+'</td>');
        };

        $('#'+tableId).find('tr#'+j).attr('data-th', data[j][col[0]]);

        if(btnArray.length > 0) {
            $('#'+tableId).find('tr#'+j).append('<td></td>');
            k = 0;
            $.each(btnArray, function(key, value) {
                var tooltipTitle = value.charAt(0).toUpperCase() + value.slice(1);
                $('#'+tableId).find('tr#'+j+' td').last().append('<a data-toggle="tooltip" title="'+tooltipTitle+'" id="'+value+j+'" onclick="tableBtnClick(this.id)" class="m-t-5 m-r-10 btn btn-icon waves-effect waves-light btn-primary"><i class="fa fa-'+iconArray[k]+'"></i></a>');
                k++;
            });
        }
        j++;
    }
    
    if(tableNo) {
        var endRow = tableNo.pageNumber * tableNo.numRecord;
        var startRow = endRow - tableNo.numRecord + 1;
//        $('#'+tableId).find('th:first-child').html('No.');
        var tdRowCount = 1;
        while(startRow <= endRow){
            $('#'+tableId).find('tr:nth-child('+tdRowCount+') td:first-child').html(startRow);
            startRow++;
            tdRowCount++;
        }
    }
    // To initialize the tooltip
    $('[data-toggle="tooltip"]').tooltip();
}

/** 
*   This function is to create pagination on the table
*   tableId - ID for your table
*   pagerId - ID of the ul for the pagination index
*   showPrevNext - Show previous/next buttons on both sides (True/False)
*   showPageNum - Show page numbers in your pagination (True/False)
*   rowsPerPage - Rows of data to be showed in a page
**/

function paginateTable(tableId, pagerId, showPrevNext, showPageNum, rowsPerPage) {
    var pagerSize = 10;
    var pagerLeftInterval = 4;
    var pagerRightInterval = 4;
    var table = $('#'+tableId);
    var pager = $('#'+pagerId);
    
    if (table.hasClass('search_res')) {
        pager.find('li').remove();
        var tableRows = table.find('tbody tr.search_res');
    }
    else
        var tableRows = table.find('tbody tr');
    
    var totalRows = tableRows.length;
    var numPages = Math.ceil(totalRows/rowsPerPage);
    
    if(pagerSize > numPages) {
        pagerSize = numPages;
    }
    
    if(showPrevNext) {
        pager.append('<li class="link"><a href="#" class="firstLink">First</a></li>');
        pager.append('<li class="link"><a href="#" class="prevLink">«</a></li>');
    }
    var curr = 0;
    while(numPages > curr && showPageNum == 1) {
        pager.append('<li><a href="#" class="pageLink">'+(curr+1)+'</a></li>');
        curr++;
    }
    if(showPrevNext) {
        pager.append('<li class="link"><a href="#" class="nextLink">»</a></li>');
        pager.append('<li class="link"><a href="#" class="lastLink">Last</a></li>');
    }
    
    
    function paginateNum(pageNum) {
        pager.find('li').not('.link').hide();
        
        var pagerMin = pageNum - pagerLeftInterval;
        var pagerMax = pageNum + pagerRightInterval;
        if(pagerMin < 0) {
            pagerMin = 0;
            pagerMax = pagerSize;
        }
        pager.find('li').not('.link').slice(pagerMin, pagerMax+1).show();
    }
    
    pager.find('.pageLink:first').addClass('active');
    pager.find('.prevLink').hide();
    pager.find('.firstLink').hide();
    if (numPages <= 1) {
        pager.find('.nextLink').hide();
        pager.find('.lastLink').hide();
        // This is to hide the number links if there's only one page
        pager.find('.pageLink').hide();
    }
    pager.children().eq(2).addClass("active");
    paginateNum(1);
    tableRows.hide();
    tableRows.slice(0, rowsPerPage).show();
    
    pager.find('.pageLink').click(function() {
        var pageNum = $(this).html().valueOf()-1;
        goPage(pageNum);
        return false;
    });
    pager.find('.prevLink').click(function() {
        previousPage();
        return false;
    });
    pager.find('.nextLink').click(function() {
        nextPage();
        return false;
    });
    pager.find('.firstLink').click(function() {
        var pageNum = 0;
        goPage(pageNum);
        return false;
    });
    pager.find('.lastLink').click(function() {
        var pageNum = numPages-1;
        goPage(pageNum);
        return false;
    });
    
    function previousPage() {
        var go = parseInt(pager.find('li.active a').text())-1;
        go = go-1;
        goPage(go);
    }
    
    function nextPage() {
        var go = parseInt(pager.find('li.active a').text())-1;
        go = go+1;
        goPage(go);
    }
    
    function goPage(pageNum) {
        var start = pageNum * rowsPerPage;
        var end = start + rowsPerPage;
        
        tableRows.css('display','none').slice(start, end).show();
        
        if (pageNum >= 1) {
            pager.find('.prevLink').show();
            pager.find('.firstLink').show();
        }
        else {
            pager.find('.prevLink').hide();
            pager.find('.firstLink').hide();
        }
        
        if (pageNum < (numPages-1)) {
            pager.find('.nextLink').show();
            pager.find('.lastLink').show();
        }
        else {
            pager.find('.nextLink').hide();
            pager.find('.lastLink').hide();
        }
        paginateNum(pageNum);
        pager.children().removeClass("active");
        pager.children().eq(pageNum+1+1).addClass("active");
    }
}

/** 
*   This function is to search the table
*   Last 4 parameters is to rebuild the pagination.
*   Use back the same parameters so that they won't realise that the pagination is rebuild
*   tableId - ID for your table
*   searchId - ID of the input field for search
*   pagerId - ID of the ul for the pagination index
*   showPrevNext - Show previous/next buttons on both sides (True/False)
*   showPageNum - Show page numbers in your pagination (True/False)
*   rowsPerPage - Rows of data to be showed in a page
**/

function searchTable(tableId, searchId, pagerId, showPrevNext, showPageNum, rowsPerPage) {
    var input, filter, table;
    input = $('#'+searchId);
    filter = input.val().toUpperCase();
    table = $('#'+tableId);
    table.addClass('search_res');
    
    table.find('tbody tr').each(function() {
        var tr = $(this);
        tr.find('td').each(function() {
            var td = $(this);
            if(td.text().toUpperCase().indexOf(filter) > -1) {
                td.parent('tr').addClass('search_res');
                return false;
            }
            else {
                td.parent('tr').removeClass('search_res');
                td.parent('tr').hide();
            }
        });
    });
    
    var i=0;
    table.removeClass('table-striped');
    table.find('tr.search_res').each(function() {
        var tr = $(this);
        if (i % 2 == 0) {
            tr.css('background-color', '#f4f8fb');
        }
        else {
            tr.css('background-color', '#fff');
        }
        i++;
    });
    
    paginate_table(tableId, pagerId, showPrevNext, showPageNum, rowsPerPage);
}

function pagination(pagerId, pageNumber, totalPage, totalRecord, numRecord) {
    var pager = $('#'+pagerId);
    var endRow = pageNumber * numRecord;
    var startRow = endRow - numRecord + 1;
    
    var pagerSize = 10;
    var pagerLeftInterval = 4;
    var pagerRightInterval = 4;
    
    var spanText = pager.parent('div').prev();
    spanText.html('');
    pager.find('li').remove();
    if(!totalPage) return;

    if (endRow > totalRecord)
        endRow = totalRecord;
    spanText.html('Showing ' + startRow + ' - ' + endRow + ' of ' + totalRecord + ' records.');

    if(pagerSize > totalPage) {
        pagerSize = totalPage;
    }
        


    if(pageNumber > 1) {
        pager.append('<li class="link"><a href="#" class="firstLink">First</a></li>');
        pager.append('<li class="link"><a href="#" class="prevLink">«</a></li>');
    }
    var curr = 0;
    while(totalPage > curr && totalPage > 1) {
        pager.append('<li><a href="#" class="pageLink">'+(curr+1)+'</a></li>');
        curr++;
    }
    if(pageNumber < totalPage) {
        pager.append('<li class="link"><a href="#" class="nextLink">»</a></li>');
        pager.append('<li class="link"><a href="#" class="lastLink">Last</a></li>');
    }
    
    function paginateNum(pageNum) {
        pager.find('li').not('.link').hide();
        pageNum-=1;
        var pagerMin = pageNum - pagerLeftInterval;
        var pagerMax = pageNum + pagerRightInterval;
        if(pagerMin < 0) {
            pagerMin = 0;
            pagerMax = pagerSize;
        }
        pager.find('li').not('.link').slice(pagerMin, pagerMax+1).show();
    }
    
    var eq = parseInt(pageNumber)-1;

    pager.find('li').not('.link').eq(eq).addClass("active");
    paginateNum(parseInt(pageNumber));
    
    pager.find('.pageLink').click(function() {
        var pageNum = $(this).html().valueOf();
        goPage(pageNum);

    });
    pager.find('.prevLink').click(function() {
        var pageNum = parseInt(pager.find('li.active a').text())-1;
        goPage(pageNum);
    });
    pager.find('.nextLink').click(function() {
        var pageNum = parseInt(pager.find('li.active a').text())+1;
        goPage(pageNum);
    });
    pager.find('.firstLink').click(function() {
        var pageNum = 0;
        goPage(pageNum);
    });
    pager.find('.lastLink').click(function() {
        var pageNum = totalPage;
        goPage(pageNum);
    });
    
    function goPage(pageNum) {
        paginateNum(pageNum);
        pager.children().removeClass("active");
        pager.children().eq(pageNum+1).addClass("active");
        pagingCallBack(pageNum);

    }
}