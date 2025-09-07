<script language="javascript" src="/CoverShopping/public/js/Admin.js"></script>
<link rel="stylesheet" href="/CoverShopping/public/css/Modules.css">
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['administrator'])) {
    echo "<h1 align='center'><font color='#000000'>管理者:" . $_SESSION["administrator"] . "</font>
    <input type='button' class='btn' value='登出' onclick='log_out()'>
    </h1>";
    echo "<div style='text-align:center'>
    <input type='button' class='btn' value='商品總管' onclick='product()'>
    <input type='button' class='btn' value='帳號總管' onclick='member()'>
    <input type='button' class='btn' value='訂單總管' onclick='order()'>
    <input type='button' class='btn' value='留言總管' onclick='message()'>
    </div>";
} else {
    echo "<h1 align='center'>未登入管理員帳號</h1>";
    header('Refresh:1;url=../../CoverShopping/routes/web.php?page=loginPage');
    exit;
}
