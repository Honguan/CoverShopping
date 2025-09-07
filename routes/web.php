<?php
// ===================== AdminController 路由分發 =====================
// product      : 商品管理頁面（AdminController::showProductPage）
// order        : 訂單管理頁面（AdminController::showOrderPage）
// member       : 會員管理頁面（AdminController::showMemberPage）
// message      : 留言管理頁面（AdminController::showMessagePage）
// change       : 會員資料更改頁面（AdminController::showChangePage），需帶 account 參數
// adminUpdateMember : 處理更新會員資料請求（AdminController::updateMember），需帶 account 參數
// adminDeleteMember : 處理刪除會員請求（AdminController::delete
//adminDeleteOrder  : 處理刪除訂單請求（AdminController::deleteOrder），需帶 no 參數
// adminDeleteProduct : 處理刪除商品請求（AdminController::deleteProduct），需帶 id 參數
// adminDeleteMessage : 處理刪除留言請求（AdminController::deleteMessage），需帶 id 參數
// adminUpdateOrderShipping : 處理更新訂單運送請求（AdminController::updateOrderShipping），需帶 id 與 no 參數
// adminUpdateOrderPayment  : 處理更新訂單付款請求（AdminController::updateOrderPayment），需帶 payment 與 no 參數
// adminChangeProduct      : 處理商品更改請求（AdminController::update  ChangeProduct），需帶 id 參數
// ================================================================
require_once __DIR__ . '/../app/controllers/AdminController.php';
$admin = new AdminController();

// 商品管理頁面
if ($_GET['page'] === 'product') {
    // 載入商品管理頁面
    $admin->showProductPage();
    exit;
}
// 訂單管理頁面
if ($_GET['page'] === 'order') {
    // 載入訂單管理頁面
    $admin->showOrderPage();
    exit;
}
// 會員管理頁面
if ($_GET['page'] === 'member') {
    // 載入會員管理頁面
    $admin->showMemberPage();
    exit;
}
// 留言管理頁面
if ($_GET['page'] === 'message') {
    // 載入留言管理頁面
    $admin->showMessagePage();
    exit;
}
// 會員資料更改頁面
if ($_GET['page'] === 'adminChangeAccount' && isset($_GET['account'])) {
    // 載入會員資料更改頁面，需指定 account
    $admin->showChangePage();
    exit;
}
// 處理更新會員資料請求
if ($_GET['page'] === 'adminUpdateMember' && isset($_GET['account'])) {
    // 處理更新會員資料請求
    $admin->updateMember();
    exit;
}
// 處理刪除會員請求
if ($_GET['page'] === 'adminDeleteMember' && isset($_GET['account'])) {
    $admin->deleteMember();
    exit;
}
// 處理刪除訂單請求
if ($_GET['page'] === 'adminDeleteOrder' && isset($_GET['no'])) {
    $admin->deleteOrder();
    exit;
}
// 處理刪除商品請求
if ($_GET['page'] === 'adminDeleteProduct' && isset($_GET['id'])) {
    $admin->deleteProduct();
    exit;
}
// 處理刪除留言請求
if ($_GET['page'] === 'adminDeleteMessage' && isset($_GET['id'])) {
    $admin->deleteMessage();
    exit;
}
// 處理更新訂單運送請求
if ($_GET['page'] === 'adminUpdateOrderShipping' && isset($_GET['id']) && isset($_GET['no'])) {
    $admin->updateOrderShipping();
    exit;
}
// 處理更新訂單付款請求
if ($_GET['page'] === 'adminUpdateOrderPayment' && isset($_GET['payment']) &&  isset($_GET['no'])) {
    $admin->updateOrderPayment();
    exit;
}
// 處理商品更改請求
if ($_GET['page'] === 'adminChangeProduct' && isset($_GET['id'])) {
    $admin->updateChangeProduct();
    exit;
}

// ===================== AccountController 路由分發 =====================
// login               : 登入頁面（AccountController::showLoginPage）
// register            : 註冊頁面（AccountController::showRegisterPage）
// checkcode           : 驗證碼頁面（AccountController::showCheckcodePage）
// checkcode_database  : 驗證碼資料庫頁面（AccountController::showCheckcodeDatabasePage）
// login              : 處理登入請求（AccountController::login）
// register           : 處理註冊請求（AccountController::register）
// logout            : 處理登出請求（AccountController::logout）
// ================================================================
require_once __DIR__ . '/../app/controllers/AccountController.php';
$account = new AccountController();

// 登入頁面
if ($_GET['page'] === 'loginPage') {
    $account->showLoginPage();
    exit;
}
// 處理登入請求
if ($_GET['page'] === 'login') {
    $account->login();
    exit;
}
// 註冊頁面
if ($_GET['page'] === 'registerPage') {
    $account->showRegisterPage();
    exit;
}
// 處理註冊請求
if ($_GET['page'] === 'register') {
    $account->register();
    exit;
}
// 處理登出請求
if ($_GET['page'] === 'logout') {
    $account->logout();
    exit;
}
// 驗證碼頁面
if ($_GET['page'] === 'checkcode' && isset($_GET['account']) && isset($_GET['checkcode'])) {
    $account->showCheckcodePage();
    exit;
}
// 驗證碼資料庫頁面
if ($_GET['page'] === 'checkcodeDatabase') {
    $account->checkcodeDatabase();
    exit;
}

