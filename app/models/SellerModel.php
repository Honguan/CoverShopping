<?php
class SellerModel 
{
    /**
     * 刪除商品
     * @param string $id 商品ID
     * @return bool 刪除是否成功
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
    /**
     * 更新訂單付款狀態
     * @param string $no 訂單編號
     * @param string $payment 付款方式
     * @return bool 更新是否成功
     */
    public static function updatePayment($no, $payment)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "UPDATE orders SET payment = '$payment' WHERE no='$no'";
        $result = execute_sql($link, "shopping", $sql);
        mysqli_close($link);
        return $result;
    }
    /**
     * 更新訂單運送狀態
     * @param string $no 訂單編號
     * @param string $id 商品ID
     * @param string $Shipping 運送方式
     * @return bool 更新是否成功
     */
    public static function updateShipping($no, $id, $Shipping)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "UPDATE orders SET Shipping = '$Shipping' WHERE no='$no' AND id='$id'";
        $result = execute_sql($link, "shopping", $sql);
        mysqli_close($link);
        return $result;
    }
    /**
     * 儲存商品變更
     * @param string $id 商品ID
     * @param string $name 商品名稱
     * @param string $content 商品描述
     * @param float $price 商品價格
     * @param int $inventory 商品庫存
     * @param string $upload_file 上傳的檔案名稱
     * @return bool 更新是否成功
     */
    public static function saveChangeProduct($id, $name, $content, $price, $inventory, $upload_file)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "UPDATE commodity SET name = '$name', content = '$content', image = '$upload_file', inventory = '$inventory', price = '$price' WHERE id='$id'";
        $result = execute_sql($link, "shopping", $sql);
        mysqli_close($link);
        return $result;
    }
    /**
     * 新增商品
     * @param string $id 商品ID
     * @param string $name 商品名稱
     * @param string $content 商品描述
     * @param float $price 商品價格
     * @param int $inventory 商品庫存
     * @param string $account 賣家帳號
     * @param string $upload_file 上傳的檔案名稱
     * @return bool 新增是否成功
     */
    public static function newProduct($id, $name, $content, $price, $inventory, $account, $upload_file)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "SELECT * FROM commodity WHERE id='$id'";
        $result = execute_sql($link, "shopping", $sql);
        if (mysqli_num_rows($result) == 0) {
            mysqli_free_result($result);
            $sql = "INSERT INTO commodity (`id`, `image`, `name`, `content`, `price`, `inventory`, `account`) VALUES ('$id','$upload_file','$name','$content','$price','$inventory','$account')";
            $result = execute_sql($link, "shopping", $sql);
            mysqli_close($link);
            return true;
        } else {
            mysqli_free_result($result);
            mysqli_close($link);
            return false;
        }
    }
    /**
     * 依商品ID取得商品資料
     * @param string $id 商品ID
     * @return object 商品資料
     */
    public static function getProductById($id)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "SELECT * FROM commodity WHERE id='$id'";
        $result = execute_sql($link, "shopping", $sql);
        $product = mysqli_fetch_object($result);
        mysqli_free_result($result);
        mysqli_close($link);
        return $product;
    }
    /**
     * 依賣家帳號取得所有商品
     * @param string $account 賣家帳號
     * @return array 商品資料陣列
     */
    public static function getProductsByAccount($account)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "SELECT * FROM commodity WHERE account='$account'";
        $result = execute_sql($link, "shopping", $sql);
        $products = [];
        while ($row = mysqli_fetch_object($result)) {
            $products[] = $row;
        }
        mysqli_free_result($result);
        mysqli_close($link);
        return $products;
    }
    /**
     * 依商品ID取得所有訂單
     * @param string $product_id 商品ID
     * @return array 訂單資料陣列
     */
    public static function getOrdersByProductId($product_id)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "SELECT * FROM orders WHERE id='$product_id'";
        $result = execute_sql($link, "shopping", $sql);
        $orders = [];
        while ($row = mysqli_fetch_object($result)) {
            $orders[] = $row;
        }
        mysqli_free_result($result);
        mysqli_close($link);
        return $orders;
    }
    /**
     * 取得賣家商品銷售統計資料
     * @param string $account 賣家帳號
     * @return array 銷售統計資料（商品、銷售數量、百分比、總計）
     */
    public static function getSalesResults($account)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "SELECT * FROM commodity WHERE account='$account'";
        $result = execute_sql($link, "shopping", $sql);
        $commodities = [];
        while ($row = mysqli_fetch_object($result)) {
            $commodities[] = $row;
        }
        mysqli_free_result($result);
        $sales_data = [];
        $total_sales = 0;
        foreach ($commodities as $commodity) {
            $sql = "SELECT COUNT(id) AS sales_count FROM orders WHERE id='{$commodity->id}' AND name !='$account'";
            $result = execute_sql($link, "shopping", $sql);
            $row = mysqli_fetch_object($result);
            $sales_count = $row ? $row->sales_count : 0;
            $sales_data[] = [
                'id' => $commodity->id,
                'name' => $commodity->name,
                'sales_count' => $sales_count
            ];
            $total_sales += $sales_count;
            mysqli_free_result($result);
        }
        mysqli_close($link);
        // 計算百分比
        foreach ($sales_data as &$data) {
            $data['percent'] = $total_sales > 0 ? round($data['sales_count'] / $total_sales, 4) * 100 : 0;
        }
        return [
            'sales_data' => $sales_data,
            'total_sales' => $total_sales
        ];
    }
}
