function delOrder() {
  var check = confirm("您真的确定要删除吗");
  if (check) {
    var no = document.getElementsByName("no");
    var noValue = no[0].value;
    document.myForm.action =
      "/CoverShopping/routes/web.php?page=deleteOrder&no=" + noValue;
    document.myForm.submit();
  }
}

function log_out() {
  var check = confirm("确定登出");
  if (check) {
    window.location.href = "/CoverShopping/routes/web.php?page=logout";
  }
}

function payment(Shipping, no) {
  if (Shipping == "0") {
    var check = confirm("確定付款");
    if (check) {
      fetch("/CoverShopping/routes/web.php?page=updatePayment ", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "action=updatePayment&payment=1&no=" + encodeURIComponent(no),
      })
        .then((response) => response.text())
        .then(() => {
          window.location.reload();
        });
    }
  } else {
    fetch("/CoverShopping/routes/web.php?page=updatePayment", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: "action=updatePayment&payment=1&no=" + encodeURIComponent(no),
    })
      .then((response) => response.text())
      .then(() => {
        window.location.reload();
      });
  }
}
