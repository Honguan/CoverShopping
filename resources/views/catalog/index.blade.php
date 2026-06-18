@extends('layouts.app')

@section('content')
    <section class="toolbar">
        <h1>商品列表</h1>
        <div class="chips">
            <a href="{{ route('catalog.index', request()->except('category')) }}">全部</a>
            @foreach($categories as $category)
                <a href="{{ route('catalog.index', array_merge(request()->query(), ['category' => $category->slug])) }}">{{ $category->name }}</a>
            @endforeach
        </div>
    </section>

    <section class="grid">
        @forelse($products as $product)
            @php
                $image = optional($product->primaryImage)->path;
                $imageUrl = $image ? (str_starts_with($image, '/') ? $image : asset('storage/' . $image)) : null;
            @endphp
            <article class="card">
                <a href="{{ route('catalog.show', $product) }}">
                    @if($imageUrl)
                        <img src="{{ $imageUrl }}" alt="{{ $product->name }}">
                    @endif
                    <h2>{{ $product->name }}</h2>
                </a>
                <p class="price">${{ number_format($product->price) }}</p>
                <p>庫存 {{ $product->inventory }}</p>
                <form action="{{ route('cart.items.store') }}" method="post">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="number" name="quantity" value="1" min="1" max="{{ max(1, $product->inventory) }}">
                    <button type="submit" @disabled($product->inventory < 1)>加入購物車</button>
                </form>
            </article>
        @empty
            <p>目前沒有符合條件的商品。</p>
        @endforelse
    </section>

    {{ $products->links() }}
@endsection
