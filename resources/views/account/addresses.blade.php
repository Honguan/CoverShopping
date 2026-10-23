@extends('layouts.app')

@section('title', __('ui.addresses'))

@section('content')
    <h1>{{ __('ui.addresses') }}</h1>

    <form class="panel stack" action="{{ route('addresses.store') }}" method="post">
        @csrf
        <h2>{{ __('ui.add_shipping_address') }}</h2>
        <label>{{ __('ui.recipient_name') }}<input name="recipient_name" required></label>
        <label>{{ __('ui.phone') }}<input name="phone" required></label>
        <label>{{ __('ui.postal_code') }}<input name="postal_code"></label>
        <label>{{ __('ui.city') }}<input name="city" required></label>
        <label>{{ __('ui.district') }}<input name="district"></label>
        <label>{{ __('ui.address_line') }}<input name="address_line" required></label>
        <label class="inline"><input name="is_default" type="checkbox" value="1"> {{ __('ui.set_default_address') }}</label>
        <button type="submit">{{ __('ui.add_address') }}</button>
    </form>

    <section class="list">
        @forelse($addresses as $address)
            <article class="panel row">
                <div>
                    <strong>{{ $address->recipient_name }}</strong>
                    <p>{{ $address->phone }}</p>
                    <p>{{ $address->postal_code }} {{ $address->city }}{{ $address->district }}{{ $address->address_line }}</p>
                    @if($address->is_default)
                        <span class="badge">{{ __('ui.default_address') }}</span>
                    @endif
                </div>
                <details>
                    <summary>{{ __('ui.edit_address') }}</summary>
                    <form class="stack" action="{{ route('addresses.update', $address) }}" method="post">
                        @csrf
                        @method('PATCH')
                        <label>{{ __('ui.recipient_name') }}<input name="recipient_name" value="{{ $address->recipient_name }}" maxlength="80" required></label>
                        <label>{{ __('ui.phone') }}<input name="phone" value="{{ $address->phone }}" maxlength="40" required></label>
                        <label>{{ __('ui.postal_code') }}<input name="postal_code" value="{{ $address->postal_code }}" maxlength="20"></label>
                        <label>{{ __('ui.city') }}<input name="city" value="{{ $address->city }}" maxlength="80" required></label>
                        <label>{{ __('ui.district') }}<input name="district" value="{{ $address->district }}" maxlength="80"></label>
                        <label>{{ __('ui.address_line') }}<input name="address_line" value="{{ $address->address_line }}" maxlength="255" required></label>
                        <button type="submit">{{ __('ui.update') }}</button>
                    </form>
                </details>
                <div class="inline">
                    @unless($address->is_default)
                        <form action="{{ route('addresses.default', $address) }}" method="post">
                            @csrf
                            @method('PATCH')
                            <button type="submit">{{ __('ui.set_default_address') }}</button>
                        </form>
                    @endunless
                    <form action="{{ route('addresses.destroy', $address) }}" method="post">
                        @csrf
                        @method('DELETE')
                        <button type="submit">{{ __('ui.remove') }}</button>
                    </form>
                </div>
            </article>
        @empty
            <p>{{ __('ui.no_shipping_addresses') }}</p>
        @endforelse
    </section>
@endsection
