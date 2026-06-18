@extends('layouts.app')

@section('content')
    <h1>通知中心</h1>
    <section class="list">
        @forelse($notifications as $notification)
            <article class="row">
                <div>
                    <strong>{{ $notification->title }}</strong>
                    <p>{{ $notification->body }}</p>
                    <small>{{ $notification->created_at }}</small>
                </div>
                @if(!$notification->read_at)
                    <form action="{{ route('notifications.read', $notification) }}" method="post">
                        @csrf
                        @method('PATCH')
                        <button type="submit">查看</button>
                    </form>
                @endif
            </article>
        @empty
            <p>目前沒有通知。</p>
        @endforelse
    </section>
    {{ $notifications->links() }}
@endsection
