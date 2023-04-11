/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the Message Assigned Related Database code.
 * Date  01/08/2017.
**///

// Initialize the arguments for ajaxSend function
var url             = 'scripts/reqJournalTables.php';
var method          = 'POST';
var debug           = 0;
var bypassBlocking  = 0;
var bypassLoading   = 0;
var fCallback       = loadFormDropdown;

// For loading the Journal Name Dropdown
function getJournalTableNames(action) {
    var formData = {
            "command" : "getJournalTableNames",
            "action"  : action
        };
        ajaxSend(url, formData, method, fCallback, debug, bypassBlocking, bypassLoading, 0);
}

function loadFormDropdown(data, message) {
    $('#table_name').find('option:not(:first-child)').remove();
    if (typeof(data) != 'undefined') {
        tblName = data.journalNames;
        $.each(tblName["tableName"], function(key, val) {
            var tableName = val;
            $('#table_name').append('<option value="' + tableName + '">' + tableName + '</option>');
        });
    }
}