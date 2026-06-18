<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', [
            'users' => User::latest()->limit(10)->get(),
            'products' => Product::with('seller')->latest()->limit(10)->get(),
            'orders' => Order::with('user')->latest()->limit(10)->get(),
        ]);
    }

    public function updateUserStatus(Request $request, User $user, AuditLogger $auditLogger)
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,active,suspended'],
            'role' => ['nullable', 'in:customer,seller,admin'],
        ]);

        $user->update(array_filter($data, fn ($value) => $value !== null));
        $auditLogger->log('admin.user.updated', $user, $data, $request);

        return redirect()->route('admin.dashboard')->with('status', '會員已更新');
    }

    public function updateProductStatus(Request $request, Product $product, AuditLogger $auditLogger)
    {
        $data = $request->validate([
            'status' => ['required', 'in:draft,pending,active,archived'],
        ]);

        $product->update($data);
        $auditLogger->log('admin.product.status_updated', $product, $data, $request);

        return redirect()->route('admin.dashboard')->with('status', '商品狀態已更新');
    }

    public function updatePaymentStatus(Request $request, Order $order, AuditLogger $auditLogger)
    {
        $data = $request->validate([
            'payment_status' => ['required', 'in:unpaid,paid,failed,refunded'],
        ]);

        $order->update($data);
        if ($data['payment_status'] === 'paid' && $order->fulfillment_status === 'pending') {
            $order->update(['fulfillment_status' => 'processing']);
        }
        $auditLogger->log('admin.order.payment_updated', $order, $data, $request);

        return redirect()->route('admin.dashboard')->with('status', '付款狀態已更新');
    }
}
