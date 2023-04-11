/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the Message Assigned Related Database code.
 * Date  07/07/2017.
**///

//Function for adding the recipient. 
function addReceipient(count){
    var messageCnt = $("#messageParamTabs"+(count)+"Cnt").val();

    var messagevar = '<div class="col-md-12 card-box kanban-box">'+
                        '<div class="col-sm-4 form-group">'+
                            '<label class="control-label">Type </label>'+
                                '<select class="form-control" name="messageParam['+messageCnt+'][messageType]" id="messageTypes'+messageCnt+'"><option>--Please Select--</option></select>'+
                        '</div>'+
                        '<div class="col-sm-8 form-group">'+
                            '<label for="">Receipient</label>'+
                            '<input class="form-control" type="text" name="messageParam['+messageCnt+'][recipient]">'+
                        '</div><a style="float:right; margin-top: 10px;" class="m-t-5 btn btn-icon waves-effect waves-light btn-primary zmdi zmdi-delete" onClick="deleteReceipient('+count+','+messageCnt+')" id="'+count+'" href="javascript:void(0)" title="Delete Receipient"></a></span>'+
                    '</div>';

    $('#messageParamTabs'+count).append('<div id="ptabs-'+count+'-'+messageCnt+'" class="row">'+messagevar+'</div>');
    var cnt = ++messageCnt;
    $("#messageParamTabs"+(count)+"Cnt").val(cnt);
    $('#messageParamTabs'+count).find('select, input').empty();
    $.each(messageTypeData, function(i, p) {
        $('#messageParamTabs'+count).find('select, input').append($('<option></option>').val(p).html(p));
    });
}

//Function for deleting the Receipients.
function deleteReceipient(count,messageCnt) {
    $('#ptabs-'+count+'-'+messageCnt).remove();
}

// For loading the Message Code Dropdown
function getMessageCode() {
    showCanvas();
    $.ajax({
        type: 'POST',
        url: 'scripts/reqMessage.php',
         data: {'command' : "getMessageCode"},
        dataType: 'text',
        encode: true
    }).done(function(data) {
        console.log(data);
        var obj = JSON.parse(data);
        console.log(obj);
        hideCanvas();
        if(obj.status == "ok") {
            var message= obj.data.messageData;
            var messageStr = message.messageCode.toString();
            var messageData = messageStr.split(",");
            $.each(messageData, function(i, p) {
                $('#messageCode').append($('<option></option>').val(p).html(p));
            });

            var messageTypeStr = message.messageType.toString();
            messageTypeData = messageTypeStr.split(",");
            $.each(messageTypeData, function(msgKey, msgValue) { 
                $('#messageType').append($('<option></option>').val(msgValue).html(msgValue));
            });
        } else {
            errorHandler(obj.code, obj.statusMsg);
        }
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
function getEditMessageAssignedData() { 
    var messageAssignedId = $.urlParam('id');
    showCanvas();
    $.ajax({
        type: 'POST',
        url: 'scripts/reqMessage.php',
        data: {'command' : "getEditMessageAssignedData", 'messageAssignedId' : messageAssignedId},
        dataType: 'text',
        encode: true
    }).done(function(messageAssignedValues) {
        var messageAssignedData = JSON.parse(messageAssignedValues);
        var obj = JSON.parse(messageAssignedValues);
        console.log(obj);
        hideCanvas();
        if(messageAssignedData.status == "ok") {
            $('#messageCode').val(messageAssignedData.data.messageAssignedData.code);
            $('#messageType').val(messageAssignedData.data.messageAssignedData.type);
            $('#messageRecipient').val(messageAssignedData.data.messageAssignedData.recipient);

            if(messageAssignedData.data.messageAssignedData.Status == 1){
                $("#inlineRadio1").prop('checked', true);
            }else{
                $("#inlineRadio2").prop('checked', true);
            }
        } else {
            errorHandler(messageAssignedData.code, messageAssignedData.statusMsg);
        }
    });
}