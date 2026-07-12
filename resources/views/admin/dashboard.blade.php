@extends('layouts.app')

@section('content')
    <h1>{{ __('ui.admin_dashboard') }}</h1>
    <section class="columns">
        <div class="panel">
            <h2>{{ __('ui.members') }}</h2>
            @foreach($users as $user)
                <form class="stack" action="{{ route('admin.users.status', $user) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <strong>{{ $user->account }}</strong>
                    <select name="status">
                        @foreach(['pending', 'active', 'suspended'] as $status)
                            <option value="{{ $status }}" @selected($user->status === $status)>{{ __('ui.status_'.$status) }}</option>
                        @endforeach
                    </select>
                    <select name="role">
                        @foreach(['customer', 'seller', 'admin'] as $role)
                            <option value="{{ $role }}" @selected($user->role === $role)>{{ __('ui.role_'.$role) }}</option>
                        @endforeach
                    </select>
                    <button type="submit">{{ __('ui.update') }}</button>
                </form>
            @endforeach
        </div>
        <div class="panel">
            <h2>{{ __('ui.business_profile_review') }}</h2>
            @foreach($businessProfiles as $profile)
                <form class="stack" action="{{ route('admin.business_profiles.status', $profile) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <strong>{{ $profile->company_name }} / {{ $profile->tax_id }}</strong>
                    <p>{{ $profile->contact_name }} {{ $profile->contact_phone }}</p>
                    <select name="status">
                        @foreach(['pending', 'approved', 'rejected'] as $status)
                            <option value="{{ $status }}" @selected($profile->status === $status)>{{ __('ui.status_'.$status) }}</option>
                        @endforeach
                    </select>
                    <button type="submit">{{ __('ui.update') }}</button>
                </form>
            @endforeach
        </div>
        <div class="panel">
            <h2>{{ __('ui.product_approval') }}</h2>
            @foreach($products as $product)
                <form class="stack" action="{{ route('admin.products.status', $product) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <strong>{{ $product->name }}</strong>
                    <select name="status">
                        @foreach(['draft', 'pending', 'active', 'archived'] as $status)
                            <option value="{{ $status }}" @selected($product->status === $status)>{{ __('ui.status_'.$status) }}</option>
                        @endforeach
                    </select>
                    <button type="submit">{{ __('ui.update') }}</button>
                </form>
            @endforeach
        </div>
        <div class="panel">
            <h2>{{ __('ui.payment_status') }}</h2>
            @foreach($orders as $order)
                <form class="stack" action="{{ route('admin.orders.payment', $order) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <strong>{{ $order->number }}</strong>
                    <select name="payment_status">
                        @foreach(['unpaid', 'paid', 'failed', 'refunded'] as $status)
                            <option value="{{ $status }}" @selected($order->payment_status === $status)>{{ __('ui.payment_'.$status) }}</option>
                        @endforeach
                    </select>
                    <button type="submit">{{ __('ui.update') }}</button>
                </form>
            @endforeach
        </div>
        <div class="panel">
            <h2>{{ __('ui.coupon') }}</h2>
            <form class="stack" action="{{ route('admin.coupons.store') }}" method="post">
                @csrf
                <input name="code" placeholder="{{ __('ui.coupon_code') }}" required>
                <input name="name" placeholder="{{ __('ui.name') }}" required>
                <select name="type">
                    <option value="fixed">{{ __('ui.discount_fixed') }}</option>
                    <option value="percent">{{ __('ui.discount_percent') }}</option>
                </select>
                <input name="value" type="number" min="1" placeholder="{{ __('ui.discount_value') }}" required>
                <input name="minimum_subtotal" type="number" min="0" placeholder="{{ __('ui.minimum_order_amount') }}">
                <input name="usage_limit" type="number" min="1" placeholder="{{ __('ui.usage_limit') }}">
                <label class="inline"><input name="is_active" type="checkbox" value="1" checked> {{ __('ui.status_active') }}</label>
                <button type="submit">{{ __('ui.create_coupon') }}</button>
            </form>
            @foreach($coupons as $coupon)
                <p>{{ $coupon->code }}：{{ $coupon->name }}，{{ __('ui.used_count', ['count' => $coupon->used_count]) }}</p>
            @endforeach
        </div>
        <div class="panel">
            <h2>{{ __('ui.return_requests') }}</h2>
            @foreach($returnRequests as $returnRequest)
                <form class="stack" action="{{ route('admin.returns.status', $returnRequest) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <strong>{{ $returnRequest->order->number }} / {{ $returnRequest->order->user->name }}</strong>
                    <p>{{ $returnRequest->reason }}</p>
                    <select name="status">
                        @foreach(['requested', 'approved', 'rejected', 'received', 'refunded'] as $status)
                            <option value="{{ $status }}" @selected($returnRequest->status === $status)>{{ __('ui.return_'.$status) }}</option>
                        @endforeach
                    </select>
                    <button type="submit">{{ __('ui.update') }}</button>
                </form>
            @endforeach
        </div>
        <div class="panel">
            <h2>{{ __('ui.shipping_method') }}</h2>
            <form class="stack" action="{{ route('admin.shipping_methods.store') }}" method="post">
                @csrf
                <input name="name" placeholder="{{ __('ui.shipping_name') }}" required>
                <input name="fee" type="number" min="0" placeholder="{{ __('ui.shipping_fee') }}" required>
                <input name="sort_order" type="number" min="0" value="0">
                <label class="inline"><input name="is_active" type="checkbox" value="1" checked> {{ __('ui.status_active') }}</label>
                <button type="submit">{{ __('ui.create_shipping_method') }}</button>
            </form>
            @foreach($shippingMethods as $shippingMethod)
                <p>{{ $shippingMethod->name }}：${{ number_format($shippingMethod->fee) }}</p>
            @endforeach
        </div>
    </section>
@endsection
