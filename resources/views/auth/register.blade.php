@extends('layouts.app')

@section('title', __('ui.register_title'))

@section('content')
    <form class="panel" action="{{ route('register.store') }}" method="post">
        @csrf
        <h1>{{ __('ui.register_title') }}</h1>
        <label>{{ __('ui.name') }}<input name="name" value="{{ old('name') }}" autocomplete="name" maxlength="80" required></label>
        <label>{{ __('ui.account') }}<input name="account" value="{{ old('account') }}" autocomplete="username" maxlength="64" required></label>
        <label>{{ __('ui.email') }}<input name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="160"></label>
        <label>{{ __('ui.birthday') }}<input name="birthday" type="date" value="{{ old('birthday') }}"></label>
        <p>{{ __('passwords.password_rules') }}</p>
        <label>{{ __('ui.password') }}<input name="password" type="password" autocomplete="new-password" minlength="10" required></label>
        <label>{{ __('ui.confirm_password') }}<input name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required></label>
        <button type="submit">{{ __('ui.create_account') }}</button>
    </form>
@endsection
