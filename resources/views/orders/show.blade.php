@extends('layouts.app')

@section('title', __('ui.order_details'))

@section('content')
    <a href="{{ route('orders.index') }}">{{ __('ui.back_to_orders') }}</a>
    <h1>{{ __('ui.order_details') }}：{{ $order->number }}</h1>

    <section class="panel stack">
        <p>{{ __('ui.ordered_at') }}：{{ $order->created_at->format('Y-m-d H:i') }}</p>
        <p>{{ __('ui.payment') }}：{{ __('ui.payment_'.$order->payment_status) }} / {{ __('ui.fulfillment') }}：{{ __('ui.fulfillment_'.$order->fulfillment_status) }}</p>
        @if($order->purchase_order_number)
            <p>{{ __('ui.purchase_order_number') }}：{{ $order->purchase_order_number }}</p>
        @endif
        @if($address = $order->shipping_address_snapshot)
            <p>{{ __('ui.shipping_address') }}：{{ $address['recipient_name'] }} / {{ $address['phone'] }} / {{ $address['postal_code'] }} {{ $address['city'] }}{{ $address['district'] }}{{ $address['address_line'] }}</p>
        @endif
        @if($order->shipping_method_name)
            <p>{{ __('ui.shipping_method') }}：{{ $order->shipping_method_name }}</p>
        @endif
        @if($companyName = ($order->business_profile_snapshot['company_name'] ?? null))
            <p>{{ __('ui.billing_company') }}：{{ $companyName }}</p>
        @endif
        @if($taxId = ($order->business_profile_snapshot['tax_id'] ?? null))
            <p>{{ __('ui.tax_id') }}：{{ $taxId }}</p>
        @endif
        <div class="inline">
            <form action="{{ route('orders.reorder', $order) }}" method="post">
                @csrf
                <button type="submit">{{ __('ui.reorder') }}</button>
            </form>
            @can('cancel', $order)
                <form action="{{ route('orders.cancel', $order) }}" method="post">
                    @csrf
                    <button type="submit">{{ __('ui.cancel_order') }}</button>
                </form>
            @endcan
        </div>
    </section>

    <section class="panel stack">
        <ul>
            @foreach($order->items as $item)
                <li>
                    {{ $item->product_name }}
                    @if($item->variant_name)
                        / {{ $item->variant_name }}
                    @endif
                    / {{ __('ui.unit_price') }} ${{ number_format($item->unit_price) }} x {{ $item->quantity }} = ${{ number_format($item->subtotal) }}
                    / {{ __('ui.fulfillment_'.$item->shipping_status) }}
                    @if($item->shipped_at)
                        （{{ __('ui.shipped_at') }} {{ $item->shipped_at->format('Y-m-d H:i') }}）
                    @endif
                </li>
            @endforeach
        </ul>
        <p>{{ __('ui.subtotal') }}：${{ number_format($order->subtotal) }}</p>
        @if($order->discount_total > 0)
            <p>{{ __('ui.discount') }}@if($order->coupon_code)（{{ $order->coupon_code }}）@endif：-${{ number_format($order->discount_total) }}</p>
        @endif
        <p>{{ __('ui.shipping_fee') }}：${{ number_format($order->shipping_fee) }}</p>
        <p><strong>{{ __('ui.order_total') }}：${{ number_format($order->total) }}</strong></p>
    </section>

    @if($order->returnRequests->isNotEmpty())
        <section class="panel stack">
            <h2>{{ __('ui.return_requests') }}</h2>
            @foreach($order->returnRequests as $returnRequest)
                <p>{{ $returnRequest->created_at->format('Y-m-d H:i') }} / {{ __('ui.return_'.$returnRequest->status) }}：{{ $returnRequest->reason }}</p>
            @endforeach
        </section>
    @endif
@endsection
