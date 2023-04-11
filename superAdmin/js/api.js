/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the Message Assigned Related Database code.
 * Date  10/07/2017.
**///

// For loading the Api Name Dropdown
function getApiName() {
    showCanvas();
    $.ajax({
        type: 'POST',
        url: 'scripts/reqApi.php',
        data: {'command' : "getApiName"},
        dataType: 'text',
        encode: true
    }).done(function(data) {
        var obj = JSON.parse(data);
        hideCanvas();
        if (obj.status == 'ok') {
            $('#api_id').find('option:not(:first-child)').remove();
            if (typeof(obj.data.apiName) != 'undefined') {
                apiName = obj.data.apiName;
                var superAdminApiID = [], superAdminApiCommand = [], superAdminApiSite = [];
                var adminApiID = [], adminApiCommand = [], adminApiSite = [];
                var clientApiID = [], clientApiCommand = [], clientApiSite = [];
                var superAdminApiList = {}, adminApiList = {}, clientApiList = {};
                var apiList = {};
                $.each(apiName['command'], function(key, val) {
                    var apiID = apiName['id'][key];
                    var apiCommand = apiName['command'][key];
                    var apiSite = apiName['site'][key];

                    if(apiSite == 'SuperAdmin') {
                        superAdminApiID.push(apiID);
                        superAdminApiCommand.push(apiCommand);
                        superAdminApiSite.push(apiSite);
                    } else if(apiSite == 'Admin') {
                        adminApiID.push(apiID);
                        adminApiCommand.push(apiCommand);
                        adminApiSite.push(apiSite);
                    } else {
                        clientApiID.push(apiID);
                        clientApiCommand.push(apiCommand);
                        clientApiSite.push(apiSite);
                    } 
                    // $('#api_id').append('<option value="' + apiID + '" name="'+apiCommand+'" site="'+apiSite+'">' + val + ' - ' + apiSite + '</option>');
                });
                superAdminApiList['apiID'] = superAdminApiID;
                superAdminApiList['apiCommand'] = superAdminApiCommand;
                superAdminApiList['apiSite'] = superAdminApiSite;

                adminApiList['apiID'] = adminApiID;
                adminApiList['apiCommand'] = adminApiCommand;
                adminApiList['apiSite'] = adminApiSite;
                
                clientApiList['apiID'] = clientApiID;
                clientApiList['apiCommand'] = clientApiCommand;
                clientApiList['apiSite'] = clientApiSite;

                apiList['superAdmin'] = superAdminApiList;
                apiList['admin'] = adminApiList;
                apiList['client'] = clientApiList;

                returnApiList(apiList);
            }
            else {
                $('#apiMsg').addClass('alert-success').html('<span>' + obj.statusMsg + '</span>').show();
                setTimeout(function() {
                    $('#apiMsg').removeClass('alert-success').html('').hide();
                }, 3000);
            }
        }
        else
            errorHandler(obj.code, obj.statusMsg);
    });
}

//For capturing the parameter from the URL.
$.urlParam = function(name){
    var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
    if (results==null){
       return null;
    }
    else{
       return decodeURI(results[1]) || 0;
    }
}

//Load the message assigned data
function getEditParamData() { 
    var apiParamId = $.urlParam('id');
    $.ajax({
        type: 'POST',
        url: 'scripts/reqApi.php',
        data: {'command' : "getEditParamData", 'apiParamId' : apiParamId},
        dataType: 'text',
        encode: true
    }).done(function(data) {
        var obj = JSON.parse(data);
        hideCanvas();
        if (obj.status == 'ok') {
            $.each(obj.data, function(key, val) {
                if(key == 'params_type' && val == "alphanumeric") {
                    $('#alphaNumeric').prop('checked', true);
                } else if(key == 'params_type' && val == "numeric") {
                    $('#numeric').prop('checked', true);
                } else if(key == 'params_type' && val == "blob") {
                    $('#blob').prop('checked', true);
                } else {
                    $('#'+key).val(val);
                }
            });
        } else {
            errorHandler(obj.code, obj.statusMsg);
        }
    });
}

