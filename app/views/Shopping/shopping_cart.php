<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <title>購物車</title>
  <script src="/CoverShopping/public/js/Shopping.js"></script>
  <link rel="stylesheet" type="text/css" href="/CoverShopping/public/css/Modules.css">
  <link rel="stylesheet" type="text/css" href="/CoverShopping/public/css/Seller.css">
</head>

<body bgcolor="LightYellow">
  <h1 align="center">購物車</h1>
  <div style="text-align:center;">
    <button type="button" class="btn" onclick="window.open('/CoverShopping/routes/web.php?page=index','_self')">商品列表</button>
  </div>
  <?php
  if (isset($_SESSION['order_msg'])) {
    echo $_SESSION['order_msg'];
    unset($_SESSION['order_msg']);
  }
  ?>
  <table class="seller-management" border="0" align="center" width="800">
    <tr class="header" bgcolor="#ACACFF" height="30" align="center">
      <th>商品貨號</th>
      <th>商品名稱</th>
      <th>商品價格</th>
      <th>購買數量</th>
      <th>價格小計</th>
      <th>變更數量</th>
      <th>刪除商品</th>
    </tr>
    <form name="myForm" method="post">
      <?php
      require_once dirname(__DIR__, 2) . '/controllers/ShoppingController.php';
      if (empty($cart)) {
        echo "<h4 align='center'>目前沒有商品在購物車中</h4>";
      } else {
        $total = 0;
        foreach ($cart as $i => $item) {
          $sub_total = $item['subtotal'];
          $total += $sub_total;
          echo "<tr class='data' bgcolor='#EDEAB1'>";
          echo "<td align='center'>" . htmlspecialchars($item['id']) . "</td>";
          echo "<td align='center'>" . htmlspecialchars($item['name']) . "</td>";
          echo "<td align='center'>$" . htmlspecialchars($item['price']) . "</td>";
          echo "<td align='center'><input type='number' name='newquantity[" . $i . "]' value='" . htmlspecialchars($item['quantity']) . "' size='3' ></td>";
          echo "<td align='center'>$" . $sub_total . "</td>";
          echo '<td bgcolor="#84C1FF" align="center">
              <input type="button" value="修改" class="btn" onclick="change(' . "'" . $item['id'] . "'" . ',' . "'" . $i . "'" . ')"></td>';
          echo '<td bgcolor="#84C1FF" align="center">
              <input type="button" value="刪除" class="btn" onclick="del(' . "'" . $item['id'] . "'" . ')"></td>';
          echo "</tr>";
        }
        echo "<tr align='right' bgcolor='#EDEAB1'>";
        echo "<td colspan='7'>
            運送方式:&emsp;
            宅配:$60<input type='radio' name='freight' value='60'>
            郵寄:$30<input type='radio' name='freight' value='30'>
            7-11取貨:$70<input type='radio' name='freight' value='70'>
            全家取貨:$50<input type='radio' name='freight' value='50'>
            OK取貨:$40<input type='radio' name='freight' value='40'>
            萊爾富取貨:$35<input type='radio' name='freight' value='35'>
            </td>";
        echo "<tr align='right' bgcolor='#EDEAB1'>";
        echo "<td colspan='7'>免運卷:<input type='checkbox' name='free_freight'>&emsp;
        總金額 = " . $total . "</td>";
        echo "</tr>";
      }
      ?>
      <tr>
        <p align="left">
          <td colspan='6'><input type="button" class="btn" value="清空購物車" onclick="deleteAll()"></td>
        </p>
        <p align="right">
          <td colspan='6'><input type="button"  class="btn"value="成立訂單"
              onclick="Create_order('<?php echo empty($cart) ?>',
          '<?php echo (isset($_SESSION['login']) && $_SESSION['login']); ?>')"></td>
        </p>
      </tr>
    </form>
  </table>
</body>

</html>