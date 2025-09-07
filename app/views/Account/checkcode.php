<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>驗證碼</title>
</head>
<script src="../../public/js/Account.js"></script>
  <link rel="stylesheet" href="/CoverShopping/public/css/Modules.css">

<body>
    <?php
    require_once __DIR__ . "/../../controllers/AccountController.php";
    $controller = new AccountController();
    $data = $controller->checkcode();
    ?>
    <form name="myForm" method="post">
        <div align="center">
            <h1 align="center">帳號:<b><?php echo $data['account'] ?></b></h1>
            請輸入驗證碼:<input type="text" name="code" value="<?php echo $data['checkcode'] ?>">
            <p align="center"><input type="button" value="送出" onclick="CAPTCHA('<?php echo $data['account'] ?>','<?php echo $data['checkcode_database'] ?>')"></p>
        </div>
    </form>
</body>

</html>