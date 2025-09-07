<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>資料更改</title>
    <script language="javascript" src="/CoverShopping/public/js/Admin.js"></script>
    <link rel="stylesheet" type="text/css" href="/CoverShopping/public/css/Modules.css">
</head>

<body>
    <form name="myForm" style="text-align:center" method="post">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/CoverShopping/public/common/admin_header.php'; ?>
        <table class="seller-management" border="1" rules="all" align="center">
            <?php
            $account = $_GET["account"];
            // 調用資料
            if (isset($member) && is_object($member)) {
                $name = isset($member->name) ? $member->name : '';
                $password = isset($member->password) ? $member->password : '';
                $Birthday = isset($member->Birthday) ? $member->Birthday : '';
                $email = isset($member->mail) ? $member->mail : '';
                $checkcode = isset($member->checkcode) ? $member->checkcode : '';
                $switch = isset($member->switch) ? $member->switch : '';
                $login = isset($member->login) ? $member->login : '';
            }
            ?>
            <caption><b>
                    <h2>帳號:<?php echo $account; ?></h2>
                </b></caption>
            <tr class="header">
                <th>資料屬性</th>
                <th>更新資料</th>
            </tr>
            <tr class="data">
                <td>姓名</td>
                <td><input type="text" name="name" value="<?php echo isset($name) ? $name : ''; ?>"></td>
            </tr>
            <tr class="data">
                <td>密碼</td>
                <td><input type="text" name="password" value="<?php echo isset($password) ? $password : ''; ?>"></td>
            </tr>
            <tr class="data">
                <td class="text">生日</td>
                <td><input type="date" name="Birthday" value="<?php echo isset($Birthday) ? $Birthday : ''; ?>"></td>
            </tr>
            <tr class="data">
                <td>電子郵件</td>
                <td><input type="text" name="email" value="<?php echo isset($email) ? $email : ''; ?>"></td>
            </tr>
            <tr class="data">
                <td>帳號是否認證</td>
                <td><select name="switch" value="<?php echo isset($switch) ? $switch : ''; ?>">
                        <option value="enable" <?php if (isset($switch) && $switch == "enable") echo "selected"; ?>>是</option>
                        <option value="disable" <?php if (isset($switch) && $switch == "disable") echo "selected"; ?>>否</option>
                    </select></td>
            </tr>
            <tr class="data">
                <td>驗證碼</td>
                <td>
                    <span 
                        style="user-select: none; background: #f5f5f5; padding: 2px 8px; border-radius: 4px;" 
                        title="隨機碼會自動產生"
                    >
                        <?php echo isset($checkcode) ? htmlspecialchars($checkcode) : ''; ?>
                    </span>
                </td>
            </tr>
            <tr class="data">
                <td>登陸允許</td>
                <td><select name="login" value="<?php echo isset($login) ? $login : ''; ?>">
                        <option value="1" <?php if (isset($login) && $login == "1") echo "selected"; ?>>啟用</option>
                        <option value="0" <?php if (isset($login) && $login == "0") echo "selected"; ?>>禁用</option>
                    </select></td>
            </tr>
        </table>
        <div style="text-align:center; margin-top:20px;">
            <button type="button" class="btn" onclick="save('<?php echo $account; ?>')">儲存</button>
            <button type="reset" class="btn">重設</button>
        </div>
        <br>
        <p align="center">
            <a href="/CoverShopping/routes/web.php?page=member">
                <button type="button" class="btn">返回管理頁面</button>
            </a>
        </p>
    </form>
</body>

</html>