@extends('layouts.app')

@section('content')
    <h1>購物車</h1>
    <section class="list">
        @forelse($items as $item)
            <article class="row">
                <div>
                    <strong>{{ $item->product->name }}</strong>
                    @if($item->variant)
                        <p>{{ $item->variant->displayName() }}</p>
                    @endif
                    <p>${{ number_format($pricingService->calculateUnitPrice($item->product, $item->variant, auth()->user(), $item->quantity)) }}</p>
                    @if(auth()->user()?->canUseBusinessPricing() && $item->product->business_price !== null)
                        <p>企業價最低採購量 {{ $item->product->business_min_quantity }}</p>
                    @endif
                </div>
                <form action="{{ route('cart.items.update', $item) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="{{ max(1, $item->product->inventory) }}">
                    <button type="submit">更新</button>
                </form>
                <form action="{{ route('cart.items.destroy', $item) }}" method="post">
                    @csrf
                    @method('DELETE')
                    <button type="submit">刪除</button>
                </form>
            </article>
        @empty
            <p>購物車目前沒有商品。</p>
        @endforelse
    </section>

    @if($items->isNotEmpty())
        <section class="summary">
            <p>商品小計：${{ number_format($subtotal) }}</p>
            @php
                $promotionDiscount = $promotionService->calculateOrderDiscount($subtotal);
                $freeShippingRemaining = $promotionService->freeShippingRemaining($subtotal);
            @endphp
            @if($promotionDiscount > 0)
                <p>滿額折扣：-${{ number_format($promotionDiscount) }}</p>
            @endif
            @if($freeShippingRemaining > 0)
                <p>再買 ${{ number_format($freeShippingRemaining) }} 即可免運。</p>
            @else
                <p>本訂單已達免運門檻。</p>
            @endif
            @auth
                <form class="stack" action="{{ route('checkout.store') }}" method="post">
                    @csrf
                    <label>收件地址
                        <select name="address_id">
                            <option value="">不指定地址</option>
                            @foreach($addresses as $address)
                                <option value="{{ $address->id }}" @selected($address->is_default)>
                                    {{ $address->recipient_name }} / {{ $address->city }}{{ $address->district }}{{ $address->address_line }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <a href="{{ route('addresses.index') }}">管理地址簿</a>
                    <label>配送方式
                        <select name="shipping_method_id">
                            <option value="">自取或免運</option>
                            @foreach($shippingMethods as $shippingMethod)
                                <option value="{{ $shippingMethod->id }}">{{ $shippingMethod->name }} ${{ number_format($shippingMethod->fee) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>優惠券<input name="coupon_code" placeholder="輸入優惠券代碼"></label>
                    <button type="submit">建立訂單</button>
                </form>
            @else
                <a class="button" href="{{ route('login') }}">登入後結帳</a>
            @endauth
        </section>
    @endif
@endsection
