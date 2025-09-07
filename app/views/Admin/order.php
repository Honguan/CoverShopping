<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>訂單總管</title>
    <script language="javascript" src="/CoverShopping/public/js/Admin.js"></script>
    <link rel="stylesheet" href="/CoverShopping/public/css/Modules.css">
</head>

<body bgcolor="lightyellow">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/CoverShopping/public/common/admin_header.php'; ?>
    <p align="center">訂單總管</p>
    <form name="myForm" align="center" method="post">
        <input type="text"  class="btn" placeholder="訂單編號" size="10" name="no">
        <input type="button" class="btn" value="刪除訂單" onclick="delOrder()">
    </form>
    <table class="seller-management" border='1' align='center' width='900' cellspacing='1'>
        <tr class="header" height='30' align='center'>
            <th>訂單編號</th>
            <th>購物者</th>
            <th>商品貨號</th>
            <th>商品名稱</th>
            <th>商品單價</th>
            <th>購買數量</th>
            <th>商品小計</th>
            <th>出貨確認</th>
        </tr>
        <?php if (isset($orders)): ?>
            <?php $order_number = []; ?>
            <?php foreach ($orders as $row): ?>
                <?php if (!isset($order_number[$row->no])) $order_number[$row->no] = 0; ?>
                <?php $order_number[$row->no] += 1; ?>
            <?php endforeach; ?>
            <?php $printed = []; ?>
            <?php foreach ($orders as $row): ?>
                <?php if (!isset($printed[$row->no])): ?>
                    <?php $printed[$row->no] = true; ?>
                    <?php $temp_rowspan = $order_number[$row->no]; ?>
                    <?php $total_price = 0; ?>
                    <?php $counter = 0; ?>
                    <?php $Shipping = []; ?>
                    <?php foreach ($orders as $item): ?>
                        <?php if ($item->no == $row->no): ?>
                            <tr class="data">
                                <?php if ($counter == 0): ?>
                                    <td align='center' rowspan='<?= $temp_rowspan ?>'><?= htmlspecialchars($item->no) ?></td>
                                    <td align='center' rowspan='<?= $temp_rowspan ?>'>買家:<br><b><?= htmlspecialchars($item->name) ?></b>
                                    </td>
                                <?php endif; ?>
                                <td align='center'><?= htmlspecialchars($item->id) ?></td>
                                <td align='center'><?= htmlspecialchars($item->commodity_name) ?></td>
                                <td align='center'><?= htmlspecialchars($item->commodity_price) ?></td>
                                <td align='center'><?= htmlspecialchars($item->quantity) ?></td>
                                <td align='center'><?= htmlspecialchars($item->commodity_price * $item->quantity) ?></td>
                                <td align='center'>
                                    <?php if (!$item->Shipping): ?>
                                        <button type="button" class="btn" onclick="Shipping('<?= $item->id ?>','<?= $item->no ?>')">出貨確認</button>
                                    <?php else: ?>
                                        <font color='#F75000'><b>已出貨</b></font>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php $counter++; ?>
                            <?php $total_price += $item->commodity_price * $item->quantity; ?>
                            <?php $Shipping[$counter] = $item->Shipping; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <tr class="data">
                        <td colspan='6' align='center'>購買時間:<?= htmlspecialchars($row->time) ?></td>
                        <td>運費:<?= htmlspecialchars($row->freight) ?></td>
                        <td align='center'>總計:<?= $total_price + $row->freight ?></td>
                    </tr>
                    <?php if (!$row->payment): ?>
                        <tr class="data">
                            <td colspan="8" align="center">
                                <button type="button" class="btn" onclick="payment('<?= $row->Shipping ?>','<?= $row->no ?>')">確認付款</button>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr class="data">
                            <td colspan="8" align="center">
                                <font color="#e54040"><b>已完成付款~~~</b></font>
                                <button type="button" class="btn" style="float:right" onclick="Refund('<?= $row->no ?>')">退款</button>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</body>

</html>