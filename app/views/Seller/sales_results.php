<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>銷售結果</title>
  <script src="/CoverShopping/public/js/Seller.js"></script>
  <link rel="stylesheet" href="/CoverShopping/public/css/Modules.css">
</head>

<body bgcolor="lightyellow">
  <h1>銷售結果</h1>
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/CoverShopping/public/common/state_header.php'; ?>
  <table class="seller-management">
    <tr class="header">
      <td align='center'><b>商品名稱</b></td>
      <td align='center'><b>銷售數量</b></td>
      <td align='center'><b>銷售百分比</b></td>
      <td align='center'><b>直方圖</b></td>
    </tr>
    <?php if (empty($sales_data['sales_data'])): ?>
      <tr>
        <td colspan="4" align="center">
          <h1>沒有可販賣的產品</h1>
        </td>
      </tr>
    <?php else: ?>
      <?php foreach ($sales_data['sales_data'] as $data): ?>
        <tr class="data">
          <td align='center'><b><?= htmlspecialchars($data['name']) ?></b></td>
          <td align='center'><?= $data['sales_count'] ?></td>
          <td align='center'><?= $data['percent'] ?>%</td>
          <td height='35'><img src='/CoverShopping/public/images/special_chart/yellow.png' width='<?= $data['percent'] * 3 ?>' height='20'></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($sales_data['total_sales'] == 0): ?>
        <tr>
          <td colspan="4" align="center">
            <h1>沒有銷售紀錄</h1>
          </td>
        </tr>
      <?php endif; ?>
      <tr bgcolor='#FFCBB3'>
        <td align='center'>總計</td>
        <td align='center'><?= $sales_data['total_sales'] ?></td>
        <td align='center'>100%</td>
        <td><img src='/CoverShopping/public/images/special_chart/red.png' width='300' height='20'></td>
      </tr>
    <?php endif; ?>
  </table>
  <div style="text-align:center;">
    <button type="button" class="btn" onclick="window.open('/CoverShopping/routes/web.php?page=new_product','_self')">新增商品</button>
    <button type="button" class="btn" onclick="window.open('/CoverShopping/routes/web.php?page=management_order','_self')">訂單管理</button>
    <button type="button" class="btn" onclick="window.open('/CoverShopping/routes/web.php?page=management_product','_self')">商品管理</button>
  </div>
  </p>

</html>