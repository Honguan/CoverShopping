<script language="javascript" src="/CoverShopping/public/js/Seller.js"></script>
<link rel="stylesheet" href="/CoverShopping/public/css/Modules.css">
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['login']) && $_SESSION['login']) {
    echo "<div style='text-align:center'><font color='#000000'>買家:" . $_SESSION["account"] . "</font>
    <input type='button' class='btn' value='登出' onclick='log_out()'><br><br>
    <button type='button' class='btn' onclick=\"window.open('/CoverShopping/routes/web.php?page=index','_self')\">購物網站</button>
    </div>";
} else {
    echo "<h1 align='center'>未登入帳號</h1>";
    header('Refresh:1;url=/CoverShopping/routes/web.php?page=loginPage');
    exit;
}
