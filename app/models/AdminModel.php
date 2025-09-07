<?php
class AdminModel
{
    /**
     * 刪除會員
     */
    public static function deleteMember($account)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "DELETE FROM register WHERE account='$account'";
        $result = execute_sql($link, "shopping", $sql);
        mysqli_close($link);
        return $result;
    }
    /**
     * 刪除留言
     */
    public static function deleteMessage($id)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "DELETE FROM information WHERE id='$id'";
        $result = execute_sql($link, "contact", $sql);
        mysqli_close($link);
        return $result;
    }
    /**
     * 刪除訂單
     */
    public static function deleteOrder($no)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "DELETE FROM orders WHERE no='$no'";
        $result = execute_sql($link, "shopping", $sql);
        $affected = mysqli_affected_rows($link);
        mysqli_close($link);
        return $affected;
    }
    /**
     * 刪除商品
     */
    public static function deleteProduct($id)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "DELETE FROM commodity WHERE id='$id'";
        $result = execute_sql($link, "shopping", $sql);
        mysqli_close($link);
        return $result;
    }

    public static function updateChangeProduct($id, $name, $content, $price, $inventory, $image)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "UPDATE commodity SET name='$name', content='$content', price='$price', inventory='$inventory', image='$image' WHERE id='$id'";
        $result = execute_sql($link, "shopping", $sql);
        mysqli_close($link);
        return $result;
    }
    /**
     * 更新訂單付款狀態
     */
    public static function updateOrderPayment($no, $payment)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "UPDATE orders SET payment = '$payment' WHERE no='$no'";
        $result = execute_sql($link, "shopping", $sql);
        mysqli_close($link);
        return $result;
    }
    /**
     * 更新會員資料
     */
    public static function updateMember($account, $name, $password, $Birthday, $email, $checkcode, $switch, $login)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "UPDATE register SET name = '$name', password = '$password', mail = '$email', Birthday = '$Birthday', switch = '$switch', checkcode = '$checkcode', login = '$login' WHERE account='$account'";
        $result = execute_sql($link, "shopping", $sql);
        mysqli_close($link);
        return $result;
    }
    /**
     * 更新訂單運送資訊
     */
    public static function updateOrderShipping($no, $id, $Shipping)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "UPDATE orders SET Shipping = '$Shipping' WHERE no='$no' AND id='$id'";
        $result = execute_sql($link, "shopping", $sql);
        mysqli_close($link);
        return $result;
    }
    /**
     * 取得單一會員資料
     * 對應會員資料更改頁面 views/Admin/change.php
     * 回傳會員物件
     */
    public static function getMember($account)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "SELECT * FROM register WHERE account='$account'";
        $result = execute_sql($link, "shopping", $sql);
        $member = mysqli_fetch_object($result);
        mysqli_free_result($result);
        mysqli_close($link);
        return $member;
    }
    /**
     * 取得所有會員資料
     * 對應會員管理頁面 views/Admin/member.php
     * 回傳會員陣列
     */
    public static function getAllMembers()
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "SELECT * FROM register";
        $result = execute_sql($link, "shopping", $sql);
        $members = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $members[] = $row;
        }
        mysqli_free_result($result);
        mysqli_close($link);
        return $members;
    }
    /**
     * 取得所有留言資料
     * 對應留言管理頁面 views/Admin/message.php
     * 回傳留言陣列
     */
    public static function getAllMessages()
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "SELECT * FROM information";
        $result = execute_sql($link, "contact", $sql);
        $messages = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $messages[] = $row;
        }
        mysqli_free_result($result);
        mysqli_close($link);
        return $messages;
    }
    /**
     * 取得所有訂單資料（含商品資訊），依時間排序
     * 對應訂單管理頁面 views/Admin/order.php
     * 回傳訂單物件陣列
     */
    public static function getAllOrders()
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        // 依時間降冪排序
        $sql = "SELECT * FROM orders ORDER BY time DESC";
        $result = execute_sql($link, "shopping", $sql);
        $orders = [];
        while ($row = mysqli_fetch_object($result)) {
            // 取得商品資訊
            $sql2 = "SELECT * FROM commodity WHERE id='{$row->id}'";
            $commodity_result = execute_sql($link, "shopping", $sql2);
            $commodity = mysqli_fetch_object($commodity_result);
            mysqli_free_result($commodity_result);
            $row->commodity_name = $commodity ? $commodity->name : '';
            $row->commodity_price = $commodity ? $commodity->price : 0;
            $orders[] = $row;
        }
        mysqli_free_result($result);
        mysqli_close($link);
        return $orders;
    }
    /**
     * 取得所有商品資料
     * 對應商品管理頁面 views/Admin/product.php
     * 回傳商品陣列
     */
    public static function getAllProducts()
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "SELECT * FROM commodity";
        $result = execute_sql($link, "shopping", $sql);
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
        mysqli_free_result($result);
        mysqli_close($link);
        return $products;
    }
}
