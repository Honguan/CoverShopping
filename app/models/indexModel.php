<?php
class IndexModel
{
    /**
     * 取得所有商品資料
     * @return array 商品資料陣列
     */
    public static function getAllProducts()
    {
        require_once(__DIR__ . '/../../config/database.php');
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
