<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <title>超皮的購物網站</title>
  <link rel="stylesheet" href="/CoverShopping/public/css/Modules.css">
  <link rel="stylesheet" href="/CoverShopping/public/css/index.css">
  <script language="javascript" src="/CoverShopping/public/js/Seller.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.card').forEach(function(card) {
        card.addEventListener('click', function() {
          card.classList.toggle('flipped');
        });
      });
    });
  </script>
</head>

<body bgcolor="#f5f5f5">
  <?php
  require_once dirname(__DIR__) . '/controllers/indexController.php';
  $controller = new indexController();
  $products = $controller->products(); // 請確認 controller 有此方法
  ?>
  <div id="title">
    <h2 align="center"><a href="../"><b>
          <font color="#ffffff">返回個人網站</font>
        </b></a></h2>
    <?php include __DIR__ . '../../../public/common/main_header.php'; ?>
    <h1 align="center">
      <font color='#FFFFFF'>超皮購物</font>
    </h1>
  </div>
  <div id="shopping_cart">
    <a href="/CoverShopping/routes/web.php?page=shopping_cart">
      <img src="/CoverShopping/public/images/special_chart/shopping_cart.png" width="50" height="50" alt="購物車">
    </a>
  </div>
  <div class="card-list">
    <?php foreach ($products as $row): ?>
      <div class="card">
        <div class="card-inner">
          <div class="card-front">
            <img src="/CoverShopping/public/images/<?= htmlspecialchars(basename($row['image'])) ?>" alt="圖片未上傳">
            <div class="name"><?= htmlspecialchars($row['name']) ?></div>
            <div class="inventory">存量: <?= htmlspecialchars($row['inventory']) ?></div>
            <?php if ($row['inventory'] > 0): ?>
              <form method="post" action="/CoverShopping/routes/web.php?page=addShoppingCart&id=<?= htmlspecialchars($row['id']) ?>&name=<?= htmlspecialchars($row['name']) ?>&price=<?= htmlspecialchars($row['price']) ?>">
                <input type="number" name="quantity" value="1" min="1" max="<?= htmlspecialchars($row['inventory']) ?>" style="width:50px;">
                <button type="submit" class="btn">加入購物車</button>
              </form>
            <?php else: ?>
              <div class="price" style="color:#e54040;font-weight:bold;">缺貨 (╥﹏╥)</div>
            <?php endif; ?>
          </div>
          <div class="card-back">
            <div class="name" style="font-size:18px;font-weight:600;margin-bottom:8px;"><?= htmlspecialchars($row['name']) ?></div>
            <div class="content"><?= htmlspecialchars($row['content']) ?></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</body>

</html>