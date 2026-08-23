@php
    $image = optional($product->primaryImage)->path;
    $imageUrl = $image ? (str_starts_with($image, '/') ? $image : asset('storage/' . $image)) : null;
    $variantInventory = $product->variants->sum('inventory');
    $inventory = $product->variants->isNotEmpty() ? $variantInventory : $product->inventory;
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
    <p>{{ __('ui.stock') }} {{ $inventory }}</p>
    @if($product->variants->isNotEmpty())
        <a class="button" href="{{ route('catalog.show', $product) }}">{{ __('ui.variant') }}</a>
    @else
        <form action="{{ route('cart.items.store') }}" method="post">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <input type="number" name="quantity" value="1" min="1" max="{{ max(1, $inventory) }}">
            <button type="submit" @disabled($inventory < 1)>{{ __('ui.add_to_cart') }}</button>
        </form>
    @endif
</article>
