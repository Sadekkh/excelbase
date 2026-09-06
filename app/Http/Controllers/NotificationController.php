<?php

namespace App\Http\Controllers;

use App\Models\Notification;

class NotificationController extends Controller
{
    public function index()
    {
        $items = auth()->user()->notifications()->limit(40)->get();

        return view('notifications.index', [
            'user' => auth()->user(),
            'notifications' => $items,
            'workspaces' => auth()->user()->workspaces,
        ]);
    }

    public function read(Notification $notification)
    {
        abort_unless($notification->user_id === auth()->id(), 403);
        $notification->update(['read_at' => now()]);

        return $notification->url
            ? redirect($notification->url)
            : back();
    }
}
