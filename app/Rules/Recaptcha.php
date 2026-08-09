<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifies a Google reCAPTCHA v2 "g-recaptcha-response" token against
 * Google's siteverify endpoint.
 *
 * Verification is skipped entirely when RECAPTCHA_SECRET_KEY isn't
 * configured, so local/dev/staging environments that haven't set up a
 * reCAPTCHA site don't get locked out of login — the widget itself is only
 * rendered when RECAPTCHA_SITE_KEY is set (see login.blade.php), so in
 * practice both are configured together in production.
 */
class Recaptcha implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.recaptcha.secret_key');

        if (blank($secret)) {
            return;
        }

        if (blank($value)) {
            $fail('Silakan verifikasi bahwa Anda bukan robot.');

            return;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secret,
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);

            if (! $response->successful() || $response->json('success') !== true) {
                $fail('Verifikasi CAPTCHA gagal, silakan coba lagi.');
            }
        } catch (Throwable $e) {
            Log::warning('reCAPTCHA verification request failed.', ['error' => $e->getMessage()]);

            $fail('Verifikasi CAPTCHA gagal, silakan coba lagi.');
        }
    }
}
