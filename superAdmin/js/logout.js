function beforeLogout() {
    var canvasBtnArray = Array('Yes');
    var message = 'Are you sure you want to logout?';
    showMessage(message, 'info', 'Logout', 'sign-out', '', canvasBtnArray);
    $('#canvasYesBtn').click(function() {
        $.ajax({
            type: 'POST',
            url: 'scripts/reqLogin.php',
            data: {type : "logout"},
            success	: function(result) {
                window.location.href = 'pageLogin.php';
            },
            error	: function(result) {
                alert("Error!");
            }
        });
    });
}