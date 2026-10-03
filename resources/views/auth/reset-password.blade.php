@extends('layouts.app')

@section('content')
    <form class="panel" action="{{ route('password.update') }}" method="post">
        @csrf
        <h1>{{ __('passwords.reset_title') }}</h1>
        <input name="token" type="hidden" value="{{ $token }}">
        @php($email = old('email', $email))
        <label>{{ __('ui.email') }}<input name="email" type="email" autocomplete="email" maxlength="160" value="{{ is_string($email) ? $email : '' }}" required></label>
        <p>{{ __('passwords.password_rules') }}</p>
        <label>{{ __('ui.password') }}<input name="password" type="password" autocomplete="new-password" minlength="10" required></label>
        <label>{{ __('ui.confirm_password') }}<input name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required></label>
        <button type="submit">{{ __('passwords.reset_title') }}</button>
    </form>
@endsection
