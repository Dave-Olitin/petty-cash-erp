<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * Store the web push subscription for the authenticated user.
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'endpoint' => 'required',
            'keys.auth' => 'required',
            'keys.p256dh' => 'required'
        ]);

        $endpoint = $request->input('endpoint');
        $key      = $request->input('keys.p256dh');
        $token    = $request->input('keys.auth');

        // This method comes from the HasPushSubscriptions trait on the User model
        $request->user()->updatePushSubscription($endpoint, $key, $token);

        return response()->json(['success' => true], 200);
    }

    /**
     * Remove the web push subscription.
     */
    public function unsubscribe(Request $request)
    {
        $request->validate([
            'endpoint' => 'required',
        ]);

        $request->user()->deletePushSubscription($request->endpoint);

        return response()->json(['success' => true], 200);
    }
}
