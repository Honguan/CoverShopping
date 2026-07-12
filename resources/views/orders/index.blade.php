@extends('layouts.app')

@section('content')
    <h1>{{ __('ui.orders') }}</h1>
    <section class="list">
        @forelse($orders as $order)
            <article class="panel">
                <div class="row">
                    <div>
                        <h2>{{ $order->number }}</h2>
                        <p>{{ strtoupper($order->sales_channel) }} / {{ __('ui.order_total') }} ${{ number_format($order->total) }} / {{ __('ui.payment') }} {{ __('ui.payment_' . $order->payment_status) }} / {{ __('ui.fulfillment') }} {{ __('ui.fulfillment_' . $order->fulfillment_status) }}</p>
                        @if($order->purchase_order_number)
                            <p>{{ __('ui.purchase_order_number') }}: {{ $order->purchase_order_number }}</p>
                        @endif
                        @if($order->business_profile_snapshot)
                            @if($companyName = ($order->business_profile_snapshot['company_name'] ?? null))
                                <p>{{ __('ui.billing_company') }}: {{ $companyName }}</p>
                            @endif
                            @if($taxId = ($order->business_profile_snapshot['tax_id'] ?? null))
                                <p>{{ __('ui.tax_id') }}: {{ $taxId }}</p>
                            @endif
                        @endif
                    </div>
                    <form action="{{ route('orders.reorder', $order) }}" method="post">
                        @csrf
                        <button type="submit">{{ __('ui.reorder') }}</button>
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
                                    <label>{{ __('ui.rating') }}
                                        <select name="rating">
                                            @foreach([5, 4, 3, 2, 1] as $rating)
                                                <option value="{{ $rating }}">{{ $rating }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>{{ __('ui.review') }}<textarea name="content"></textarea></label>
                                    <button type="submit">{{ __('ui.submit_review') }}</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if($order->return_status === 'none' && in_array($order->fulfillment_status, ['shipped', 'completed'], true))
                    <form class="stack" action="{{ route('returns.store', $order) }}" method="post">
                        @csrf
                        <label>{{ __('ui.return_reason') }}<textarea name="reason" required></textarea></label>
                        <button type="submit">{{ __('ui.request_return') }}</button>
                    </form>
                @elseif($order->return_status !== 'none')
                    <p>{{ __('ui.return_status') }}：{{ __('ui.return_' . $order->return_status) }}</p>
                @endif
            </article>
        @empty
            <p>{{ __('ui.no_orders') }}</p>
        @endforelse
    </section>
    {{ $orders->links() }}
@endsection
