<?php
require_once __DIR__ . '/../models/AdminModel.php';
require_once dirname(__DIR__, 2) . '/config/phpmail.tools.php';
/**
 * 管理者相關控制器
 * 對應 views/Admin 資料夾各管理頁面
 */
class AdminController
{
    /**
     * 商品管理頁面
     * 對應 views/Admin/product.php
     * 取得所有商品資料並載入 view
     */
    public function showProductPage()
    {
        $products = AdminModel::getAllProducts();
        require __DIR__ . '/../views/Admin/product.php';
    }

    /**
     * 訂單管理頁面
     * 對應 views/Admin/order.php
     * 取得所有訂單資料（含商品資訊）並載入 view
     */
    public function showOrderPage()
    {
        $orders = AdminModel::getAllOrders();
        require __DIR__ . '/../views/Admin/order.php';
    }

    /**
     * 會員管理頁面
     * 對應 views/Admin/member.php
     * 取得所有會員資料並載入 view
     */
    public function showMemberPage()
    {
        $members = AdminModel::getAllMembers();
        require __DIR__ . '/../views/Admin/member.php';
    }

    /**
     * 留言管理頁面
     * 對應 views/Admin/message.php
     * 取得所有留言資料並載入 view
     */
    public function showMessagePage()
    {
        $messages = AdminModel::getAllMessages();
        require __DIR__ . '/../views/Admin/message.php';
    }

    /**
     * 會員資料更改頁面
     * 對應 views/Admin/change.php
     * 取得單一會員資料並載入 view
     */
    public function showChangePage()
    {
        $account = htmlspecialchars(trim($_GET["account"] ?? ''));
        $member = AdminModel::getMember($account);
        require __DIR__ . '/../views/Admin/change.php';
    }

    public function deleteMember()
    {
        header("Content-type: text/html; charset=utf-8");
        $delete_account = htmlspecialchars(trim($_GET["account"] ?? ''));
        AdminModel::deleteMember($delete_account);
        header("Location: /CoverShopping/routes/web.php?page=member");
        exit;
    }
    public function deleteMessage()
    {
        header("Content-type: text/html; charset=utf-8");
        $delete_message_id = intval($_GET["id"] ?? 0);
        AdminModel::deleteMessage($delete_message_id);
        header("Location: /CoverShopping/routes/web.php?page=message");
        exit;
    }
    public function deleteOrder()
    {
        header("Content-type: text/html; charset=utf-8");
        $no = intval($_GET["no"] ?? 0);
        $affected = AdminModel::deleteOrder($no);
        if ($affected == 0) {
            echo "<script>
            alert('訂單編號錯誤或不存在');
            window.location.href = 'order';
            </script>";
            exit;
        }
        header("Location: /CoverShopping/routes/web.php?page=order");
        exit;
    }
    public function deleteProduct()
    {
        header("Content-type: text/html; charset=utf-8");
        $id = intval($_GET["id"] ?? 0);
        AdminModel::deleteProduct($id);
        header("Location: /CoverShopping/routes/web.php?page=product");
        exit;
    }

    public function updateChangeProduct()
    {
        // 檔案上傳處理
        $id = intval($_GET["id"] ?? 0);
        $img = basename($_GET["img"] ?? '');
        $upload_dir = __DIR__ . '/../../public/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $ext = pathinfo($_FILES["image"]["name"] ?? '', PATHINFO_EXTENSION);
        $to = 'img_' . uniqid('', true) . ($ext ? ('.' . $ext) : '');
        $web_path = '/public/images/' . $to;
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $file_type = $_FILES["image"]["type"] ?? '';
        $file_size = $_FILES["image"]["size"] ?? 0;
        $has_file = isset($_FILES["image"]) && $_FILES["image"]["error"] === UPLOAD_ERR_OK;
        $is_valid = $has_file && in_array($file_type, $allowed_types) && $file_size > 0 && $file_size <= $max_size;
        if ($is_valid && move_uploaded_file($_FILES["image"]["tmp_name"], $upload_dir . $to)) {
            $upload_file = $web_path;
            // 僅刪除 public/images 目錄下的舊檔案，避免誤刪
            if ($img && $img !== $to) {
                $old_file = $upload_dir . $img;
                if (is_file($old_file)) {
                    unlink($old_file);
                }
            }
        } elseif ($has_file) {
            $upload_file = null;
            echo "<script>alert('圖片上傳失敗或格式不符，請重新選擇。');window.location.href='/CoverShopping/routes/web.php?page=change_product&id=$id';</script>";
            exit;
        } else {
            $upload_file = '/public/images/' . $img;
        }
        $id = intval($_GET["id"] ?? 0);
        $name = htmlspecialchars(trim($_POST["name"] ?? ''));
        $content = htmlspecialchars(trim($_POST["content"] ?? ''));
        $price = intval($_POST["price"] ?? 0);
        $inventory = intval($_POST["inventory"] ?? 0);
        AdminModel::updateChangeProduct($id, $name, $content, $price, $inventory, $upload_file);
        header("Location: /CoverShopping/routes/web.php?page=product");
        exit;
    }


