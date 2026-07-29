<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the actual trading app behind an active subscription. Admins
 * bypass this entirely — they're staff, not paying customers. Everyone
 * else with a pending/expired/cancelled subscription is redirected to a
 * holding page explaining why (see SubscriptionController@pending).
 */
class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_admin && ! $user->hasActiveSubscription()) {
            return redirect()->route('subscription.pending');
        }

        return $next($request);
    }
}
