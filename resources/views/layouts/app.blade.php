<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="topbar">
        <a class="brand" href="{{ route('catalog.index') }}">CoverShopping</a>
        <form class="search" action="{{ route('catalog.index') }}" method="get">
            <input name="q" value="{{ request('q') }}" placeholder="搜尋商品">
            <button type="submit">搜尋</button>
        </form>
        <nav>
            <a href="{{ route('cart.index') }}">購物車</a>
            @auth
                <a href="{{ route('notifications.index') }}">通知</a>
                <a href="{{ route('business_profile.edit') }}">企業會員</a>
                <a href="{{ route('orders.index') }}">我的訂單</a>
                @if(auth()->user()->isRole('seller', 'admin'))
                    <a href="{{ route('seller.products.index') }}">商家後台</a>
                @endif
                @if(auth()->user()->isRole('admin'))
                    <a href="{{ route('admin.dashboard') }}">管理後台</a>
                @endif
                <form action="{{ route('logout') }}" method="post">
                    @csrf
                    <button type="submit">登出</button>
                </form>
            @else
                <a href="{{ route('login') }}">登入</a>
                <a href="{{ route('register') }}">註冊</a>
            @endauth
        </nav>
    </header>

    <main class="container">
        @if(session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