    public function updateOrderPayment()
    {
        header("Content-type: text/html; charset=utf-8");
        $payment = htmlspecialchars(trim($_GET["payment"] ?? ''));
        $no = intval($_GET["no"] ?? 0);
        AdminModel::updateOrderPayment($no, $payment);
        header("Location: /CoverShopping/routes/web.php?page=order");
        exit;
    }
    public function updateMember()
    {
        header("Content-type: text/html; charset=utf-8");

        $account = htmlspecialchars(trim($_GET["account"] ?? ''));
        $name = htmlspecialchars(trim($_POST["name"] ?? ''));
        $password = htmlspecialchars(trim($_POST["password"] ?? ''));
        $Birthday = htmlspecialchars(trim($_POST["Birthday"] ?? ''));
        $email = filter_var(trim($_POST["email"] ?? ''), FILTER_VALIDATE_EMAIL) ? trim($_POST["email"]) : '';
        $switch = htmlspecialchars(trim($_POST["switch"] ?? ''));
        $login = htmlspecialchars(trim($_POST["login"] ?? ''));

        // 隨機碼產生器
        $comb = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $shfl = str_shuffle($comb);
        $checkcode = substr($shfl, 0, 6);

        AdminModel::updateMember($account, $name, $password, $Birthday, $email, $checkcode, $switch, $login);

        if ($switch == "disable") {
            $mail_result = $this->resendActivationMail($account, $checkcode, $email);
            if ($mail_result) {
                echo "<script>alert('啟用信件已寄送至 " . $email . "，請查收並點擊連結完成驗證。'); window.location.href='/CoverShopping/routes/web.php?page=member';</script>";
            } else {
                echo "<script>alert('信件寄送失敗，請確認郵箱或聯絡管理員。'); window.location.href='/CoverShopping/routes/web.php?page=member';</script>";
            }
        } else {
            header("Location: /CoverShopping/routes/web.php?page=member");
        }
        exit;
    }
    public function updateOrderShipping()
    {
        header("Content-type: text/html; charset=utf-8");
        $Shipping = htmlspecialchars(trim($_GET["Shipping"] ?? ''));
        $no = intval($_GET["no"] ?? 0);
        $id = intval($_GET["id"] ?? 0);
        AdminModel::updateOrderShipping($no, $id, $Shipping);
        header("Location: /CoverShopping/routes/web.php?page=order");
        exit;
    }
    /**
     * 重新寄送帳戶啟用信件
     * @param string $account
     * @param string $checkcode
     * @param string $to_mail
     */
    public function resendActivationMail($account, $checkcode, $to_mail)
    {
        // 預設完整連結
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        // 假設 checkcode.php 在 routes/web.php?page=checkcode
        $account = htmlspecialchars(trim($account));
        $checkcode = htmlspecialchars(trim($checkcode));
        $to_mail = filter_var(trim($to_mail), FILTER_VALIDATE_EMAIL) ? trim($to_mail) : '';
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
            error_log('Mailer Error: ' . $mail->ErrorInfo);
        } else {
            return $mail->send();
        }
    }
}
