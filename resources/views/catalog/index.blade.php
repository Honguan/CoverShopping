@extends('layouts.app')

@section('content')
    <section class="toolbar">
        <div>
            <h1>商品列表</h1>
            <p>快速搜尋、篩選分類與價格，找到適合 B2C 或 B2B 採購的商品。</p>
        </div>
        <div class="chips">
            <a href="{{ route('catalog.index', request()->except('category')) }}">全部分類</a>
            @foreach($categories as $category)
                <a href="{{ route('catalog.index', array_merge(request()->query(), ['category' => $category->slug])) }}">{{ $category->name }}</a>
            @endforeach
        </div>
    </section>

    <form class="panel filters" action="{{ route('catalog.index') }}" method="get">
        <input name="q" value="{{ request('q') }}" placeholder="關鍵字">
        <input name="min_price" type="number" min="0" value="{{ request('min_price') }}" placeholder="最低價格">
        <input name="max_price" type="number" min="0" value="{{ request('max_price') }}" placeholder="最高價格">
        <select name="sort">
            <option value="latest" @selected(request('sort', 'latest') === 'latest')>最新上架</option>
            <option value="price_asc" @selected(request('sort') === 'price_asc')>價格低到高</option>
            <option value="price_desc" @selected(request('sort') === 'price_desc')>價格高到低</option>
            <option value="oldest" @selected(request('sort') === 'oldest')>較早上架</option>
        </select>
        @if(request('category'))
            <input type="hidden" name="category" value="{{ request('category') }}">
        @endif
        <button type="submit">套用篩選</button>
    </form>

    @if($recentlyViewedProducts->isNotEmpty())
        <section class="panel">
            <h2>最近瀏覽</h2>
            <div class="mini-grid">
                @foreach($recentlyViewedProducts as $product)
                    @include('catalog.partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif

    <section class="panel">
        <h2>熱門推薦</h2>
        <div class="mini-grid">
            @foreach($popularProducts as $product)
                @include('catalog.partials.product-card', ['product' => $product])
            @endforeach
        </div>
    </section>

    <section class="grid">
        @forelse($products as $product)
            @include('catalog.partials.product-card', ['product' => $product])
        @empty
            <p>目前沒有符合條件的商品。</p>
        @endforelse
    </section>

    {{ $products->links() }}
@endsection
