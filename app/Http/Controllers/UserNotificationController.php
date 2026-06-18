<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    public function showNotifications(Request $request)
    {
        return view('notifications.index', [
            'notifications' => Notification::where('user_id', $request->user()->id)->latest()->paginate(30),
        ]);
    }

    public function markNotificationRead(Request $request, Notification $notification)
    {
        $this->authorize('view', $notification);

        $notification->update(['read_at' => now()]);

        return redirect($notification->url ?: route('notifications.index'));
    }
}
