function check() {
  var account = document.getElementsByName("account");
  if (account[0].value.length == 0) {
    alert("帳號未輸入");
    return false;
  }
  var password = document.getElementsByName("password");
  if (password[0].value.length == 0) {
    alert("密碼未輸入");
    return false;
  }
  myForm.submit();
}
window.onload = function () {
  var input = document.getElementsByName("password")[0];
  var eyesIcon = document.querySelector(".password-eye img");
  var flag = 0;
  if (eyesIcon && input) {
    eyesIcon.style.cursor = "pointer";
    eyesIcon.onclick = function () {
      eyesIcon.style.transition = "opacity 0.2s";
      eyesIcon.style.opacity = 0;
      setTimeout(function () {
        if (flag == 0) {
          input.type = "text";
          eyesIcon.src =
            "/CoverShopping/public/images/special_chart/eye-solid.png";
          flag = 1;
        } else {
          input.type = "password";
          eyesIcon.src =
            "/CoverShopping/public/images/special_chart/eye-slash-solid.png";
          flag = 0;
        }
        eyesIcon.style.opacity = 1;
      }, 200);
    };
  }
};

function CAPTCHA(account, checkcode_database) {
  var code = document.getElementsByName("code");
  if (code[0].value == checkcode_database) {
    alert("驗證碼正確");
    document.myForm.action = "/CoverShopping/routes/web.php?page=checkcodeDatabase&account=" + account;
    document.myForm.submit();
  } else {
    alert("驗證碼錯誤");
  }
}

function check() {
  var name = document.getElementsByName("name");
  if (name[0].value.length == 0) {
    alert("姓名未輸入");
    return false;
  }
  var account = document.getElementsByName("account");
  if (account[0].value.length == 0) {
    alert("帳號未輸入");
    return false;
  }
  var password = document.getElementsByName("password");
  if (password[0].value.length == 0) {
    alert("密碼未輸入");
    return false;
  }
  if (password[0].value == account[0].value) {
    alert("帳號和密碼不能相同");
    return false;
  }
  if (password[0].value.length < 6) {
    alert("密碼最少為6位數");
    return false;
  }
  var mail = document.getElementsByName("mail");
  if (mail[0].value.length == 0) {
    alert("e-mail未輸入");
    return false;
  }
  myForm.submit();
}

function welcome() {
  alert("歡迎來到超皮購物");
  window.location.href = "/CoverShopping/routes/web.php?page=index";
}

function login(account) {
  alert("使用者:" + account + "已登入");
  alert("如果要登入其他帳號請先登出");
  window.location.href = "/CoverShopping/routes/web.php?page=index";
}

function password_error() {
  alert("帳號或密碼錯誤");
  window.location.href = "/CoverShopping/routes/web.php?page=loginPage";
}

function enable() {
  alert("帳號未啟用");
  window.location.href = "/CoverShopping/routes/web.php?page=loginPage";
}

function pause() {
  alert("帳號暫停使用");
  window.location.href = "/CoverShopping/routes/web.php?page=loginPage";
}

function not_account() {
  alert("帳號不存在");
  window.location.href = "/CoverShopping/routes/web.php?page=loginPage";
}
