<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route on the subscriber's plan including a given feature, e.g.
 * ->middleware('plan:backtest'). The feature keys are defined in
 * config/subscription.php and ticked per plan in the admin plan form.
 *
 * Runs after EnsureSubscriptionActive, so by the time it sees a request the
 * user already has an active subscription — this only decides which parts of
 * the app that subscription covers.
 */
class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if ($request->user()?->planAllows($feature)) {
            return $next($request);
        }

        abort(403, 'Fitur ini tidak termasuk dalam paket langganan Anda.');
    }
}
