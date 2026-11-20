<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    public function showNotifications(Request $request)
    {
        return view('notifications.index', [
            'notifications' => Notification::where('user_id', $request->user()->id)->latest()->latest('id')->paginate(30),
        ]);
    }

    public function markAllNotificationsRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return redirect()->route('notifications.index')->with('status', __('ui.notifications_marked_read'));
    }

    public function markNotificationRead(Request $request, Notification $notification)
    {
        $this->authorize('view', $notification);

        Notification::whereKey($notification->id)->whereNull('read_at')->update(['read_at' => now()]);

        return redirect($notification->url ?: route('notifications.index'));
    }
}
