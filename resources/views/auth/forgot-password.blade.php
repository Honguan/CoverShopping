@extends('layouts.app')

@section('title', __('passwords.forgot_title'))

@section('content')
    <form class="panel" action="{{ route('password.email') }}" method="post">
        @csrf
        <h1>{{ __('passwords.forgot_title') }}</h1>
        <p>{{ __('passwords.instructions') }}</p>
        @php($email = old('email', ''))
        <label>{{ __('ui.email') }}<input name="email" type="email" autocomplete="email" maxlength="160" value="{{ is_string($email) ? $email : '' }}" required></label>
        <button type="submit">{{ __('passwords.send_link') }}</button>
        <a href="{{ route('login') }}">{{ __('ui.login') }}</a>
    </form>
@endsection
