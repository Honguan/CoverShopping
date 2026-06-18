@extends('layouts.app')

@section('content')
    <h1>我的訂單</h1>
    <section class="list">
        @forelse($orders as $order)
            <article class="panel">
                <div class="row">
                    <div>
                        <h2>{{ $order->number }}</h2>
                        <p>{{ strtoupper($order->sales_channel) }} / 總金額 ${{ number_format($order->total) }} / 付款 {{ $order->payment_status }} / 出貨 {{ $order->fulfillment_status }}</p>
                    </div>
                    <form action="{{ route('orders.reorder', $order) }}" method="post">
                        @csrf
                        <button type="submit">再次購買</button>
                    </form>
                </div>
                <ul>
                    @foreach($order->items as $item)
                        <li>
                            {{ $item->product_name }}
                            @if($item->variant_name)
                                / {{ $item->variant_name }}
                            @endif
                            x {{ $item->quantity }} = ${{ number_format($item->subtotal) }}
                            @if($item->product_id && in_array($order->fulfillment_status, ['shipped', 'completed'], true))
                                <form class="stack" action="{{ route('reviews.store', $item->product_id) }}" method="post">
                                    @csrf
                                    <input type="hidden" name="order_item_id" value="{{ $item->id }}">
                                    <label>評分
                                        <select name="rating">
                                            @foreach([5, 4, 3, 2, 1] as $rating)
                                                <option value="{{ $rating }}">{{ $rating }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>評價<textarea name="content"></textarea></label>
                                    <button type="submit">送出評價</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if($order->return_status === 'none' && in_array($order->fulfillment_status, ['shipped', 'completed'], true))
                    <form class="stack" action="{{ route('returns.store', $order) }}" method="post">
                        @csrf
                        <label>退貨原因<textarea name="reason" required></textarea></label>
                        <button type="submit">申請退貨</button>
                    </form>
                @elseif($order->return_status !== 'none')
                    <p>退貨狀態：{{ $order->return_status }}</p>
                @endif
            </article>
        @empty
            <p>目前沒有訂單。</p>
        @endforelse
    </section>
    {{ $orders->links() }}
@endsection
