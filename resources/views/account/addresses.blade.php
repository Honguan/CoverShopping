@extends('layouts.app')

@section('content')
    <h1>地址簿</h1>

    <form class="panel stack" action="{{ route('addresses.store') }}" method="post">
        @csrf
        <h2>新增收件地址</h2>
        <label>收件人<input name="recipient_name" required></label>
        <label>電話<input name="phone" required></label>
        <label>郵遞區號<input name="postal_code"></label>
        <label>城市<input name="city" required></label>
        <label>區域<input name="district"></label>
        <label>詳細地址<input name="address_line" required></label>
        <label class="inline"><input name="is_default" type="checkbox" value="1"> 設為預設地址</label>
        <button type="submit">新增地址</button>
    </form>

    <section class="list">
        @forelse($addresses as $address)
            <article class="panel row">
                <div>
                    <strong>{{ $address->recipient_name }}</strong>
                    <p>{{ $address->phone }}</p>
                    <p>{{ $address->postal_code }} {{ $address->city }}{{ $address->district }}{{ $address->address_line }}</p>
                    @if($address->is_default)
                        <span class="badge">預設</span>
                    @endif
                </div>
                <div class="inline">
                    @unless($address->is_default)
                        <form action="{{ route('addresses.default', $address) }}" method="post">
                            @csrf
                            @method('PATCH')
                            <button type="submit">設為預設</button>
                        </form>
                    @endunless
                    <form action="{{ route('addresses.destroy', $address) }}" method="post">
                        @csrf
                        @method('DELETE')
                        <button type="submit">刪除</button>
                    </form>
                </div>
            </article>
        @empty
            <p>尚未建立收件地址。</p>
        @endforelse
    </section>
@endsection
