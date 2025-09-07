<?php
class OrdersModel
{
    public static function updatePayment($no, $payment)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "UPDATE orders SET payment = '$payment' WHERE no='$no'";
        $result = execute_sql($link, "shopping", $sql);
        mysqli_close($link);
        return $result;
    }
    public static function getOrdersByAccount($account)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "SELECT * FROM orders WHERE name = '$account'";
        $result = execute_sql($link, 'shopping', $sql);
        $orders = [];
        while ($row = mysqli_fetch_object($result)) {
            $orders[] = $row;
        }
        mysqli_free_result($result);
        mysqli_close($link);
        return $orders;
    }
    public static function getCommodityById($id)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "SELECT * FROM commodity WHERE id = '$id'";
        $result = execute_sql($link, 'shopping', $sql);
        $commodity = mysqli_fetch_object($result);
        mysqli_free_result($result);
        mysqli_close($link);
        return $commodity;
    }
}
