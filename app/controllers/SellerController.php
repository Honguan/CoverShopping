<?php
require_once __DIR__ . '/../models/SellerModel.php';

class SellerController
{
    /**
     * 刪除商品
     * @param $_GET['id'] 商品ID
     */
    public function deleteProduct()
    {
        $id = intval($_GET["id"] ?? 0);
        SellerModel::deleteProduct($id);
        header("Location: /CoverShopping/routes/web.php?page=management_product");
        exit;
    }
    /**
     * 新增商品（含圖片上傳）
     * @param $_POST 商品資料
     * @param $_FILES['image'] 商品圖片
     */
    public function newProduct()
    {
        // 檔案上傳處理
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
            $upload_file = '/public/images/' . $to;
        } else {
            $upload_file = null; // 上傳失敗或檔案不合法
        }
        $id = htmlspecialchars(trim($_POST["id"] ?? ''));
        $name = htmlspecialchars(trim($_POST["name"] ?? ''));
        $content = htmlspecialchars(trim($_POST["content"] ?? ''));
        $price = intval($_POST["price"] ?? 0);
        $inventory = intval($_POST["inventory"] ?? 0);
        session_start();
        $account = htmlspecialchars(trim($_SESSION['account'] ?? ''));
        $success = SellerModel::newProduct($id, $name, $content, $price, $inventory, $account, $upload_file);
        if ($success) {
            header("Location: /CoverShopping/routes/web.php?page=management_product");
        } else {
            echo "<script>alert('產品ID重複請重新輸入');window.location.href='/CoverShopping/routes/web.php?page=new_product';</script>";
        }
        exit;
    }
    /**
     * 更新訂單付款狀態
     * @param $_GET['payment'] 付款狀態
     * @param $_GET['no'] 訂單編號
     */
    public function updatePayment()
    {
        $payment = 0;
        $no = intval($_GET["no"] ?? 0);
        SellerModel::updatePayment($no, $payment);
        echo "no: " . $no . ", payment: " . ($payment ? "true" : "false");
        header("Location: /CoverShopping/routes/web.php?page=management_order");
        exit;
    }
    /**
     * 儲存商品更改（含圖片上傳）
     * @param $_GET['img'] 舊圖片路徑
     * @param $_FILES['image'] 新圖片
     * @param $_GET['id'] 商品ID
     * @param $_POST 商品資料
     */
    public function saveChangeProduct()
    {
        $id = htmlspecialchars(trim($_GET["id"] ?? ''));
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
            $upload_file = $img;
        }
        $name = htmlspecialchars(trim($_POST["name"] ?? ''));
        $content = htmlspecialchars(trim($_POST["content"] ?? ''));
        $price = intval($_POST["price"] ?? 0);
        $inventory = intval($_POST["inventory"] ?? 0);
        SellerModel::saveChangeProduct($id, $name, $content, $price, $inventory, $upload_file);
        header("Location: /CoverShopping/routes/web.php?page=management_product");
        exit;
    }
    /**
     * 更新訂單出貨狀態
     * @param $_GET['Shipping'] 出貨狀態
     * @param $_GET['no'] 訂單編號
     * @param $_GET['id'] 商品ID
     */
    public function updateShipping()
    {
        $Shipping = true;
        $no = intval($_GET["no"] ?? 0);
        $id = intval($_GET["id"] ?? 0);
        SellerModel::updateShipping($no, $id, $Shipping);
        header("Location: /CoverShopping/routes/web.php?page=management_order");
        exit;
    }
    /**
     * 顯示商品更改頁面
     * @param $_GET['id'] 商品ID
     * @return $product 商品資料
     */
    public function showChangeProductPage()
    {
        $id = htmlspecialchars(trim($_GET['id'] ?? ''));
        $product = SellerModel::getProductById($id);
        include __DIR__ . '/../views/Seller/change_product.php';
    }
    /**
     * 顯示訂單管理頁面
     * @return $orders_by_product 商品與訂單分組資料
     */
    public function showManagementOrderPage()
    {
        session_start();
        if (!isset($_SESSION['login']) || !$_SESSION['login']) {
            echo "<h1 align='center'>未登入帳號</h1>";
            header('Refresh:1;url=/CoverShopping/routes/web.php?page=login');
            exit;
        }
        $account = $_SESSION['account'];
        $products = SellerModel::getProductsByAccount($account);
        $orders_by_product = [];
        foreach ($products as $product) {
            $orders = SellerModel::getOrdersByProductId($product->id);
            $orders_by_product[$product->id] = [
                'product' => $product,
                'orders' => $orders
            ];
        }
        include __DIR__ . '/../views/Seller/management_order.php';
    }
    /**
     * 顯示商品管理頁面
     * @return $products 商品資料陣列
     */
    public function showManagementProductPage()
    {
        session_start();
        if (!isset($_SESSION['login']) || !$_SESSION['login']) {
            echo "<h1 align='center'>未登入帳號</h1>";
            header('Refresh:1;url=/CoverShopping/routes/web.php?page=login');
            exit;
        }
        $account = $_SESSION['account'];
        $products = SellerModel::getProductsByAccount($account);
        include __DIR__ . '/../views/Seller/management_product.php';
    }
    /**
     * 顯示新增商品頁面
     * @return $ID 隨機商品ID
     */
    public function showNewProductPage()
    {
        // 隨機碼產生器
        $comb = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $shfl = str_shuffle($comb);
        $ID = substr($shfl, 0, 6);
        include __DIR__ . '/../views/Seller/new_product.php';
    }
    /**
     * 顯示銷售結果頁面
     * @return $sales_data 銷售統計資料
     */
    public function showSalesResultsPage()
    {
        session_start();
        if (!isset($_SESSION['login']) || !$_SESSION['login']) {
            echo "<h1 align='center'>未登入帳號</h1>";
            header('Refresh:1;url=/CoverShopping/routes/web.php?page=login');
            exit;
        }
        $account = $_SESSION['account'];
        $sales_data = SellerModel::getSalesResults($account);
        include __DIR__ . '/../views/Seller/sales_results.php';
    }
}