function buildTestApiInput(formID, inputID, formGroupIndex) {
    $('#'+formID).find('div.form-group:eq(' + formGroupIndex + ')').append('<input id="'+inputID+'" type="text" class="form-control"/>');
}

function buildTestApiBool(formID, divID, formGroupIndex) {
    $('#'+formID).find('div.form-group:eq(' + formGroupIndex + ')').append('<div id="'+divID+'" class="m-b-20"><div class="radio radio-info radio-inline"><input type="radio" id="inlineRadio1" value="0" name="radioInline" checked="checked"/><label for="inlineRadio1">Active</label></div><div class="radio radio-inline"><input type="radio" id="inlineRadio2" value="1" name="radioInline"/><label for="inlineRadio2">Disabled</label></div></div>');
}

function buildTestApiSelect(formID, selectID, formGroupIndex, command, url) {
    $('#'+formID).find('div.form-group:eq(' + formGroupIndex + ')').append('<select id="'+selectID+'" class="form-control"></select>');
    
    var formData = {command: command};
    if (command == "getRoles")
        formData.getActiveRoles = "getActiveRoles";
    $.ajax({
        type : 'POST',
        url : url,
        data: formData,
        dataType: 'text',
        encode: true
    })
    .done(function(data) {
        var obj = JSON.parse(data);
        $.each(obj.data.roleList['Name'], function(key, val) {
            $('#'+selectID).append('<option value="' + obj.data.roleList['ID'][key] + '">' + val + '</option>') 
        });
    }); 
}


/*
*   data - data['Name'][i] where i=0,1,2,...
*   data['Name'], data['Type'], data['ID']
*/
function buildTestApiData(apiID, data) {
    var formID = "testApi";
    switch(apiID) {
        
        //addUser
        case 1:
            var title = ["Status", "Role Name", "Password", "Email", "Full Name"];
            var arrLength = data['Type'].length;
            var i = arrLength;
            var j = 0; //form-group index tracker
            
            while (i) {
                $('#testApi').find('div.tab-content').append('<div class="form-group"></div>');
                i--;
                $('#testApi').find('div.form-group:eq('+j+')').append('<label>'+title[i]+'</label>');
                
                if (data['Type'][i] == "string") {
                    buildTestApiInput(formID, data['Name'][i], j);
                }
                else if(data['Type'][i] == "boolean") {
                    buildTestApiBool(formID, data['Name'][i], j);
                }
                else if(data['Type'][i] == "int") {
                    buildTestApiSelect(formID, data['Name'][i], j, "getRoles", "scripts/reqUsers.php");
                }
                j++;
            }
            break;
        
        case 2:
            break;
            
        default:
//            alert("No params for this API");
    }
}

function sendTestApi(apiID) {
    
    switch(apiID) {
        
        // addUser
        case 1:
            var fullName = $('#fullName').val();
            var email = $('#email').val();
            var password = $('#password').val();
            var roleID = $('#roleID').find('option:selected').val();
            var status = $('#status').find('input[type=radio]:checked').val();
            
            var formData = {
                command : "addUser",
                fullName : fullName,
                email : email,
                password : password,
                roleID : roleID,
                status : status
            };
            
            $.ajax({
                type: 'POST',
                url: 'scripts/reqUsers.php',
                data: formData,
                dataType: 'text',
                encode: true
            })
            .done(function(data) {
                console.log(data);
                var obj = JSON.parse(data);
                if (obj.status == 'ok') {
                    alert('Successfully tested. New user successfully created.');
                    window.location.href = "testApi.php";
                }
                else {
                    errorHandler(obj.code, obj.statusMsg);
                }
            });
            break;
        default:
    }
}
