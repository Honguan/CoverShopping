@extends('layouts.app')

@section('content')
    <h1>商家訂單管理</h1>
    <a class="button" href="{{ route('seller.orders.export') }}">匯出 CSV</a>
    <section class="list">
        @foreach($items as $item)
            <article class="row">
                <div>
                    <strong>{{ $item->order->number }}</strong>
                    <p>{{ $item->product_name }} x {{ $item->quantity }}，買家 {{ $item->order->user->name }}</p>
                </div>
                <span>{{ $item->shipping_status }}</span>
                @if($item->shipping_status !== 'shipped')
                    <form action="{{ route('seller.orders.items.ship', [$item->order, $item]) }}" method="post">
                        @csrf
                        @method('PATCH')
                        <button type="submit">標記出貨</button>
                    </form>
                @endif
            </article>
        @endforeach
    </section>
    {{ $items->links() }}
@endsection
