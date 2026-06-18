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
                        <p>企業最低採購 {{ $item->product->business_min_quantity }}</p>
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
                    <button type="submit">移除</button>
                </form>
            </article>
        @empty
            <p>購物車目前沒有商品。</p>
        @endforelse
    </section>

    @if($items->isNotEmpty())
        <section class="summary">
            <p>商品小計：${{ number_format($subtotal) }}</p>
            @auth
                <form action="{{ route('checkout.store') }}" method="post">
                    @csrf
                    <label>配送方式
                        <select name="shipping_method_id">
                            <option value="">免運/未指定</option>
                            @foreach($shippingMethods as $shippingMethod)
                                <option value="{{ $shippingMethod->id }}">{{ $shippingMethod->name }} ${{ number_format($shippingMethod->fee) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>優惠券<input name="coupon_code" placeholder="輸入優惠碼"></label>
                    <button type="submit">建立訂單</button>
                </form>
            @else
                <a class="button" href="{{ route('login') }}">登入後結帳</a>
            @endauth
        </section>
    @endif
@endsection
