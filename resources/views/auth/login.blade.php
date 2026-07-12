@extends('layouts.app')

@section('content')
    <form class="panel" action="{{ route('login.store') }}" method="post">
        @csrf
        <h1>{{ __('ui.login_title') }}</h1>
        <label>{{ __('ui.account') }}<input name="account" value="{{ old('account') }}" required></label>
        <label>{{ __('ui.password') }}<input name="password" type="password" required></label>
        <label class="inline"><input name="remember" type="checkbox" value="1"> {{ __('ui.remember_me') }}</label>
        <button type="submit">{{ __('ui.login') }}</button>
    </form>
@endsection
