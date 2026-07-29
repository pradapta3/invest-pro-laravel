<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'plans' => SubscriptionPlan::activeOnes(),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->string('name'),
                'email' => $request->string('email'),
                'password' => $request->string('password'), // hashed automatically by User's 'password' => 'hashed' cast
            ]);

            // No payment gateway is wired up — the subscription starts
            // 'pending' until an admin activates it from the admin
            // dashboard. See Subscription/SubscriptionStatus.
            Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $request->integer('subscription_plan_id'),
                'status' => SubscriptionStatus::Pending,
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('status', 'Akun berhasil dibuat! Langganan Anda berstatus "Menunggu Aktivasi" — admin akan mengaktifkannya setelah pembayaran dikonfirmasi.');
    }
}
