function save(account) {
  document.myForm.action =
    "/CoverShopping/routes/web.php?page=adminUpdateMember&account=" + account;
  document.myForm.submit();
}

function change(account) {
  document.myForm.action =
    "/CoverShopping/routes/web.php?page=adminChangeAccount&account=" + account;
  document.myForm.submit();
}

function delOrder() {
    var check = confirm("您真的确定要删除");
    if (check) {
        var no = document.getElementsByName("no");
        var noValue = no[0].value;
        window.location.href =
            "/CoverShopping/routes/web.php?page=adminDeleteOrder&no=" + noValue;
    }
}

function delMember(account) {
    var check = confirm("您真的确定要删除");
    if (check) {
        window.location.href =
            "/CoverShopping/routes/web.php?page=adminDeleteMember&account=" + account;
    }
}

function delMessage(id) {
    var check = confirm("您真的确定要删除");
    if (check) {
        window.location.href =
            "/CoverShopping/routes/web.php?page=adminDeleteMessage&id=" + id;
    }
}

function delProduct(id) {
    var check = confirm("您真的确定要删除");
    if (check) {
        window.location.href =
            "/CoverShopping/routes/web.php?page=adminDeleteProduct&id=" + id;
    }
}

function product() {
  window.location.href = "/CoverShopping/routes/web.php?page=product";
}

function order() {
  window.location.href = "/CoverShopping/routes/web.php?page=order";
}

function message() {
  window.location.href = "/CoverShopping/routes/web.php?page=message";
}

function member() {
  window.location.href = "/CoverShopping/routes/web.php?page=member";
}

function log_out() {
  var check = confirm("确定登出");
  if (check) {
    window.location.href = "/CoverShopping/routes/web.php?page=logout";
  }
}

function payment(Shipping, no) {
  if (Shipping == "0") {
    alert("商品尚完全未出貨");
    var check = confirm("確定付款");
    if (check) {
      document.myForm.action =
        "/CoverShopping/routes/web.php?page=adminUpdateOrderPayment&payment=1&no=" +
        no;
      document.myForm.submit();
    }
  } else {
    document.myForm.action =
      "/CoverShopping/routes/web.php?page=adminUpdateOrderPayment&payment=1&no=" +
      no;
    document.myForm.submit();
  }
}

function Shipping(id, no) {
  document.myForm.action =
    "/CoverShopping/routes/web.php?page=adminUpdateOrderShipping&Shipping=1&no=" +
    no +
    "&id=" +
    id;
  document.myForm.submit();
}

function Refund(no) {
  var check = confirm("確定退款");
  if (check) {
    document.myForm.action =
      "/CoverShopping/routes/web.php?page=adminUpdateOrderPayment&payment=0&no=" +
      no;
    document.myForm.submit();
  }
}

function change_product(id) {
  if (document.myForm) {
    document.myForm.action =
      "/CoverShopping/routes/web.php?page=adminChangeProduct&id=" + id;
    document.myForm.submit();
  } else {
    alert("找不到表單，請確認頁面有 <form name='myForm'>");
  }
}
