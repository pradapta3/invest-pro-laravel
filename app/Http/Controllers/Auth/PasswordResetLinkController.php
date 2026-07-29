<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Sends a password-reset email via Laravel's built-in Password
     * broker. Whether it actually *arrives* depends on MAIL_MAILER being
     * a real SMTP provider — with the default `log` driver the reset
     * link is written to storage/logs/laravel.log instead of delivered,
     * which is fine for local testing but needs real mail credentials
     * before this works for actual users.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', 'Tautan reset password telah dikirim ke email Anda.')
            : back()->withErrors(['email' => __($status)]);
    }
}
