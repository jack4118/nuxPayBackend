/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the Message Assigned Related Database code.
 * Date  07/07/2017.
**///





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

//Load the message Code data
function getEditMessageCodeData() { 
    var messageCodeId = $.urlParam('id');
    $.ajax({
        type: 'POST',
        url: 'scripts/reqMessageCodes.php',
        data: {'command' : "getEditMessageCodeData", 'messageCodeId' : messageCodeId},
        dataType: 'text',
        encode: true
    }).done(function(messageCodeValues) {
        var messageCodeData = JSON.parse(messageCodeValues);
        $('#messageCode').val(messageCodeData.data.messageCode);
        $('#messageCodeDescription').val(messageCodeData.data.messageCodeDescription);
        
    });
}