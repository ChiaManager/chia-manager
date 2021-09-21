$(function(){
  if(loggedinstatus == "007008002"){
    showAuthKeyWindow();
  }

  $("#resend-authkey").on("click", function(e){
    e.preventDefault();
    resendAuthkey();
  });

  $("#inputAuthkey").on("input", function(){
    if($(this).val().trim().length == 50){
      $("#authkeybutton").removeAttr("disabled");
    }else{
      $("#authkeybutton").attr("disabled","disabled");
    }
  });

  $("#go-back").on("click", function(e){
    e.preventDefault();
    var url = backend + "/core/Login/Login_Rest.php";
    var action = "invalidateLogin";
    var type = "POST";

    sendToAPI(url, action, type, {});
  });

  $("#authkeybutton").on("click", function(e){
    e.preventDefault();
    var authkey = $("#inputAuthkey").val().trim();
    if(authkey.length == 50){
      var url = backend + "/core/Login/Login_Rest.php";
      var action = "checkAuthKey";
      var type = "POST";
      var data = {
        "authkey" : authkey
      }
      $(this).attr("disabled","disabled").find("i").show();
      sendToAPI(url, action, type, data);
    }else{
      showMessage("alert-danger", "Authkey not valid or empty.");
    }
  });

  $("#loginbutton").on("click",function(e){
    e.preventDefault();
    var username = $("#inputLogin").val();
    var password = $("#inputPassword").val();
    var stayloggedin = ($("#stayloggedin").prop("checked") ? "1" : "0");
    if(username != "" && password != ""){
      var action = "login";
      var type = "POST";
      var url = backend + "/core/Login/Login_Rest.php";
      var data = {
        username: username,
        password: password,
        stayloggedin: stayloggedin
      };
      beginLogin();
      sendToAPI(url, action, type, data);
    }else{
      if(username == "" && password == ""){
        $("#inputLogin").addClass("error");
        setTimeout(function() {
          $("#inputLogin").removeClass("error");
        },2000);
        showMessage("alert-danger", "Username and Password cannot be emtpy!");
      }else{
        if(username == ""){
          $("#inputLogin").addClass("error");
          setTimeout(function() {
            $("#inputLogin").removeClass("error");
          },2000);
          showMessage("alert-danger", "Username cannot be emtpy!");
        }
        if(password == ""){
          $("#inputPassword").addClass("error");
          setTimeout(function() {
            $("#inputPassword").removeClass("error");
          },2000);
          showMessage("alert-danger", "Password cannot be emtpy!");
        }
      }
    }
  });

  $("#forgot-password").on("click", function(e){
    e.preventDefault();
    showPWResetwindow();
  });

  $("#pwreset-go-back").on("click", function(e){
    e.preventDefault();
    showloginwindow();
  });

  $("#inputPWReset").on("input", function(){
    if($(this).val().trim().length > 0){
      $("#sendResetLinkBtn").removeAttr("disabled");
    }else{
      $("#sendResetLinkBtn").attr("disabled","disabled");
    }
  });

  $("#sendResetLinkBtn").on("click", function(e){
    e.preventDefault();

    var action = "requestUserPasswordReset";
    var type = "POST";
    var url = backend + "/core/Users/Users_Rest.php";
    var data = {
      username: $("#inputPWReset").val()
    };

    $("#sendResetLinkBtn i").show();
    sendToAPI(url, action, type, data);
  });

  function beginLogin(){
    $("#loginbutton").attr("disabled", "disabled").find("i").show();
    $("#inputLogin").attr("disabled", "disabled");
    $("#inputPassword").attr("disabled", "disabled");
    $("#stayloggedin").attr("disabled", "disabled");
    $("#remeberMe").attr("disabled", "disabled");
  }

  function finishLogin(){
    $("#loginbutton").removeAttr("disabled").find("i").hide();
    $("#inputLogin").removeAttr("disabled");
    $("#inputPassword").removeAttr("disabled");
    $("#stayloggedin").removeAttr("disabled");
    $("#remeberMe").removeAttr("disabled");
  }

  function showMessage(messagetype, message){
    setTimeout(function () {
      $("#messagecontainer").append(
        "<div class='alert " + messagetype + " alert-dismissible desktop mobile'>" +
          "<a href='#' class='close' data-dismiss='alert' aria-label='close' title='close'>×</a>" +
          "<span>" + message + "</span>" +
        "</div>"
      );
      setTimeout(function () {
        $("#messagecontainer").children().fadeOut(), 10000
      },5000);
    },50);
  }

  function showAuthKeyWindow(){
    if($("#authkeywindow").is(":hidden")){
      $("#loginwindow").hide(500);
      $("#authkeywindow").show(500);
      $("#authkeybutton").attr("disabled","disabled");
    }
  }

  function showloginwindow(){
    if($("#loginwindow").is(":hidden")){
      $("#authkeywindow").hide(500);
      $("#pwresetwindow").hide(500);
      $("#loginwindow").show(500);
    }
  }

  function showPWResetwindow(){
    if($("#pwresetwindow").is(":hidden")){
      $("#inputPWReset").val("");
      $("#loginwindow").hide(500);
      $("#pwresetwindow").show(500);
    }
  }

  function resendAuthkey(){
    var url = backend + "/core/Login/Login_Rest.php";
    var action = "generateAndsendAuthKey";
    var type = "POST";

    sendToAPI(url, action, type, {});
  }

  function sendToAPI(url, action, type, data){
    $.ajax({
      url: url,
      type: type,
      dataType: 'JSON',
      encode: true,
      data : {
        "action" : action,
        "data" : data
      },
      success: function (result, status, xhr) {
        if(result["status"] == 0){
          if(action == "login"){
            $(location).attr('href',frontend + '/index.php');
          }else if(action == "sendPWResetLink"){
            showMessage("alert-success", result["message"]);
            $("#pwresetform").hide();
            $("#loginform").show("slow");
            $("#resetPWLogin").val("");
          }else if(action == "generateAndsendAuthKey"){
            showMessage("alert-success", result["message"]);
          }else if(action == "invalidateLogin"){
            showloginwindow();
          }else if(action == "checkAuthKey"){
            $(location).attr('href',frontend + '/index.php');
          }else if(action == "requestUserPasswordReset"){
            $("#pwResetMessage").show();
            $("#pwResetMessage .card-body").text(result["message"]);
            $("#sendResetLinkBtn i").hide();
          }
        }else{
          if(result["status"] == "007001001"){
            showAuthKeyWindow();
            showMessage("alert-warning", result["message"]);
          }else{
            showMessage("alert-danger", result["message"]);
          }
          finishLogin();
          $("#authkeybutton").removeAttr("disabled").find("i").hide();
        }
      },
      error:function(xhr, status, error){
        finishLogin();
        $("#authkeybutton").removeAttr("disabled").find("i").hide();
        showMessage("alert-danger", error);
      }
    });
  }
});
