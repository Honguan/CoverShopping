@extends('layouts.app')

@section('content')
    <h1>{{ __('ui.notification_center') }}</h1>
    <section class="list">
        @forelse($notifications as $notification)
            <article class="row">
                <div>
                    <strong>{{ str_starts_with($notification->title, 'ui.') ? __($notification->title) : $notification->title }}</strong>
                    <p>{{ is_string($notification->body) && str_starts_with($notification->body, 'ui.') ? __($notification->body) : $notification->body }}</p>
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
