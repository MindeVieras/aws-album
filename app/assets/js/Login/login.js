
Login.process = function(btn, info) {

  var data = {
    username: $('#signin_form #username').val(),
    password: $('#signin_form #password').val()
  };

  $.ajax({
      type: "POST",
      data: data,
      url: '/login',
      dataType: "json",
      beforeSend:function(xhr){
        $('#login-button').text('Wait...');
      },
      success: function (res) {
        $('#login-button').text('Sign in');
        if (res.ack == 'ok') {
          window.location = '/';
        } else {
          $('#signin_form .alert').text(res.msg);
          $('#signin_form').addClass('form-error');
          setTimeout(function(){
            $('#signin_form').removeClass('form-error');
          }, 1000);
        }
      }
  });
}