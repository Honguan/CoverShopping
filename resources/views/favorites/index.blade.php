@extends('layouts.app')

@section('content')
    <h1>{{ __('ui.favorites') }}</h1>

    <section class="grid">
        @forelse($products as $product)
            <div class="stack">
                @if($product->status === 'active')
                    @include('catalog.partials.product-card', ['product' => $product])
                @else
                    <article class="card">
                        <h2>{{ $product->name }}</h2>
                        <p>{{ __('ui.favorite_unavailable') }}</p>
                    </article>
                @endif
                <form action="{{ route('favorites.destroy', $product) }}" method="post">
                    @csrf
                    @method('DELETE')
                    <button type="submit" aria-label="{{ __('ui.remove') }} {{ $product->name }}">{{ __('ui.remove') }}</button>
                </form>
            </div>
        @empty
            <p>{{ __('ui.empty_favorites') }}</p>
            <a href="{{ route('catalog.index') }}">{{ __('ui.catalog_title') }}</a>
        @endforelse
    </section>

    {{ $products->links() }}
@endsection
