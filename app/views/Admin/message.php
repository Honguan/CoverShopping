<!doctype html>
<html>

<head>
    <title>留言板面</title>
    <meta charset="utf-8">
    <script language="javascript" src="/CoverShopping/public/js/Admin.js"></script>
    <link rel="stylesheet" href="/CoverShopping/public/css/Modules.css">
</head>

<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/CoverShopping/public/common/admin_header.php'; ?>
    <h2 align="center">留言列表</h2>
    <table class="seller-management" border="1" align="center">
        <tr class="header">
            <th>
                <font>名稱</font>
            </th>
            <th>
                <font>信箱</font>
            </th>
            <th>
                <font>訊息</font>
            </th>
            <th>
                <font>時間</font>
            </th>
            <th>
                <font>IP</font>
            </th>
            <th>
                <font>ID</font>
            </th>
            <th>
                <font>刪除訊息</font>
            </th>
        </tr>
        <?php if (isset($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <tr class="data">
                    <td><?= htmlspecialchars($msg['name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($msg['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($msg['message'] ?? '') ?></td>
                    <td><?= htmlspecialchars($msg['time'] ?? '') ?></td>
                    <td><?= htmlspecialchars($msg['ip'] ?? '') ?></td>
                    <td><?= htmlspecialchars($msg['id'] ?? '') ?></td>
                    <td bgcolor="#84C1FF">
                        <button type="button" class="btn" onclick="delMessage('<?= $msg['id'] ?>')">刪除</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</body>

</html>