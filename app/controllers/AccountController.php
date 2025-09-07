<?php
require_once __DIR__ . '/../models/AccountModel.php';
require_once dirname(__DIR__, 2) . '/config/phpmail.tools.php';
echo '<script src="../public/js/Account.js"></script>';
class AccountController
{
    public function logout()
    {
        session_start();
        unset($_SESSION['administrator']);
        unset($_SESSION['account']);
        unset($_SESSION['login']);
        unset($_SESSION['name']);
        header("Location: /CoverShopping/routes/web.php?page=index");
        exit;
    }

    public function register()
    {
        header("Content-type: text/html; charset=utf-8");

        // 取得表單資料
        $name = htmlspecialchars(trim($_POST["name"] ?? ''));
        $account = htmlspecialchars(trim($_POST["account"] ?? ''));
        $password = htmlspecialchars(trim($_POST["password"] ?? ''));
        $to_mail = filter_var(trim($_POST["mail"] ?? ''), FILTER_VALIDATE_EMAIL) ? trim($_POST["mail"]) : '';
        $Birthday = htmlspecialchars(trim($_POST["Birthday"] ?? ''));
        // 隨機碼產生器
        $comb = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $shfl = str_shuffle($comb);
        $checkcode = substr($shfl, 0, 6);

        $exists = AccountModel::accountExists($account);
        if ($exists) {
            echo "<h1 align='center'>帳號已存在</h1>";
            header('Refresh:1;url=/CoverShopping/routes/web.php?page=registerPage');
            exit;
        } else if (empty($account)) {
            echo "<h1 align='center'>錯誤連結</h1>";
            header('Refresh:1;url=/CoverShopping/routes/web.php?page=loginPage');
            exit;
        } else {
            $result = AccountModel::createAccount($name, $account, $password, $to_mail, $Birthday, $checkcode);
        }
        if (!empty($result)) {

            // 預設完整連結
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'];
            // 假設 checkcode.php 在 routes/web.php?page=checkcode
            $get_code_url = $protocol . $host . "/CoverShopping/routes/web.php?page=checkcode&account=$account&checkcode=$checkcode";

            $message = "<h1 style='text-align:center;'>帳戶啟用連結</h1>";
            $message .= "<h2><p style='text-align:center;'><b>帳號: $account</b></p></h2>";
            $message .= "<h2><p style='text-align:center;'><b>驗證碼: $checkcode</b></p></h2>";
            $message .= "<p style='text-align:center;'><b>點選超連結，輸入驗證碼</b></p>";
            $message .= "<h3><p style='text-align:center;'><a href='$get_code_url'>啟用我的帳戶</a></p></h3>";

            $subject = "=?utf-8?B?" . base64_encode("[超皮購物]您的驗證碼") . "?=";
            $from_to_name = "hgchen";
            $from_mail = "hgchen@hgchen.eu.org";

            $mail = pmail();
            $mail->setFrom($from_mail, $from_to_name);
            $mail->addAddress($to_mail);
            $mail->Subject = $subject;
            $mail->msgHTML($message);

            if (!$mail->send()) {
                echo "<h1 align='center'>Mailer send Error:</h1> " . $mail->ErrorInfo;
            } else {
                echo "<h1 align='center'>成功發送驗證碼，請查收郵箱</h1>";
                echo "<p align='center'>三秒後到登入頁面。</p>";
                header('Refresh:3;url=/CoverShopping/routes/web.php?page=loginPage');
            }
        } else {
            echo "<p align='center'>帳號已經存在。</p>";
            echo "<p align='center'>三秒後回到註冊頁面。</p>";
            header('Refresh:3;url=/CoverShopping/routes/web.php?page=registerPage   ');
        }
    }

    public function login()
    {
        header("Content-type: text/html; charset=utf-8");

        $account = htmlspecialchars(trim($_POST["account"] ?? ''));
        $password = htmlspecialchars(trim($_POST["password"] ?? ''));
        $user = AccountModel::getUser($account);
        $admin = AccountModel::getAdmin($account);

        session_start();
        if ($admin && $admin->password == $password) {
            $_SESSION['administrator'] = $account;
            header("Location: /CoverShopping/routes/web.php?page=member");
            exit;
        } else if (!$user) {
            echo "<script>not_account();</script>";
            exit;
        } else if ($user->password != $password) {
            echo "<script>password_error();</script>";
            exit;
        } else if ($user->switch == "disable") {
            echo "<script>enable();</script>";
            exit;
        } else if (!($user->login)) {
            echo "<script>pause();</script>";
            exit;
        } else if ((isset($_SESSION['login']) && $_SESSION['login'])) {
            $account_SESSION = $_SESSION['account'];
            echo "<script>login('$account_SESSION');</script>";
            exit;
        } else if ($user->password == $password) {
            $_SESSION['login'] = true;
            $_SESSION['account'] = $account;
            $_SESSION['name'] = $user->name;
            echo "<h1 align='center'>登入成功</h1>";
            echo "<script>welcome();</script>";
            exit;
        }
    }

    public function checkcode()
    {
        $account = htmlspecialchars(trim($_GET["account"] ?? ''));
        $checkcode = htmlspecialchars(trim($_GET["checkcode"] ?? ''));
        $user = AccountModel::getUser($account);
        $error = true;
        $checkcode_database = '';
        $switch = '';
        if ($user) {
            $checkcode_database = $user->checkcode;
            $switch = $user->switch;
            $error = false;
        }
        if ($error) {
            echo "<h1 align='center'>錯誤連結</h1>";
            header('Refresh:3;url=/CoverShopping/routes/web.php?page=index');
            exit;
        }
        if ($switch == "enable") {
            echo "<h1 align='center'>帳號已激活</h1>";
            echo "<p align='center'>三秒後轉入登入頁面。</p>";
            header('Refresh:3;url=/CoverShopping/routes/web.php?page=index');
            exit;
        }
        return [
            'account' => $account,
            'checkcode' => $checkcode,
            'checkcode_database' => $checkcode_database
        ];
    }

    public function checkcodeDatabase()
    {
        $account = htmlspecialchars(trim($_GET["account"] ?? ''));
        if (empty($account)) {
            echo "<h1 align='center'>錯誤連結</h1>";
            echo "<script>setTimeout(function(){window.location.href='/CoverShopping/routes/web.php?page=registerPage';}, 1500);</script>";
            exit;
        } else {
            AccountModel::activateAccount($account);
            echo "<h1 align='center'>成功激活帳號</h1>";
            echo "<script>setTimeout(function(){window.location.href='/CoverShopping/routes/web.php?page=loginPage';}, 1500);</script>";
            exit;
        }
    }
    public function showLoginPage()
    {
        include __DIR__ . '/../views/Account/login.html';
    }
    public function showRegisterPage()
    {
        include __DIR__ . '/../views/Account/register.html';
    }
    public function showCheckcodePage()
    {
        include __DIR__ . '/../views/Account/checkcode.php';
    }
}
