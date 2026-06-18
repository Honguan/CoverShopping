@extends('layouts.app')

@section('content')
    <form class="panel" action="{{ route('login.store') }}" method="post">
        @csrf
        <h1>會員登入</h1>
        <label>帳號<input name="account" value="{{ old('account') }}" required></label>
        <label>密碼<input name="password" type="password" required></label>
        <label class="inline"><input name="remember" type="checkbox" value="1"> 記住我</label>
        <button type="submit">登入</button>
    </form>
@endsection
