<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>購物訂單</title>
    <script language="javascript" src="/CoverShopping/public/js/Orders.js"></script>
    <link rel="stylesheet" href="/CoverShopping/public/css/Modules.css">
</head>

<body bgcolor="lightyellow">
    <h1 align="center">購物訂單</h1>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/CoverShopping/public/common/state_header.php'; ?>
    <form name="myForm" method="post">
        <table class="seller-management" border='1' align='center' width='800' cellspacing='1'>
            <tr class="header" height='30' align='center'>
                <th>訂單編號</th>
                <th>購物者</th>
                <th>商品名稱</th>
                <th>商品單價</th>
                <th>購買數量</th>
                <th>商品小計</th>
                <th>出貨確認</th>
            </tr>
            <?php foreach ($orders_grouped as $no => $order): ?>
                <?php $rowspan = count($order['items']); ?>
                <?php $total_price = 0; ?>
                <?php foreach ($order['items'] as $idx => $item): ?>
                    <tr class="data">
                        <?php if ($idx === 0): ?>
                            <td align="center" rowspan="<?= $rowspan ?>"><?= $no ?></td>
                            <td align="center" rowspan="<?= $rowspan ?>">買家:<b><?= htmlspecialchars($order['buyer']) ?></b></td>
                        <?php endif; ?>
                        <td align="center"><?= htmlspecialchars($item['name']) ?></td>
                        <td align="center"><?= $item['price'] ?></td>
                        <td align="center"><?= $item['quantity'] ?></td>
                        <td align="center"><?= $item['subtotal'] ?></td>
                        <?php if ($idx === 0): ?>
                            <td align="center" rowspan="<?= $rowspan ?>">
                                <?php if (!$order['shipping']): ?>
                                    <font color="#000079"><b>未出貨</b></font>
                                <?php else: ?>
                                    <font color="#F75000"><b>已出貨</b></font>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php $total_price += $item['subtotal']; ?>
                <?php endforeach; ?>
                <tr class="data">
                    <td colspan="5" align="center">購買時間:<?= htmlspecialchars($order['time']) ?></td>
                    <td>運費:<?= $order['freight'] ?></td>
                    <td align="center">總計:<?= $total_price + $order['freight'] ?></td>
                </tr>
                <?php if (!$order['payment']): ?>
                    <tr class="data">
                        <td colspan="7" align="center">
                            <button type="button" class="btn" onclick="payment('<?= $order['shipping'] ?>','<?= $no ?>')">確認付款</button>
                        </td>
                    </tr>
                <?php else: ?>
                    <tr class="data">
                        <td colspan="7" align="center">
                            <font color="#e54040"><b>已完成付款~~~</b></font>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </table>
    </form>
</body>

</html>