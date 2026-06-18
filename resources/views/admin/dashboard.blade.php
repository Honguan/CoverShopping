@extends('layouts.app')

@section('content')
    <h1>管理員後台</h1>
    <section class="columns">
        <div class="panel">
            <h2>會員</h2>
            @foreach($users as $user)
                <form class="stack" action="{{ route('admin.users.status', $user) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <strong>{{ $user->account }}</strong>
                    <select name="status">
                        @foreach(['pending', 'active', 'suspended'] as $status)
                            <option value="{{ $status }}" @selected($user->status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <select name="role">
                        @foreach(['customer', 'seller', 'admin'] as $role)
                            <option value="{{ $role }}" @selected($user->role === $role)>{{ $role }}</option>
                        @endforeach
                    </select>
                    <button type="submit">更新</button>
                </form>
            @endforeach
        </div>
        <div class="panel">
            <h2>企業會員審核</h2>
            @foreach($businessProfiles as $profile)
                <form class="stack" action="{{ route('admin.business_profiles.status', $profile) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <strong>{{ $profile->company_name }} / {{ $profile->tax_id }}</strong>
                    <p>{{ $profile->contact_name }} {{ $profile->contact_phone }}</p>
                    <select name="status">
                        @foreach(['pending', 'approved', 'rejected'] as $status)
                            <option value="{{ $status }}" @selected($profile->status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <button type="submit">更新</button>
                </form>
            @endforeach
        </div>
        <div class="panel">
            <h2>商品審核</h2>
            @foreach($products as $product)
                <form class="stack" action="{{ route('admin.products.status', $product) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <strong>{{ $product->name }}</strong>
                    <select name="status">
                        @foreach(['draft', 'pending', 'active', 'archived'] as $status)
                            <option value="{{ $status }}" @selected($product->status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <button type="submit">更新</button>
                </form>
            @endforeach
        </div>
        <div class="panel">
            <h2>付款狀態</h2>
            @foreach($orders as $order)
                <form class="stack" action="{{ route('admin.orders.payment', $order) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <strong>{{ $order->number }}</strong>
                    <select name="payment_status">
                        @foreach(['unpaid', 'paid', 'failed', 'refunded'] as $status)
                            <option value="{{ $status }}" @selected($order->payment_status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <button type="submit">更新</button>
                </form>
            @endforeach
        </div>
        <div class="panel">
            <h2>優惠券</h2>
            <form class="stack" action="{{ route('admin.coupons.store') }}" method="post">
                @csrf
                <input name="code" placeholder="優惠碼" required>
                <input name="name" placeholder="名稱" required>
                <select name="type">
                    <option value="fixed">固定折扣</option>
                    <option value="percent">百分比</option>
                </select>
                <input name="value" type="number" min="1" placeholder="折扣值" required>
                <input name="minimum_subtotal" type="number" min="0" placeholder="最低訂單金額">
                <input name="usage_limit" type="number" min="1" placeholder="使用上限">
                <label class="inline"><input name="is_active" type="checkbox" value="1" checked> 啟用</label>
                <button type="submit">建立優惠券</button>
            </form>
            @foreach($coupons as $coupon)
                <p>{{ $coupon->code }}：{{ $coupon->name }}，已用 {{ $coupon->used_count }}</p>
            @endforeach
        </div>
        <div class="panel">
            <h2>退貨申請</h2>
            @foreach($returnRequests as $returnRequest)
                <form class="stack" action="{{ route('admin.returns.status', $returnRequest) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <strong>{{ $returnRequest->order->number }} / {{ $returnRequest->order->user->name }}</strong>
                    <p>{{ $returnRequest->reason }}</p>
                    <select name="status">
                        @foreach(['requested', 'approved', 'rejected', 'received', 'refunded'] as $status)
                            <option value="{{ $status }}" @selected($returnRequest->status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <button type="submit">更新</button>
                </form>
            @endforeach
        </div>
        <div class="panel">
            <h2>配送方式</h2>
            <form class="stack" action="{{ route('admin.shipping_methods.store') }}" method="post">
                @csrf
                <input name="name" placeholder="配送名稱" required>
                <input name="fee" type="number" min="0" placeholder="運費" required>
                <input name="sort_order" type="number" min="0" value="0">
                <label class="inline"><input name="is_active" type="checkbox" value="1" checked> 啟用</label>
                <button type="submit">建立配送方式</button>
            </form>
            @foreach($shippingMethods as $shippingMethod)
                <p>{{ $shippingMethod->name }}：${{ number_format($shippingMethod->fee) }}</p>
            @endforeach
        </div>
    </section>
@endsection
