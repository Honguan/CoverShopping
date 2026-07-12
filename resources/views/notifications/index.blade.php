@extends('layouts.app')

@section('content')
    <h1>{{ __('ui.notification_center') }}</h1>
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
                        <button type="submit">{{ __('ui.view_notification') }}</button>
                    </form>
                @endif
            </article>
        @empty
            <p>{{ __('ui.no_notifications') }}</p>
        @endforelse
    </section>
    {{ $notifications->links() }}
@endsection
