/**
 * Javascript functions for search
 * 
 **/

/** 
*   This function is to build your search parameters
*   searchId - ID of your search form
**/

function buildSearchData(searchId) {
    search = $('#'+searchId);
    
    var dataForm = [];
    var dataName;
    var dataValue = [];
    search.find('div.form-group').each(function() {
        var formGroup = $(this);
        dataName  = formGroup.find('label.control-label').attr('data-th');
        if (dataName != '') {
            if(formGroup.find('input').length) {
                formGroup.find('input').each(function() {
                    var val = $(this).val();
                    if (val != '')
                        dataValue.push(val);
                });
            }
            else if(formGroup.find('select').length) {
                var val= formGroup.find('option:selected').val();
                if (val != '')
                    dataValue.push(val);
            }

            if (dataValue.length > 0)
                dataForm.push({dataName : dataName, dataValue : dataValue});
            dataValue = [];
        }
    });
    return dataForm;
}