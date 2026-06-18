@extends('layouts.app')

@section('content')
    <h1>管理員後台</h1>
    <section class="columns">
        <div class="panel">
            <h2>會員</h2>
            @foreach($users as $user)
                <form class="stack" action="{{ route('admin.users.status', $user) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <strong>{{ $user->account }}</strong>
                    <select name="status">
                        @foreach(['pending', 'active', 'suspended'] as $status)
                            <option value="{{ $status }}" @selected($user->status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <select name="role">
                        @foreach(['customer', 'seller', 'admin'] as $role)
                            <option value="{{ $role }}" @selected($user->role === $role)>{{ $role }}</option>
                        @endforeach
                    </select>
                    <button type="submit">更新</button>
                </form>
            @endforeach
        </div>
        <div class="panel">
            <h2>商品審核</h2>
            @foreach($products as $product)
                <form class="stack" action="{{ route('admin.products.status', $product) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <strong>{{ $product->name }}</strong>
                    <select name="status">
                        @foreach(['draft', 'pending', 'active', 'archived'] as $status)
                            <option value="{{ $status }}" @selected($product->status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <button type="submit">更新</button>
                </form>
            @endforeach
        </div>
        <div class="panel">
            <h2>付款狀態</h2>
            @foreach($orders as $order)
                <form class="stack" action="{{ route('admin.orders.payment', $order) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <strong>{{ $order->number }}</strong>
                    <select name="payment_status">
                        @foreach(['unpaid', 'paid', 'failed', 'refunded'] as $status)
                            <option value="{{ $status }}" @selected($order->payment_status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <button type="submit">更新</button>
                </form>
            @endforeach
        </div>
    </section>
@endsection
