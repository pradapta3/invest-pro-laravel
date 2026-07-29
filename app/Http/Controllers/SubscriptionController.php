<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    /**
     * Holding page for logged-in users without an active subscription —
     * see EnsureSubscriptionActive.
     */
    public function pending(Request $request): View
    {
        return view('subscription.pending', [
            'subscription' => $request->user()->currentSubscription(),
        ]);
    }
}
