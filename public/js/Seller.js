function save(id, image) {
  document.myForm.action =
    "/CoverShopping/routes/web.php?page=saveChangeProduct&id=" + id + "&img=" + image;
  document.myForm.submit();
}

function log_out() {
  var check = confirm("确定登出");
  if (check) {
    window.location.href = "/CoverShopping/routes/web.php?page=logout";
  }
}

function Refund(no) {
  var check = confirm("確定退款");
  if (check) {
    window.location.href =
      "/CoverShopping/routes/web.php?page=sellerUpdatePayment&&no=" + no;
  }
}

function Shipping(id, no) {
  window.location.href =
    "/CoverShopping/routes/web.php?page=updateShipping&no=" + no + "&id=" + id;
}

function change(id) {
  window.location.href =
    "/CoverShopping/routes/web.php?page=change_product&id=" + id;
}

function del(id) {
  var check = confirm("您真的确定要删除吗");
  if (check) {
    document.myForm.action =
      "/CoverShopping/routes/web.php?page=sellerDeleteProduct&id=" + id;
    document.myForm.submit();
  }
}

function management_product() {
  window.location.href =
    "/CoverShopping/routes/web.php?page=management_product";
}

function id_check() {
  var id = document.getElementsByName("id");
  if (id[0].value.length != 6) {
    alert("產品ID要6碼");
    return false;
  }
  myForm.submit();
}

function orders() {
  window.location.href =
    "/CoverShopping/routes/web.php?page=orders";
}
