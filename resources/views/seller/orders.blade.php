@extends('layouts.app')

@section('content')
    <h1>{{ __('ui.seller_orders') }}</h1>
    <a class="button" href="{{ route('seller.orders.export') }}">{{ __('ui.export_csv') }}</a>
    <section class="list">
        @foreach($items as $item)
            <article class="row">
                <div>
                    <strong>{{ $item->order->number }}</strong>
                    <p>{{ $item->product_name }} x {{ $item->quantity }}，{{ __('ui.buyer') }} {{ $item->order->user->name }}</p>
                    @if($address = $item->order->shipping_address_snapshot)
                        <p>{{ __('ui.shipping_address') }}: {{ $address['recipient_name'] }} / {{ $address['phone'] }} / {{ $address['postal_code'] }} {{ $address['city'] }}{{ $address['district'] }}{{ $address['address_line'] }}</p>
                    @endif
                </div>
                <span>{{ __('ui.fulfillment_'.$item->shipping_status) }}</span>
                @if($item->shipping_status !== 'shipped')
                    <form action="{{ route('seller.orders.items.ship', [$item->order, $item]) }}" method="post">
                        @csrf
                        @method('PATCH')
                        <button type="submit">{{ __('ui.mark_shipped') }}</button>
                    </form>
                @endif
            </article>
        @endforeach
    </section>
    {{ $items->links() }}
@endsection
