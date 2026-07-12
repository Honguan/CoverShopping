@extends('layouts.app')

@section('content')
    <section class="toolbar">
        <div>
            <h1>{{ __('ui.catalog_title') }}</h1>
            <p>{{ __('ui.catalog_description') }}</p>
        </div>
        <div class="chips">
            <a href="{{ route('catalog.index', request()->except('category')) }}">{{ __('ui.all_categories') }}</a>
            @foreach($categories as $category)
                <a href="{{ route('catalog.index', array_merge(request()->query(), ['category' => $category->slug])) }}">{{ $category->name }}</a>
            @endforeach
        </div>
    </section>

    <form class="panel filters" action="{{ route('catalog.index') }}" method="get">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('ui.keyword') }}">
        <input name="min_price" type="number" min="0" value="{{ request('min_price') }}" placeholder="{{ __('ui.minimum_price') }}">
        <input name="max_price" type="number" min="0" value="{{ request('max_price') }}" placeholder="{{ __('ui.maximum_price') }}">
        <select name="sort">
            <option value="latest" @selected(request('sort', 'latest') === 'latest')>{{ __('ui.latest') }}</option>
            <option value="price_asc" @selected(request('sort') === 'price_asc')>{{ __('ui.price_low_to_high') }}</option>
            <option value="price_desc" @selected(request('sort') === 'price_desc')>{{ __('ui.price_high_to_low') }}</option>
            <option value="oldest" @selected(request('sort') === 'oldest')>{{ __('ui.oldest') }}</option>
        </select>
        @if(request('category'))
            <input type="hidden" name="category" value="{{ request('category') }}">
        @endif
        <button type="submit">{{ __('ui.apply_filters') }}</button>
    </form>

    @if($recentlyViewedProducts->isNotEmpty())
        <section class="panel">
            <h2>{{ __('ui.recently_viewed') }}</h2>
            <div class="mini-grid">
                @foreach($recentlyViewedProducts as $product)
                    @include('catalog.partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif

    <section class="panel">
        <h2>{{ __('ui.popular_products') }}</h2>
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
            <p>{{ __('ui.no_products') }}</p>
        @endforelse
    </section>

    {{ $products->links() }}
@endsection
