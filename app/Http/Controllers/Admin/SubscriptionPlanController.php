<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubscriptionPlanRequest;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => SubscriptionPlan::query()->orderBy('sort_order')->withCount('subscriptions')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.edit', ['plan' => new SubscriptionPlan]);
    }

    public function store(SubscriptionPlanRequest $request): RedirectResponse
    {
        SubscriptionPlan::create($request->normalizedData());

        return redirect()->route('admin.plans.index')->with('status', 'Paket baru dibuat.');
    }

    public function edit(SubscriptionPlan $plan): View
    {
        return view('admin.plans.edit', ['plan' => $plan]);
    }

    public function update(SubscriptionPlanRequest $request, SubscriptionPlan $plan): RedirectResponse
    {
        $plan->update($request->normalizedData());

        return redirect()->route('admin.plans.index')->with('status', 'Paket diperbarui.');
    }

    public function destroy(SubscriptionPlan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            $plan->update(['is_active' => false]);

            return back()->with('status', 'Paket punya riwayat langganan, jadi dinonaktifkan (bukan dihapus) agar riwayat tetap utuh.');
        }

        $plan->delete();

        return redirect()->route('admin.plans.index')->with('status', 'Paket dihapus.');
    }
}
