@extends('layouts.app')

@section('title', __('ui.account_settings'))

@section('content')
    <h1>{{ __('ui.account_settings') }}</h1>

    <form class="panel stack" action="{{ route('account.update') }}" method="post">
        @csrf
        @method('PATCH')
        <h2>{{ __('ui.profile') }}</h2>
        <p>{{ __('ui.account') }}：{{ $user->account }}</p>
        @php($name = old('name', $user->name))
        @php($email = old('email', $user->email))
        @php($birthday = old('birthday', $user->birthday?->format('Y-m-d')))
        <label>{{ __('ui.name') }}<input name="name" value="{{ is_string($name) ? $name : '' }}" autocomplete="name" maxlength="80" required></label>
        <label>{{ __('ui.email') }}<input name="email" type="email" value="{{ is_string($email) ? $email : '' }}" autocomplete="email" maxlength="160"></label>
        <label>{{ __('ui.birthday') }}<input name="birthday" type="date" value="{{ is_string($birthday) ? $birthday : '' }}"></label>
        <button type="submit">{{ __('ui.update') }}</button>
    </form>

    <form class="panel stack" action="{{ route('account.password') }}" method="post">
        @csrf
        @method('PUT')
        <h2>{{ __('ui.change_password') }}</h2>
        <p>{{ __('passwords.password_rules') }}</p>
        <label>{{ __('ui.current_password') }}<input name="current_password" type="password" autocomplete="current-password" required></label>
        <label>{{ __('ui.new_password') }}<input name="password" type="password" autocomplete="new-password" minlength="10" required></label>
        <label>{{ __('ui.confirm_password') }}<input name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required></label>
        <button type="submit">{{ __('ui.change_password') }}</button>
    </form>
@endsection
