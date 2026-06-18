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
                    <p>企業價 ${{ number_format($product->business_price) }}，最低採購量 {{ $product->business_min_quantity }}</p>
                @endif
            @endauth
            <p>分類：{{ optional($product->category)->name ?? '未分類' }}</p>
            <p>商家：{{ $product->seller->name }}</p>
            <p>庫存：{{ $product->inventory }}</p>
            <p>{{ $product->description }}</p>
            <form action="{{ route('cart.items.store') }}" method="post">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                @if($product->variants->isNotEmpty())
                    <label>規格
                        <select name="product_variant_id">
                            @foreach($product->variants as $variant)
                                <option value="{{ $variant->id }}">
                                    {{ $variant->displayName() }} / ${{ number_format($product->price + $variant->price_delta) }} / 庫存 {{ $variant->inventory }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif
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

    @if($relatedProducts->isNotEmpty())
        <section class="panel">
            <h2>你可能也喜歡</h2>
            <div class="mini-grid">
                @foreach($relatedProducts as $product)
                    @include('catalog.partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif

    <section class="panel">
        <h2>商品評價</h2>
        @forelse($product->reviews as $review)
            <article>
                <strong>{{ $review->user->name }}</strong>
                <span>{{ str_repeat('★', $review->rating) }}</span>
                <p>{{ $review->content }}</p>
            </article>
        @empty
            <p>目前沒有評價。</p>
        @endforelse
    </section>

    <section class="panel">
        <h2>商品問答</h2>
        @auth
            <form action="{{ route('questions.store', $product) }}" method="post">
                @csrf
                <label>提問<textarea name="question" required></textarea></label>
                <button type="submit">送出問題</button>
            </form>
        @endauth
        @forelse($product->questions as $question)
            <article>
                <strong>{{ $question->user->name }}</strong>
                <p>{{ $question->question }}</p>
                @foreach($question->answers as $answer)
                    <blockquote>{{ $answer->answer }} - {{ $answer->user->name }}</blockquote>
                @endforeach
            </article>
        @empty
            <p>目前沒有問答。</p>
        @endforelse
    </section>
@endsection
