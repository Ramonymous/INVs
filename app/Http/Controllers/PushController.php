<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Notifications\TestPushNotification; // Make sure to import your notification class

class PushController extends Controller
{
    /**
     * Store the user's push subscription.
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        $user = Auth::user();

        // The updatePushSubscription method handles creating or updating the subscription.
        $user->updatePushSubscription(
            $request->endpoint,
            $request->keys['p256dh'],
            $request->keys['auth']
        );

        return response()->json(['success' => true], 201);
    }

    /**
     * Send a test notification to the authenticated user.
     */
    public function sendTestNotification()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Send the notification
        $user->notify(new TestPushNotification());

        // You can also return a redirect or a view
        return back()->with('success', 'Test notification sent!');
    }
}