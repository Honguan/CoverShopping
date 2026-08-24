@extends('layouts.app')

@section('content')
    <section class="product-detail">
        <div class="gallery">
            @forelse($product->images as $image)
                @php $imageUrl = str_starts_with($image->path, '/') ? $image->path : asset('storage/' . $image->path); @endphp
                <img src="{{ $imageUrl }}" alt="{{ $product->name }}">
            @empty
                <div class="image-placeholder large">CoverShopping</div>
            @endforelse
        </div>
        <div class="panel">
            <h1>{{ $product->name }}</h1>
            <p class="price">${{ number_format($product->price) }}</p>
            @auth
                @if(auth()->user()->canUseBusinessPricing() && $product->business_price !== null)
                    <p>{{ __('ui.business_price') }} ${{ number_format($product->business_price) }}，{{ __('ui.minimum_quantity') }} {{ $product->business_min_quantity }}</p>
                @endif
            @endauth
            <p>{{ __('ui.category') }}：{{ optional($product->category)->name ?? __('ui.uncategorized') }}</p>
            <p>{{ __('ui.seller') }}：{{ $product->seller->name }}</p>
            @php
                $selectedInventory = $product->variants->first()?->inventory ?? $product->inventory;
            @endphp
            <p>{{ __('ui.stock') }}：<span data-selected-inventory>{{ $selectedInventory }}</span></p>
            <p>{{ $product->description }}</p>
            <form action="{{ route('cart.items.store') }}" method="post">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                @if($product->variants->isNotEmpty())
                    <label>{{ __('ui.variant') }}
                        <select name="product_variant_id" data-variant-selector>
                            @foreach($product->variants as $variant)
                                <option value="{{ $variant->id }}" data-inventory="{{ $variant->inventory }}">
                                    {{ $variant->displayName() }} / ${{ number_format($product->price + $variant->price_delta) }} / {{ __('ui.stock') }} {{ $variant->inventory }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <input type="number" name="quantity" value="1" min="1" max="{{ max(1, $selectedInventory) }}" data-cart-quantity>
                <button type="submit" @disabled($selectedInventory < 1) data-cart-submit>{{ __('ui.add_to_cart') }}</button>
            </form>
            @auth
                <form action="{{ route('favorites.store', $product) }}" method="post">
                    @csrf
                    <button type="submit">{{ __('ui.add_to_favorites') }}</button>
                </form>
            @endauth
        </div>
    </section>

    @if($relatedProducts->isNotEmpty())
        <section class="panel">
            <h2>{{ __('ui.related_products') }}</h2>
            <div class="mini-grid">
                @foreach($relatedProducts as $product)
                    @include('catalog.partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif

    <section class="panel">
        <h2>{{ __('ui.product_reviews') }}</h2>
        @forelse($reviews as $review)
            <article>
                <strong>{{ $review->user->name }}</strong>
                <span>{{ str_repeat('★', $review->rating) }}</span>
                <p>{{ $review->content }}</p>
            </article>
        @empty
            <p>{{ __('ui.no_reviews') }}</p>
        @endforelse
        {{ $reviews->links() }}
    </section>

    <section class="panel">
        <h2>{{ __('ui.product_questions') }}</h2>
        @auth
            <form action="{{ route('questions.store', $product) }}" method="post">
                @csrf
                <label>{{ __('ui.ask_question') }}<textarea name="question" required></textarea></label>
                <button type="submit">{{ __('ui.submit_question') }}</button>
            </form>
        @endauth
        @forelse($questions as $question)
            <article>
                <strong>{{ $question->user->name }}</strong>
                <p>{{ $question->question }}</p>
                @foreach($question->answers as $answer)
                    <blockquote>{{ $answer->answer }} - {{ $answer->user->name }}</blockquote>
                @endforeach
            </article>
        @empty
            <p>{{ __('ui.no_questions') }}</p>
        @endforelse
        {{ $questions->links() }}
    </section>
@endsection
