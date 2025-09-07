<?php
require_once __DIR__ . '/../models/OrdersModel.php';

class OrdersController
{
    public function updatePayment()
    {
        header("Content-type: text/html; charset=utf-8");
        $payment = htmlspecialchars(trim($_POST["payment"] ?? ''));
        $no = intval($_POST["no"] ?? 0);
        OrdersModel::updatePayment($no, $payment);
        header("Location: /CoverShopping/routes/web.php?page=orders");
    }

    public function showOrderPage()
    {
        session_start();
        $account = $_SESSION['account'];
        $orders_raw = OrdersModel::getOrdersByAccount($account);
        // 資料分組與處理
        $orders_grouped = [];
        foreach ($orders_raw as $row) {
            $orders_grouped[$row->no]['buyer'] = $row->name;
            $orders_grouped[$row->no]['time'] = $row->time;
            $orders_grouped[$row->no]['freight'] = $row->freight;
            $orders_grouped[$row->no]['payment'] = $row->payment;
            $orders_grouped[$row->no]['shipping'] = $row->Shipping;
            $commodity = OrdersModel::getCommodityById($row->id);
            $orders_grouped[$row->no]['items'][] = [
                'name' => $commodity->name,
                'price' => $commodity->price,
                'quantity' => $row->quantity,
                'subtotal' => $commodity->price * $row->quantity
            ];
        }
        include $_SERVER['DOCUMENT_ROOT'] . '/CoverShopping/app/views/Orders/order.php';
    }
}
