<?php
class indexController
{
    /**
     * 顯示首頁，取得所有商品資料
     */
    public function showIndexPage()
    {
        include __DIR__ . '/../views/index.php';
    }

    public function products()
    {
        require_once(dirname(__DIR__) . '/models/indexModel.php');
        $products = IndexModel::getAllProducts();
        return $products;
    }
}
