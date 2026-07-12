@extends('layouts.app')

@section('content')
    <h1>{{ __('ui.shopping_cart') }}</h1>

    @if(session('status'))
        <p class="notice">{{ session('status') }}</p>
    @endif

    @error('checkout')
        <p class="error">{{ $message }}</p>
    @enderror

    <section class="list">
        @forelse($items as $item)
            @php
                $messages = $itemStatusMessages[$item->id] ?? [];
                $maxQuantity = $item->variant ? $item->variant->inventory : $item->product->inventory;
            @endphp
            <article class="row">
                <div>
                    <strong>{{ $item->product->name }}</strong>
                    @if($item->variant)
                        <p>{{ $item->variant->displayName() }}</p>
                    @endif
                    <p>${{ number_format($pricingService->calculateUnitPrice($item->product, $item->variant, auth()->user(), $item->quantity)) }}</p>
                    @if($messages)
                        <ul>
                            @foreach($messages as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <form action="{{ route('cart.items.update', $item) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="{{ max(1, $maxQuantity) }}">
                    <button type="submit">{{ __('ui.update') }}</button>
                </form>
                <form action="{{ route('cart.items.destroy', $item) }}" method="post">
                    @csrf
                    @method('DELETE')
                    <button type="submit">{{ __('ui.remove') }}</button>
                </form>
            </article>
        @empty
            <p>{{ __('ui.empty_cart') }}</p>
        @endforelse
    </section>

    @if($items->isNotEmpty())
        <form action="{{ route('cart.items.clear') }}" method="post">
            @csrf
            @method('DELETE')
            <button type="submit">{{ __('ui.clear_cart') }}</button>
        </form>

        <section class="summary">
            <p>{{ __('ui.subtotal') }}: ${{ number_format($subtotal) }}</p>
            @php
                $promotionDiscount = $promotionService->calculateOrderDiscount($subtotal);
                $freeShippingRemaining = $promotionService->freeShippingRemaining($subtotal);
            @endphp
            @if($promotionDiscount > 0)
                <p>{{ __('ui.promotion_discount') }}: ${{ number_format($promotionDiscount) }}</p>
            @endif
            @if($freeShippingRemaining > 0)
                <p>{{ __('ui.add_for_free_shipping', ['amount' => number_format($freeShippingRemaining)]) }}</p>
            @else
                <p>{{ __('ui.free_shipping_unlocked') }}</p>
            @endif
            @auth
                <form class="stack" action="{{ route('checkout.store') }}" method="post">
                    @csrf
                    <label>{{ __('ui.shipping_address') }}
                        <select name="address_id">
                            <option value="">{{ __('ui.no_address_selected') }}</option>
                            @foreach($addresses as $address)
                                <option value="{{ $address->id }}" @selected($defaultAddress?->id === $address->id)>
                                    {{ $address->recipient_name }} / {{ $address->city }}{{ $address->district }}{{ $address->address_line }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <a href="{{ route('addresses.index') }}">{{ __('ui.manage_addresses') }}</a>
                    <label>{{ __('ui.shipping_method') }}
                        <select name="shipping_method_id">
                            <option value="">{{ __('ui.no_shipping_method_selected') }}</option>
                            @foreach($shippingMethods as $shippingMethod)
                                <option value="{{ $shippingMethod->id }}" @selected($defaultShippingMethod?->id === $shippingMethod->id)>
                                    {{ $shippingMethod->name }} ${{ number_format($shippingMethod->fee) }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>{{ __('ui.coupon') }}
                        <input name="coupon_code" placeholder="{{ __('ui.enter_coupon_code') }}">
                    </label>
                    @if(auth()->user()->canUseBusinessPricing())
                        <label>{{ __('ui.purchase_order_number') }}
                            <input name="purchase_order_number" maxlength="64">
                        </label>
                    @endif
                    <button type="submit">{{ __('ui.checkout') }}</button>
                </form>
            @else
                <a class="button" href="{{ route('login') }}">{{ __('ui.login_to_checkout') }}</a>
            @endauth
        </section>
    @endif
@endsection
