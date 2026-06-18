<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCouponRequest;
use App\Http\Requests\CreateShippingMethodRequest;
use App\Http\Requests\ReviewBusinessProfileRequest;
use App\Http\Requests\UpdateOrderPaymentRequest;
use App\Http\Requests\UpdateProductStatusRequest;
use App\Http\Requests\UpdateReturnStatusRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Models\BusinessProfile;
use App\Models\Coupon;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\AuditLogService;

class AdminDashboardController extends Controller
{
    public function showDashboard()
    {
        return view('admin.dashboard', [
            'users' => User::latest()->limit(10)->get(),
            'products' => Product::with('seller')->latest()->limit(10)->get(),
            'orders' => Order::with('user')->latest()->limit(10)->get(),
            'coupons' => Coupon::latest()->limit(10)->get(),
            'shippingMethods' => ShippingMethod::orderBy('sort_order')->latest()->limit(10)->get(),
            'returnRequests' => ReturnRequest::with(['order.user'])->latest()->limit(10)->get(),
            'businessProfiles' => BusinessProfile::with('user')->latest()->limit(10)->get(),
        ]);
    }

    public function changeUserStatus(UpdateUserStatusRequest $request, User $user, AuditLogService $auditLogService)
    {
        $data = $request->validated();

        $user->update(array_filter($data, fn ($value) => $value !== null));
        $auditLogService->writeLog('admin.user.updated', $user, $data, $request);

        return redirect()->route('admin.dashboard')->with('status', 'User updated.');
    }

    public function reviewBusinessProfile(ReviewBusinessProfileRequest $request, BusinessProfile $businessProfile, AuditLogService $auditLogService)
    {
        $data = $request->validated();

        $businessProfile->update($data);
        $businessProfile->user->update([
            'account_type' => $data['status'] === 'approved' ? 'b2b' : 'b2c',
        ]);

        Notification::create([
            'user_id' => $businessProfile->user_id,
            'type' => 'business_profile_reviewed',
            'title' => 'Business profile reviewed.',
            'body' => $data['status'],
            'url' => route('business_profile.edit'),
        ]);

        $auditLogService->writeLog('admin.business_profile.updated', $businessProfile, $data, $request);

        return redirect()->route('admin.dashboard')->with('status', 'Business profile reviewed.');
    }

    public function changeProductStatus(UpdateProductStatusRequest $request, Product $product, AuditLogService $auditLogService)
    {
        $data = $request->validated();

        $product->update($data);
        $auditLogService->writeLog('admin.product.status_updated', $product, $data, $request);

        return redirect()->route('admin.dashboard')->with('status', 'Product status updated.');
    }

    public function changeOrderPaymentStatus(UpdateOrderPaymentRequest $request, Order $order, AuditLogService $auditLogService)
    {
        $data = $request->validated();

        $order->update($data);
        if ($data['payment_status'] === 'paid' && $order->fulfillment_status === 'pending') {
            $order->update(['fulfillment_status' => 'processing']);
        }
        $auditLogService->writeLog('admin.order.payment_updated', $order, $data, $request);

        return redirect()->route('admin.dashboard')->with('status', 'Payment status updated.');
    }

    public function createCoupon(CreateCouponRequest $request, AuditLogService $auditLogService)
    {
        $data = $request->validated();
        $data['code'] = strtoupper($data['code']);
        $data['minimum_subtotal'] = $data['minimum_subtotal'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);

        $coupon = Coupon::create($data);
        $auditLogService->writeLog('admin.coupon.created', $coupon, $data, $request);

        return redirect()->route('admin.dashboard')->with('status', 'Coupon created.');
    }

    public function createShippingMethod(CreateShippingMethodRequest $request, AuditLogService $auditLogService)
    {
        $data = $request->validated();
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);

        $shippingMethod = ShippingMethod::create($data);
        $auditLogService->writeLog('admin.shipping_method.created', $shippingMethod, $data, $request);

        return redirect()->route('admin.dashboard')->with('status', 'Shipping method created.');
    }

    public function changeReturnStatus(UpdateReturnStatusRequest $request, ReturnRequest $returnRequest, AuditLogService $auditLogService)
    {
        $data = $request->validated();

        $returnRequest->update($data);
        $returnRequest->order->update(['return_status' => $data['status']]);
        $auditLogService->writeLog('admin.return.updated', $returnRequest, $data, $request);

        return redirect()->route('admin.dashboard')->with('status', 'Return status updated.');
    }
}
