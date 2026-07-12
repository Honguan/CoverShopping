@extends('layouts.app')

@section('content')
    <form class="panel" action="{{ route('register.store') }}" method="post">
        @csrf
        <h1>{{ __('ui.register_title') }}</h1>
        <label>{{ __('ui.name') }}<input name="name" value="{{ old('name') }}" required></label>
        <label>{{ __('ui.account') }}<input name="account" value="{{ old('account') }}" required></label>
        <label>{{ __('ui.email') }}<input name="email" type="email" value="{{ old('email') }}"></label>
        <label>{{ __('ui.birthday') }}<input name="birthday" type="date" value="{{ old('birthday') }}"></label>
        <label>{{ __('ui.password') }}<input name="password" type="password" required></label>
        <label>{{ __('ui.confirm_password') }}<input name="password_confirmation" type="password" required></label>
        <button type="submit">{{ __('ui.create_account') }}</button>
    </form>
@endsection
