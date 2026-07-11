<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
            <input name="q" value="{{ request('q') }}" placeholder="{{ __('ui.search_products') }}">
            <button type="submit">{{ __('ui.search') }}</button>
        </form>
        <nav>
            @foreach(config('app.supported_locales') as $locale => $label)
                <a href="{{ route('locale.update', $locale) }}" @class(['active' => app()->getLocale() === $locale])>{{ $label }}</a>
            @endforeach
            <a href="{{ route('cart.index') }}">{{ __('ui.cart') }}</a>
            @auth
                <a href="{{ route('notifications.index') }}">{{ __('ui.notifications') }}</a>
                <a href="{{ route('addresses.index') }}">{{ __('ui.addresses') }}</a>
                <a href="{{ route('business_profile.edit') }}">{{ __('ui.business_account') }}</a>
                <a href="{{ route('orders.index') }}">{{ __('ui.orders') }}</a>
                @if(auth()->user()->isRole('seller', 'admin'))
                    <a href="{{ route('seller.products.index') }}">{{ __('ui.seller_dashboard') }}</a>
                @endif
                @if(auth()->user()->isRole('admin'))
                    <a href="{{ route('admin.dashboard') }}">{{ __('ui.admin_dashboard') }}</a>
                @endif
                <form action="{{ route('logout') }}" method="post">
                    @csrf
                    <button type="submit">{{ __('ui.logout') }}</button>
                </form>
            @else
                <a href="{{ route('login') }}">{{ __('ui.login') }}</a>
                <a href="{{ route('register') }}">{{ __('ui.register') }}</a>
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
