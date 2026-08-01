<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        Log::info('Notifications: liste consultée', ['user_id' => Auth::id()]);

        $notifications = Auth::user()
            ->notifications()
            ->orderBy('cree_le', 'desc')
            ->paginate(20);

        Auth::user()
            ->notificationsNonLues()
            ->update(['lu_le' => now()]);

        return view('client.notifications', compact('notifications'));
    }

    public function marquerLu(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $notification = Notification::where('user_id', Auth::id())
            ->findOrFail($id);

        $notification->marquerLue();

        Log::info('Notifications: notification marquée lue', [
            'user_id' => Auth::id(),
            'notification_id' => $id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function count(): JsonResponse
    {
        $count = Auth::user()->notificationsNonLues()->count();

        Log::debug('Notifications: compteur non lus', ['user_id' => Auth::id(), 'count' => $count]);

        return response()->json(['count' => $count]);
    }
}
