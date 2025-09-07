<?php
class ShoppingModel
{
    public static function addToCart($id, $name, $price, $quantity)
    {
        if (empty($quantity)) $quantity = 1;
        if (empty($_COOKIE["id_list"])) {
            setcookie("id_list", $id);
            setcookie("name_list", $name);
            setcookie("price_list", $price);
            setcookie("quantity_list", $quantity);
        } else {
            $id_array = explode(",", $_COOKIE["id_list"]);
            $name_array = explode(",", $_COOKIE["name_list"]);
            $price_array = explode(",", $_COOKIE["price_list"]);
            $quantity_array = explode(",", $_COOKIE["quantity_list"]);
            if (in_array($id, $id_array)) {
                $key = array_search($id, $id_array);
                $quantity_array[$key] += $quantity;
            } else {
                $id_array[] = $id;
                $name_array[] = $name;
                $price_array[] = $price;
                $quantity_array[] = $quantity;
            }
            setcookie("id_list", implode(",", $id_array));
            setcookie("name_list", implode(",", $name_array));
            setcookie("price_list", implode(",", $price_array));
            setcookie("quantity_list", implode(",", $quantity_array));
        }
    }
    public static function changeProductQuantity($id, $newquantity)
    {
        $id_array = explode(",", $_COOKIE["id_list"]);
        $quantity_array = explode(",", $_COOKIE["quantity_list"]);
        $key = array_search($id, $id_array);
        // 確保數量為整數且不小於1
        $quantity_array[$key] = (is_numeric($newquantity) && intval($newquantity) > 0) ? intval($newquantity) : 1;
        setcookie("quantity_list", implode(",", $quantity_array));
        session_write_close();
    }
    public static function deleteAll()
    {
        setcookie("id_list", "");
        setcookie("name_list", "");
        setcookie("price_list", "");
        setcookie("quantity_list", "");
        session_write_close();
    }
    public static function deleteProduct($id)
    {
        $id_array = explode(",", $_COOKIE["id_list"]);
        $name_array = explode(",", $_COOKIE["name_list"]);
        $price_array = explode(",", $_COOKIE["price_list"]);
        $quantity_array = explode(",", $_COOKIE["quantity_list"]);
        $key = array_search($id, $id_array);
        array_splice($id_array, $key, 1);
        array_splice($name_array, $key, 1);
        array_splice($price_array, $key, 1);
        array_splice($quantity_array, $key, 1);
        setcookie("id_list", implode(",", $id_array));
        setcookie("name_list", implode(",", $name_array));
        setcookie("price_list", implode(",", $price_array));
        setcookie("quantity_list", implode(",", $quantity_array));
    }
    public static function createOrder($freight, $account)
    {
        // 先檢查庫存
        if (!self::checkInventory()) {
            // 庫存不足，不建立訂單
            return false;
        }
        require_once __DIR__ . '/../../config/database.php';
        $id_array = explode(",", $_COOKIE["id_list"]);
        $name_array = explode(",", $_COOKIE["name_list"]);
        $price_array = explode(",", $_COOKIE["price_list"]);
        $quantity_array = explode(",", $_COOKIE["quantity_list"]);
        $link = create_connection();
        $comb = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $shfl = str_shuffle($comb);
        $no = substr($shfl, 0, 6);
        date_default_timezone_set("Asia/Taipei");
        $time = date("Y-m-d H:i:s");
        for ($i = 0; $i < count($id_array); $i++) {
            $total = $price_array[$i] * $quantity_array[$i];
            $sql_orders = "INSERT INTO `orders`(`no`,`name`,`id`, `quantity`,`freight` , `time`) VALUES ('$no','$account','$id_array[$i]','$quantity_array[$i]',$freight,'$time')";
            $sql_search = "SELECT * FROM commodity WHERE id='$id_array[$i]'";
            $result = execute_sql($link, "shopping", $sql_search);
            $row = mysqli_fetch_object($result);
            $inventory = $row ? $row->inventory : 0;
            mysqli_free_result($result);
            $inventory -= $quantity_array[$i];
            $sql_UPDATE = "UPDATE commodity SET inventory = '$inventory' WHERE name='$name_array[$i]'";
            execute_sql($link, 'shopping', $sql_UPDATE);
            execute_sql($link, 'shopping', $sql_orders);
        }
        ShoppingModel::deleteAll();
        mysqli_close($link);
        return true;
    }

    public static function checkInventory()
    {
        //查詢資料庫商品數量是否足夠
        require_once __DIR__ . '/../../config/database.php';
        $id_array = explode(",", $_COOKIE["id_list"]);
        $quantity_array = explode(",", $_COOKIE["quantity_list"]);
        $link = create_connection();
        for ($i = 0; $i < count($id_array); $i++) {
            $sql_search = "SELECT * FROM commodity WHERE id='$id_array[$i]'";
            $result = execute_sql($link, "shopping", $sql_search);
            $row = mysqli_fetch_object($result);
            if (!$row || $row->inventory - $quantity_array[$i] < 0) {
                // 只要有一個商品庫存不足就 return false
                mysqli_free_result($result);
                return false;
            }
            mysqli_free_result($result);
        }
        // 全部商品庫存都足夠才 return true
        return true;
    }

    /**
     * 取得購物車資料（從 cookie）
     * @return array 購物車商品資料
     */
    public static function getCartData()
    {
        $id_array = isset($_COOKIE['id_list']) ? explode(',', $_COOKIE['id_list']) : [];
        $name_array = isset($_COOKIE['name_list']) ? explode(',', $_COOKIE['name_list']) : [];
        $price_array = isset($_COOKIE['price_list']) ? explode(',', $_COOKIE['price_list']) : [];
        $quantity_array = isset($_COOKIE['quantity_list']) ? explode(',', $_COOKIE['quantity_list']) : [];
        $cart = [];
        for ($i = 0; $i < count($id_array); $i++) {
            $cart[] = [
                'id' => $id_array[$i],
                'name' => $name_array[$i],
                'price' => $price_array[$i],
                'quantity' => $quantity_array[$i],
                'subtotal' => $price_array[$i] * $quantity_array[$i]
            ];
        }
        return $cart;
    }
}
