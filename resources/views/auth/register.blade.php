@extends('layouts.app')

@section('content')
    <form class="panel" action="{{ route('register.store') }}" method="post">
        @csrf
        <h1>會員註冊</h1>
        <label>姓名<input name="name" value="{{ old('name') }}" required></label>
        <label>帳號<input name="account" value="{{ old('account') }}" required></label>
        <label>Email<input name="email" type="email" value="{{ old('email') }}"></label>
        <label>生日<input name="birthday" type="date" value="{{ old('birthday') }}"></label>
        <label>密碼<input name="password" type="password" required></label>
        <label>確認密碼<input name="password_confirmation" type="password" required></label>
        <button type="submit">建立帳號</button>
    </form>
@endsection
