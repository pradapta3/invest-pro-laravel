<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a "push this signal to Telegram" request from the dashboard,
 * stock detail page or Titan Radar. Only a ticker (and which message
 * format to use) is accepted from the client — unlike the legacy
 * index.php/titan_scan.php handlers, which trusted whatever price/score/
 * plan numbers the browser POSTed back and broadcast them verbatim.
 * TelegramBroadcastController recomputes everything server-side from the
 * ticker instead.
 */
class BroadcastSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ticker' => ['required', 'string', 'max:20'],
            'format' => ['sometimes', 'in:standard,titan'],
        ];
    }

    public function normalizedTicker(): string
    {
        $ticker = strtoupper((string) $this->input('ticker', ''));

        return str_contains($ticker, '.JK') ? $ticker : $ticker.'.JK';
    }
}
