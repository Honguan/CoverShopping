@extends('layouts.app')

@section('content')
    <h1>商家商品管理</h1>

    @if($lowStockProducts->isNotEmpty())
        <section class="panel">
            <h2>低庫存提醒</h2>
            <div class="list">
                @foreach($lowStockProducts as $product)
                    <article class="row">
                        <strong>{{ $product->name }}</strong>
                        <span>剩餘 {{ $product->inventory }}</span>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <form class="panel stack" action="{{ route('seller.products.store') }}" method="post" enctype="multipart/form-data">
        @csrf
        <h2>新增商品</h2>
        <label>名稱<input name="name" required></label>
        <label>描述<textarea name="description"></textarea></label>
        <label>售價<input name="price" type="number" min="0" required></label>
        <label>企業價<input name="business_price" type="number" min="0"></label>
        <label>企業最低採購量<input name="business_min_quantity" type="number" min="1" value="1"></label>
        <label>庫存<input name="inventory" type="number" min="0" required></label>
        <label>圖片<input name="images[]" type="file" accept="image/*" multiple></label>
        <button type="submit">送出審核</button>
    </form>

    <section class="list">
        @foreach($products as $product)
            <article class="panel">
                <form class="row" action="{{ route('seller.products.update', $product) }}" method="post" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')
                    <input name="name" value="{{ $product->name }}" required>
                    <input name="price" type="number" min="0" value="{{ $product->price }}" required>
                    <input name="business_price" type="number" min="0" value="{{ $product->business_price }}" placeholder="企業價">
                    <input name="business_min_quantity" type="number" min="1" value="{{ $product->business_min_quantity }}" placeholder="企業最低量">
                    <input name="inventory" type="number" min="0" value="{{ $product->inventory }}" required>
                    <input name="images[]" type="file" accept="image/*" multiple>
                    <span>{{ $product->status }}</span>
                    <button type="submit">更新</button>
                </form>
                <form class="row" action="{{ route('seller.products.variants.store', $product) }}" method="post">
                    @csrf
                    <input name="sku" placeholder="SKU" required>
                    <input name="option_name" placeholder="規格名稱" required>
                    <input name="option_value" placeholder="規格內容" required>
                    <input name="price_delta" type="number" value="0">
                    <input name="inventory" type="number" min="0" placeholder="規格庫存" required>
                    <button type="submit">新增規格</button>
                </form>
                @foreach($product->variants as $variant)
                    <p>{{ $variant->sku }} / {{ $variant->displayName() }} / 庫存 {{ $variant->inventory }}</p>
                @endforeach
            </article>
        @endforeach
    </section>

    <section class="panel">
        <h2>商品問答</h2>
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
                        <label>回覆<textarea name="answer" required></textarea></label>
                        <button type="submit">送出回覆</button>
                    </form>
                @endif
            </article>
        @empty
            <p>目前沒有待處理問答。</p>
        @endforelse
    </section>
    {{ $products->links() }}
@endsection
