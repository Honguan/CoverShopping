<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <title>商品總管</title>
  <script language="javascript" src="/CoverShopping/public/js/Admin.js"></script>
  <script language="javascript" src="/CoverShopping/public/js/S.js"></script>
    <link rel="stylesheet" href="/CoverShopping/public/css/Modules.css">
</head>

<body bgcolor="lightyellow">
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/CoverShopping/public/common/admin_header.php'; ?>
  <h2 align="center">商品列表</h2>
  <table class="seller-management" border="1" align="center" name ="myForm">
    <tr class="header">
      <th>商品編號</th>
      <th>商品名稱</th>
      <th>商品內容</th>
      <th>商品價格</th>
      <th>庫存</th>
      <th>賣家帳號</th>
      <th>圖片</th>
      <th>刪除</th>
      <th>修改</th>
    </tr>
    <?php if (isset($products)) : ?>
      <?php foreach ($products as $row) : ?>
        <tr class="data">
          <td><b><?= htmlspecialchars($row['id']) ?></b></td>
          <td><b><?= htmlspecialchars($row['name']) ?></b></td>
          <td><?= htmlspecialchars($row['content']) ?></td>
          <td><?= htmlspecialchars($row['price']) ?></td>
          <td><?= htmlspecialchars($row['inventory']) ?></td>
          <td><?= htmlspecialchars($row['account']) ?></td>
            <td>
            <img src="/CoverShopping/public/images/<?= htmlspecialchars(basename($row['image'])) ?>"
               style="max-width:100px; max-height:140px; width:auto; height:auto;"
               alt="圖片未上傳">
            </td>
          <td>
            <button type="button" class="btn" onclick="delProduct('<?= $row['id'] ?>')">刪除</button>
          </td>
          <td>
            <button type="button" class="btn" onclick="change_product('<?= $row['id'] ?>')">修改</button>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </table>
</body>

</html>