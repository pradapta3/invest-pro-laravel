<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q', '')->toString());

        $users = User::query()
            ->withCount(['portfolios', 'histories'])
            ->when($search !== '', fn ($q) => $q->where(fn ($qq) => $qq->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $users->getCollection()->each(fn (User $u) => $u->setRelation('currentSub', $u->currentSubscription()));

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'subscriptions' => $user->subscriptions()->with('plan')->latest()->get(),
            'plans' => SubscriptionPlan::activeOnes(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', 'min:8'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->is_admin = $request->boolean('is_admin');
        if (! empty($data['password'])) {
            $user->password = $data['password']; // hashed automatically by the 'password' => 'hashed' cast
        }
        $user->save();

        return back()->with('status', 'Data pengguna diperbarui.');
    }

    public function activateSubscription(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'months' => ['required', 'integer', 'min:1', 'max:24'],
        ]);

        $active = [
            'subscription_plan_id' => $data['subscription_plan_id'],
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
            'ends_at' => now()->addMonths((int) $data['months']),
        ];

        // Promote the request the user actually made, rather than adding a
        // second row beside it. Creating a new one left the original Pending
        // subscription untouched, and the admin dashboard counts pending rows
        // — so an approved user stayed on the "Menunggu Aktivasi" list and in
        // its badge forever, even though their access had already opened.
        $pending = $user->subscriptions()
            ->where('status', SubscriptionStatus::Pending)
            ->latest('id')
            ->first();

        if ($pending !== null) {
            $pending->update($active);
        } else {
            // No pending request — an admin granting or renewing access
            // directly. Nothing to promote, so start a fresh subscription.
            Subscription::create($active + ['user_id' => $user->id]);
        }

        // Anything still pending for this user is now superseded. Without this,
        // a second registration attempt would leave a stray row that puts them
        // straight back on the pending list.
        $user->subscriptions()
            ->where('status', SubscriptionStatus::Pending)
            ->update(['status' => SubscriptionStatus::Cancelled]);

        return back()->with('status', "Langganan {$user->name} diaktifkan.");
    }

    public function cancelSubscription(User $user): RedirectResponse
    {
        $subscription = $user->currentSubscription();

        if ($subscription !== null) {
            $subscription->update(['status' => SubscriptionStatus::Cancelled]);
        }

        return back()->with('status', "Langganan {$user->name} dibatalkan.");
    }
}
