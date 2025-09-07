<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>新增商品</title>

  <link rel="stylesheet" href="/CoverShopping/public/css/Modules.css">
  <script src="/CoverShopping/public/js/Seller.js"></script>
</head>

<body>
  <h1 align="center">新增商品</h1>
  <form name="myForm" action="/CoverShopping/routes/web.php?page=saveNewProduct" method="post" enctype="multipart/form-data">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/CoverShopping/public/common/state_header.php'; ?>
    <table class="seller-management">
      <tr class="header">
        <td class="title">產品ID</td>
        <td style="text-align:left;"><input type="text" name="id" size="6" maxlength="6" value="<?= htmlspecialchars($ID) ?>"></td>
      </tr>
      <tr class="data">
        <td class="title">產品封面</td>
        <td style="text-align:left;"><input type="file"  name="image" accept="image/*"></td>
      </tr>
      <tr class="data">
        <td class="title">產品名稱</td>
        <td style="text-align:left;"><input type="text" name="name"></td>
      </tr>
      <tr class="data">
        <td class="title">產品介紹</td>
        <td style="text-align:left;"><textarea name="content" cols="50" rows="5"></textarea></td>
      </tr>
      <tr class="data">
        <td class="title">產品價格</td>
        <td style="text-align:left;">$<input type="number" name="price"></td>
      </tr>
      <tr class="data">
        <td class="title">產品存量</td>
        <td style="text-align:left;"><input type="number" name="inventory" value="0"></td>
      </tr>
    </table>
    <br>
    <div style="text-align:center;">
      <button type="button" class="btn" onclick="id_check()">添加</button>
      <button type="reset" class="btn">重新輸入</button>
    </div>
  </form>
  <div style="text-align:center;">
    <button type="button" class="btn" onclick="window.open('/CoverShopping/routes/web.php?page=management_product','_self')">商品管理頁面</button>
  </div>
</body>

</html>