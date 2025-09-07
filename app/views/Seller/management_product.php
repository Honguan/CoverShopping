<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <title>商品管理</title>
  <link rel="stylesheet" href="/CoverShopping/public/css/Modules.css">
</head>

<body>
  <h1>商品管理</h1>
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/CoverShopping/public/common/state_header.php'; ?>
  <table class="seller-management">
    <tr class="header">
      <td><b>商品ID</b></td>
      <td><b>商品圖片</b></td>
      <td><b>商品名稱</b></td>
      <td><b>商品說明</b></td>
      <td><b>商品價格</b></td>
      <td><b>存貨量</b></td>
      <td><b>修改資料</b></td>
      <td><b>刪除商品</b></td>
    </tr>
    <form name="myForm" method="post">
      <?php foreach ($products as $row): ?>
        <tr class="data">
          <td><b><?= htmlspecialchars($row->id) ?></b></td>
          <td><img src="/CoverShopping/public/images/<?= htmlspecialchars(basename($row->image)) ?>" width="100" height="140" alt="圖片未上傳"></td>
          <td><b><?= htmlspecialchars($row->name) ?></b></td>
          <td><?= htmlspecialchars($row->content) ?></td>
          <td><?= htmlspecialchars($row->price) ?></td>
          <td><?= htmlspecialchars($row->inventory) ?></td>
          <td><input type="button" value="修改" class="btn" onclick="change('<?= $row->id ?>')"></td>
          <td><input type="button" value="刪除" class="btn" onclick="del('<?= $row->id ?>')"></td>
        </tr>
      <?php endforeach; ?>
  </table>
  <div style="text-align:center;">
    <input type="button" value="新增商品" class="btn" onclick="window.open('/CoverShopping/routes/web.php?page=new_product','_self')">
    <input type="button" value="訂單管理" class="btn" onclick="window.open('/CoverShopping/routes/web.php?page=management_order','_self')">
    <input type="button" value="銷售結果" class="btn" onclick="window.open('/CoverShopping/routes/web.php?page=sales_results','_self')">
  </div>
  </form>
</body>

</html>