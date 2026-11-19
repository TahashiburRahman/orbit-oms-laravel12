<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->when($request->input('filter') === 'unread', fn ($q) => $q->whereNull('read_at'))->paginate(20)->withQueryString();

        return view('notifications.index', compact('notifications'));
    }

    public function read(Request $request, string $notification)
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function unread(Request $request, string $notification)
    {
        $request->user()->notifications()->findOrFail($notification)->markAsUnread();

        return back()->with('success', 'Notification marked as unread.');
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
