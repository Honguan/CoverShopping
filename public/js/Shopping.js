function change(id, i) {
  // 使用 fetch 呼叫對應的 ShoppingController.php 函式
  var input = document.getElementsByName("newquantity[" + i + "]")[0];
  var newquantity = input ? input.value : 1;
  alert("修改完畢"); // 這行可放在 fetch 前
  fetch(
    `/CoverShopping/routes/web.php?page=shopping_cart&action=changeProduct&id=${id}&i=${i}`,
    {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `newquantity=${encodeURIComponent(newquantity)}`
      
    }
  )

    .then((response) => response.text())
    .then((html) => {
      document.body.innerHTML = html;
    })
    .catch((error) => {
      alert("請求失敗：" + error);
    });
}

function del(id) {
  var check = confirm("您真的确定要删除吗");
  if (check) {
    fetch(
      `/CoverShopping/routes/web.php?page=shopping_cart&action=deleteProduct&id=${id}`,
      {
        method: "POST",
      }
    )
      .then((response) => response.text())
      .then((html) => {
        document.body.innerHTML = html;
      })
      .catch((error) => {
        alert("請求失敗：" + error);
      });
  }
}

function Create_order(cookie, login) {
  if (cookie) {
    alert("購物車沒有任何商品，訂單無法成立");
  } else if (!login) {
    window.location.href = "/CoverShopping/routes/web.php?page=login";
  } else {
    var freight = document.myForm.freight.value;
    var free_freight = document.myForm.free_freight.checked;
    let freightValue = free_freight ? 0 : freight;

    if (free_freight) {
      alert("已啟用--免運");
    } else if (freight === "") {
      alert("未選擇運送方式");
      return;
    }

    // 使用 fetch 呼叫 ShoppingController.php 的 createOrder 函式
    fetch(
      `/CoverShopping/routes/web.php?page=shopping_cart&action=createOrder`,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ freight: freightValue }),
      }
    )
      .then((response) => response.text())
      .then((html) => {
        document.body.innerHTML = html;
      })
      .catch((error) => {
        alert("請求失敗：" + error);
      });
  }
}

function deleteAll() {
  if (confirm("您確定要清空購物車嗎？")) {
    fetch("/CoverShopping/routes/web.php?page=shopping_cart&action=deleteAll", {
      method: "POST",
    })
      .then((response) => response.text())
      .then((html) => {
        document.body.innerHTML = html;
      })
      .catch((error) => {
        alert("請求失敗：" + error);
      });
  }
}