// ===================== OrdersController 路由分發 =====================
// orders       : 買家訂單頁面（OrdersController::showOrderPage）
// updatePayment : 處理訂單付款更新（OrdersController::updatePayment）
// ================================================================
require_once __DIR__ . '/../app/controllers/OrdersController.php';
$orders = new OrdersController();

// 買家訂單頁面
if ($_GET['page'] === 'orders') {
    $orders->showOrderPage();
    exit;
}
// 處理訂單付款更新
if ($_GET['page'] === 'updatePayment' && isset($_POST['payment']) && isset($_POST['no'])) {
    $orders->updatePayment();
    exit;
}

// ===================== SellerController 路由分發 =====================
// seller_product         : 商品管理頁面（SellerController::showManagementProductPage）
// seller_new_product     : 新增商品頁面（SellerController::showNewProductPage）
// seller_order           : 訂單管理頁面（SellerController::showManagementOrderPage）
// seller_change_product  : 商品更改頁面（SellerController::showChangeProductPage），需帶 id 參數
// seller_sales_results   : 銷售結果頁面（SellerController::showSalesResultsPage）
// sellerDeleteProduct   : 刪除商品（SellerController::deleteProduct），需帶 id 參數
// updateShipping        : 更新訂單運送狀態（SellerController::updateShipping）
// sellerUpdatePayment   : 更新訂單付款狀態（SellerController::updatePayment
// ================================================================
require_once __DIR__ . '/../app/controllers/SellerController.php';
$seller = new SellerController();

// 商品管理頁面
if ($_GET['page'] === 'management_product') {
    $seller->showManagementProductPage();
    exit;
}
// 新增商品頁面
if ($_GET['page'] === 'new_product') {
    $seller->showNewProductPage();
    exit;
}
// 處理新增商品請求
if ($_GET['page'] === 'saveNewProduct') {
    $seller->newProduct();
    exit;
}
// 訂單管理頁面
if ($_GET['page'] === 'management_order') {
    $seller->showManagementOrderPage();
    exit;
}
// 商品更改頁面
if ($_GET['page'] === 'change_product' && isset($_GET['id'])) {
    $seller->showChangeProductPage();
    exit;
}
// 商品更改儲存
if ($_GET['page'] === 'saveChangeProduct' && isset($_GET['id']) && isset($_GET['img'])) {
    $seller->saveChangeProduct();
    exit;
}
// 處理刪除商品請求
if ($_GET['page'] === 'sellerDeleteProduct' && isset($_GET['id'])) {
    $seller->deleteProduct();
    exit;
}
// 處理更新訂單運送狀態請求
if ($_GET['page'] === "updateShipping") {
    $seller->updateShipping();
    exit;
}
// 銷售結果頁面
if ($_GET['page'] === 'sales_results') {
    $seller->showSalesResultsPage();
    exit;
}
// 處理更新訂單付款請求
if ($_GET['page'] === 'sellerUpdatePayment' &&  isset($_GET['no'])) {
    $seller->updatePayment();
    exit;
}

// ===================== ShoppingController 路由分發 =====================
// shopping_cart   : 購物車頁面（ShoppingController::showCartPage）
// deleteAll       : 清空購物車（ShoppingController::deleteAll）
// changeProduct  : 修改購物車商品數量（ShoppingController::changeProduct）
// deleteProduct  : 刪除購物車商品（ShoppingController::deleteProduct）
// createOrder    : 建立訂單（ShoppingController::createOrder）
// ================================================================
require_once __DIR__ . '/../app/controllers/ShoppingController.php';
$shopping = new ShoppingController();

// 加入購物車
if ($_GET['page'] === 'addShoppingCart' && isset($_GET['id']) && isset($_GET['name']) && isset($_GET['price'])) {
    $shopping->addShoppingCart();
    exit;
}
// 購物車相關 AJAX 行為
if (isset($_GET['page']) && $_GET['page'] === 'shopping_cart') {
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'changeProduct':
                $shopping->changeProduct();
                exit;
            case 'deleteProduct':
                $shopping->deleteProduct();
                exit;
            case 'createOrder':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    if (isset($_POST['freight'])) {
                        $freight = $_POST['freight'];
                    } else {
                        $input = json_decode(file_get_contents('php://input'), true);
                        if (isset($input['freight'])) {
                            $_POST['freight'] = $input['freight'];
                        }
                    }
                }
                $shopping->createOrder();
                exit;
            case 'deleteAll':
                $shopping->deleteAll();
                exit;
        }
    }
    // 一般購物車頁面顯示
    $shopping->showCartPage();
    exit;
}

// ===================== indexController 路由分發 =====================
// index         : 首頁（indexController::showIndexPage）
// ================================================================
require_once __DIR__ . '/../app/controllers/indexController.php';
$index = new indexController();

// 首頁
if ($_GET['page'] === 'index' || !isset($_GET['page'])) {
    $index->showIndexPage();
    exit;
}
// 其他未定義頁面，導向首頁
$index->showIndexPage();
exit;
