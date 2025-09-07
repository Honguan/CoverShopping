<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>訂單管理</title>
    <script language="javascript" src="/CoverShopping/public/js/Seller.js"></script>
  <link rel="stylesheet" href="/CoverShopping/public/css/Modules.css">

</head>

<body bgcolor="lightyellow">
    <h1>訂單資料</h1>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/CoverShopping/public/common/state_header.php'; ?>
    <form name="myForm" method="post">
        <table class="seller-management">
            <tr class="header">
                <th>訂單編號</th>
                <th>購物者</th>
                <th>購買數量</th>
                <th>商品單價</th>
                <th>商品總價</th>
                <th>購買時間</th>
                <th>出貨確認</th>
                <th>收款確認</th>
            </tr>
            <?php foreach ($orders_by_product as $product_id => $data): ?>
                <tr class="data">
                    <td align='center' colspan='2'>商品編號:<b><?= htmlspecialchars($data['product']->id) ?></b></td>
                    <td align='center' colspan='6'>商品名稱:<b><?= htmlspecialchars($data['product']->name) ?></b></td>
                </tr>
                <?php foreach ($data['orders'] as $row): ?>
                    <tr class="data">
                        <td align='center'><?= htmlspecialchars($row->no) ?></td>
                        <td align='center'>買家:<br><b><?= htmlspecialchars($row->name) ?></b></td>
                        <td align='center'><?= htmlspecialchars($row->quantity) ?></td>
                        <td align='center'><?= htmlspecialchars($data['product']->price) ?></td>
                        <td align='center'><?= htmlspecialchars($data['product']->price * $row->quantity) ?></td>
                        <td align='center'><?= htmlspecialchars($row->time) ?></td>
                        <td align='center'>
                            <?php if (!$row->Shipping): ?>
                                <button type="button" class="btn" onclick="Shipping('<?= $row->id ?>','<?= $row->no ?>')">出貨確認</button>
                            <?php else: ?>
                                <font color='#F75000'><b>已出貨</b></font>
                            <?php endif; ?>
                        </td>
                        <td align='center'>
                            <?php if (!$row->payment): ?>
                                <span style="color:#5B00AE;font-weight:bold;">未付款</span>
                            <?php else: ?>
                                <span style="color:#e54040;font-weight:bold;">已完成付款</span>
                                <button type="button" class="btn" onclick="Refund('<?= $row->no ?>')">退款</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </table>
        <div style="text-align:center;">
            <button type="button" class="btn" onclick="window.open('/CoverShopping/routes/web.php?page=new_product','_self')">新增商品</button>
            <button type="button" class="btn" onclick="window.open('/CoverShopping/routes/web.php?page=management_product','_self')">商品管理</button>
            <button type="button" class="btn" onclick="window.open('/CoverShopping/routes/web.php?page=sales_results','_self')">銷售結果</button>
        </div>
    </form>
</body>

</html>