@extends('layouts.app')

@section('content')
    <h1>我的訂單</h1>
    <section class="list">
        @forelse($orders as $order)
            <article class="panel">
                <h2>{{ $order->number }}</h2>
                <p>總額 ${{ number_format($order->total) }}，付款 {{ $order->payment_status }}，物流 {{ $order->fulfillment_status }}</p>
                <ul>
                    @foreach($order->items as $item)
                        <li>{{ $item->product_name }} x {{ $item->quantity }} = ${{ number_format($item->subtotal) }}</li>
                    @endforeach
                </ul>
                @if($order->return_status === 'none' && in_array($order->fulfillment_status, ['shipped', 'completed'], true))
                    <form action="{{ route('returns.store', $order) }}" method="post">
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
