<!doctype html>
<html>

<head>
    <title>帳號總管</title>
    <meta charset="utf-8">
    <script language="javascript" src="/CoverShopping/public/js/Admin.js"></script>
    <link rel="stylesheet" type="text/css" href="/CoverShopping/public/css/Modules.css">
</head>

<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/CoverShopping/public/common/admin_header.php'; ?>
    <p align="center">帳號總管</p>
    <form name="myForm" method="post">
        <table class="seller-management" align="center">
            <tr class="header">
                <th>帳號ID</th>
                <th>名稱</th>
                <th>密碼</th>
                <th>e-mail</th>
                <th>生日</th>
                <th>啟用帳號</th>
                <th>驗證碼</th>
                <th>登陸許可</th>
                <th>修改</th>
                <th>刪除</th>
            </tr>
            <?php if (isset($members)): ?>
                <?php foreach ($members as $row): ?>
                    <tr class="data">
                        <td><b><?= htmlspecialchars($row['account']) ?></b></td>
                        <td><b><?= htmlspecialchars($row['name']) ?></b></td>
                        <td><?= htmlspecialchars($row['password']) ?></td>
                        <td><?= htmlspecialchars($row['mail']) ?></td>
                        <td><?= htmlspecialchars($row['Birthday']) ?></td>
                        <td><?= htmlspecialchars($row['switch']) ?></td>
                        <td><?= htmlspecialchars($row['checkcode']) ?></td>
                        <td><?= htmlspecialchars($row['login']) ?></td>
                        <td><button type="button" class="btn" onclick="change('<?= $row['account'] ?>')">修改</button></td>
                        <td><button type="button" class="btn" onclick="delMember('<?= $row['account'] ?>')">刪除</button></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </form>
</body>

</html>