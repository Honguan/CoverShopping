@extends('layouts.app')

@section('content')
    <h1>商家商品管理</h1>
    <form class="panel" action="{{ route('seller.products.store') }}" method="post" enctype="multipart/form-data">
        @csrf
        <h2>新增商品</h2>
        <label>名稱<input name="name" required></label>
        <label>描述<textarea name="description"></textarea></label>
        <label>價格<input name="price" type="number" min="0" required></label>
        <label>庫存<input name="inventory" type="number" min="0" required></label>
        <label>圖片<input name="images[]" type="file" accept="image/*" multiple></label>
        <button type="submit">送出審核</button>
    </form>

    <section class="list">
        @foreach($products as $product)
            <form class="row" action="{{ route('seller.products.update', $product) }}" method="post" enctype="multipart/form-data">
                @csrf
                @method('PATCH')
                <input name="name" value="{{ $product->name }}" required>
                <input name="price" type="number" min="0" value="{{ $product->price }}" required>
                <input name="inventory" type="number" min="0" value="{{ $product->inventory }}" required>
                <input name="images[]" type="file" accept="image/*" multiple>
                <span>{{ $product->status }}</span>
                <button type="submit">更新</button>
            </form>
        @endforeach
    </section>
    {{ $products->links() }}
@endsection
