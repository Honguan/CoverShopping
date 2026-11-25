@extends('layouts.app')

@section('title', __('ui.payment_redirecting'))

@section('content')
    <form class="panel stack" action="{{ $action }}" method="post" data-auto-submit>
        <p>{{ __('ui.payment_redirecting') }}</p>
        @foreach($fields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <button type="submit">{{ __('ui.continue_to_payment') }}</button>
    </form>
@endsection
