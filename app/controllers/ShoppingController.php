<?php
require_once __DIR__ . '/../models/ShoppingModel.php';

class ShoppingController
{
    public function addShoppingCart()
    {
        $id = htmlspecialchars(trim($_GET["id"] ?? ''));
        $name = htmlspecialchars(trim($_GET["name"] ?? ''));
        $price = intval($_GET["price"] ?? 0);
        $quantity = intval($_POST["quantity"] ?? 1);
        ShoppingModel::addToCart($id, $name, $price, $quantity);
        echo '<script>alert("您所選取的產品及數量已成功放入購物車！");window.location.href = "/CoverShopping/routes/web.php?page=index";</script>';
        exit;
    }
    public function changeProduct()
    {
        $id = htmlspecialchars(trim($_GET['id'] ?? ''));
        $i = intval($_GET['i'] ?? 0);
        $newquantity = intval($_POST['newquantity'] ?? 1);
        ShoppingModel::changeProductQuantity($id, $newquantity);
        header("Location: /CoverShopping/routes/web.php?page=shopping_cart");
        exit;
    }
    public function createOrder()
    {
        $freight = htmlspecialchars(trim($_POST["freight"] ?? ''));
        session_start();
        $account = htmlspecialchars(trim($_SESSION["account"] ?? ''));
        $result = ShoppingModel::createOrder($freight, $account);
        if ($result) {
            $_SESSION['order_msg'] = '<div style="color:green;text-align:center;font-size:20px;margin:10px 0;">訂單已成功建立！</div>';
        } else {
            $_SESSION['order_msg'] = '<div style="color:red;text-align:center;font-size:20px;margin:10px 0;">訂單建立失敗，庫存不足！</div>';
        }
        header("Location: /CoverShopping/routes/web.php?page=shopping_cart");
        exit;
    }
    /**
     * 清空購物車
     * 會清除所有購物車相關 cookie
     */
    public function deleteAll()
    {
        ShoppingModel::deleteAll();
        session_write_close();
        header("Location: /CoverShopping/routes/web.php?page=shopping_cart");
        exit;
    }
    public function deleteProduct()
    {
        $id = htmlspecialchars(trim($_GET["id"] ?? ''));
        ShoppingModel::deleteProduct($id);
        header("Location: /CoverShopping/routes/web.php?page=shopping_cart");
        exit;
    }
    /**
     * 顯示購物車頁面
     * @return $cart 購物車商品資料
     */
    public function showCartPage()
    {
        session_start();
        $cart = ShoppingModel::getCartData();
        include __DIR__ . '../../views/Shopping/shopping_cart.php';
    }
}
