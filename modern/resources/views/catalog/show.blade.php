@extends('layouts.app')

@section('content')
    <section class="product-detail">
        <div class="gallery">
            @foreach($product->images as $image)
                @php $imageUrl = str_starts_with($image->path, '/') ? $image->path : asset('storage/' . $image->path); @endphp
                <img src="{{ $imageUrl }}" alt="{{ $product->name }}">
            @endforeach
        </div>
        <div>
            <h1>{{ $product->name }}</h1>
            <p class="price">${{ number_format($product->price) }}</p>
            <p>分類：{{ optional($product->category)->name ?? '未分類' }}</p>
            <p>商家：{{ $product->seller->name }}</p>
            <p>庫存：{{ $product->inventory }}</p>
            <p>{{ $product->description }}</p>
            <form action="{{ route('cart.items.store') }}" method="post">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="number" name="quantity" value="1" min="1" max="{{ max(1, $product->inventory) }}">
                <button type="submit" @disabled($product->inventory < 1)>加入購物車</button>
            </form>
            @auth
                <form action="{{ route('favorites.store', $product) }}" method="post">
                    @csrf
                    <button type="submit">加入收藏</button>
                </form>
            @endauth
        </div>
    </section>
@endsection
