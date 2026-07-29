<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $activeSubscriptions = Subscription::query()
            ->with('plan')
            ->where('status', SubscriptionStatus::Active)
            ->get();

        $estimatedMrr = (float) $activeSubscriptions->sum(fn (Subscription $s) => (float) $s->plan->price_per_month);

        return view('admin.dashboard', [
            'totalUsers' => User::query()->count(),
            'activeCount' => $activeSubscriptions->count(),
            'pendingCount' => Subscription::query()->where('status', SubscriptionStatus::Pending)->count(),
            'expiredCount' => Subscription::query()->where('status', SubscriptionStatus::Expired)->count(),
            'estimatedMrr' => $estimatedMrr,
            'recentUsers' => User::query()->latest()->limit(8)->get(),
            'pendingSubscriptions' => Subscription::query()
                ->with(['user', 'plan'])
                ->where('status', SubscriptionStatus::Pending)
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
}
