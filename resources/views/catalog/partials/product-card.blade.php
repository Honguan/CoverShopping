@php
    $image = optional($product->primaryImage)->path;
    $imageUrl = $image ? (str_starts_with($image, '/') ? $image : asset('storage/' . $image)) : null;
@endphp

<article class="card">
    <a href="{{ route('catalog.show', $product) }}">
        @if($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ $product->name }}">
        @else
            <div class="image-placeholder">CoverShopping</div>
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
