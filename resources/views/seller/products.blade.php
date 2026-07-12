@extends('layouts.app')

@section('content')
    <h1>{{ __('ui.seller_products') }}</h1>

    @if($lowStockProducts->isNotEmpty())
        <section class="panel">
            <h2>{{ __('ui.low_stock_alert') }}</h2>
            <div class="list">
                @foreach($lowStockProducts as $product)
                    <article class="row">
                        <strong>{{ $product->name }}</strong>
                        <span>{{ __('ui.remaining_stock', ['quantity' => $product->inventory]) }}</span>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <form class="panel stack" action="{{ route('seller.products.store') }}" method="post" enctype="multipart/form-data">
        @csrf
        <h2>{{ __('ui.create_product') }}</h2>
        <label>{{ __('ui.name') }}<input name="name" required></label>
        <label>{{ __('ui.description') }}<textarea name="description"></textarea></label>
        <label>{{ __('ui.price') }}<input name="price" type="number" min="0" required></label>
        <label>{{ __('ui.business_price') }}<input name="business_price" type="number" min="0"></label>
        <label>{{ __('ui.minimum_quantity') }}<input name="business_min_quantity" type="number" min="1" value="1"></label>
        <label>{{ __('ui.stock') }}<input name="inventory" type="number" min="0" required></label>
        <label>{{ __('ui.images') }}<input name="images[]" type="file" accept="image/*" multiple></label>
        <button type="submit">{{ __('ui.submit_for_review') }}</button>
    </form>

    <section class="list">
        @foreach($products as $product)
            <article class="panel">
                <form class="row" action="{{ route('seller.products.update', $product) }}" method="post" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')
                    <input name="name" value="{{ $product->name }}" required>
                    <input name="price" type="number" min="0" value="{{ $product->price }}" required>
                    <input name="business_price" type="number" min="0" value="{{ $product->business_price }}" placeholder="{{ __('ui.business_price') }}">
                    <input name="business_min_quantity" type="number" min="1" value="{{ $product->business_min_quantity }}" placeholder="{{ __('ui.minimum_quantity') }}">
                    <input name="inventory" type="number" min="0" value="{{ $product->inventory }}" required>
                    <input name="images[]" type="file" accept="image/*" multiple>
                    <span>{{ __('ui.product_status_'.$product->status) }}</span>
                    <button type="submit">{{ __('ui.update') }}</button>
                </form>
                <form class="row" action="{{ route('seller.products.variants.store', $product) }}" method="post">
                    @csrf
                    <input name="sku" placeholder="SKU" required>
                    <input name="option_name" placeholder="{{ __('ui.option_name') }}" required>
                    <input name="option_value" placeholder="{{ __('ui.option_value') }}" required>
                    <input name="price_delta" type="number" value="0" aria-label="{{ __('ui.price_delta') }}">
                    <input name="inventory" type="number" min="0" placeholder="{{ __('ui.variant_inventory') }}" required>
                    <button type="submit">{{ __('ui.add_variant') }}</button>
                </form>
                @foreach($product->variants as $variant)
                    <p>{{ $variant->sku }} / {{ $variant->displayName() }} / {{ __('ui.remaining_stock', ['quantity' => $variant->inventory]) }}</p>
                @endforeach
            </article>
        @endforeach
    </section>

    <section class="panel">
        <h2>{{ __('ui.product_questions') }}</h2>
        @forelse($questions as $question)
            <article>
                <strong>{{ $question->product->name }}</strong>
                <p>{{ $question->user->name }}：{{ $question->question }}</p>
                @foreach($question->answers as $answer)
                    <blockquote>{{ $answer->answer }}</blockquote>
                @endforeach
                @if($question->status !== 'answered')
                    <form class="stack" action="{{ route('seller.questions.answer', $question) }}" method="post">
                        @csrf
                        <label>{{ __('ui.reply') }}<textarea name="answer" required></textarea></label>
                        <button type="submit">{{ __('ui.submit_reply') }}</button>
                    </form>
                @endif
            </article>
        @empty
            <p>{{ __('ui.no_pending_product_questions') }}</p>
        @endforelse
    </section>
    {{ $products->links() }}
@endsection
