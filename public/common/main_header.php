<script language="javascript" src="/CoverShopping/public/js/Seller.js"></script>
<link rel="stylesheet" href="/CoverShopping/public/css/Modules.css">
<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (isset($_SESSION['login']) && $_SESSION['login']) {
  echo "<p nowrap align='center'><font color='#ffffffff'>使用者:" . $_SESSION["account"] . "</font>
        <input type='button' class='btn' value='登出' onclick='log_out()'>
        <input type='button' class='btn' value='賣家模式' onclick='management_product()'>
        <input type='button' class='btn' value='您的訂單' onclick='orders()'>
        </p>";
} else {
  echo '<div style="text-align:center;">';
  echo '<button type="button" class="btn" onclick="window.open(\'/CoverShopping/routes/web.php?page=registerPage\',\'_self\')">註冊</button>';
  echo '<button type="button" class="btn" onclick="window.open(\'/CoverShopping/routes/web.php?page=loginPage\',\'_self\')">登入</button>';
  echo '</div>';
}
