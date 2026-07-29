<?php

namespace App\Http\Requests;

use App\Models\UserPortfolio;
use App\Models\UserWallet;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a BUY/SELL order before it reaches PortfolioService. Replaces
 * portfolio.php's inline `if ($price>0 && $lot>0)` / balance / lot checks,
 * which ran after the query was already built and gave no user feedback
 * on failure (the page just silently didn't apply the trade).
 *
 * The balance/lot-sufficiency check below is a courtesy for form
 * feedback; PortfolioService re-checks the same thing inside a locked DB
 * transaction as the authoritative guard (see InsufficientFundsException/
 * InsufficientLotsException) in case state changed in between.
 */
class StoreTradeRequest extends FormRequest
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
            'action' => ['required', 'in:buy,sell'],
            'ticker' => ['required', 'string', 'max:20'],
            'price' => ['required', 'numeric', 'gt:0'],
            'lot' => ['required', 'integer', 'gt:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // The datalist input could contain "BBCA - Bank Central Asia";
        // keep only the ticker token, as portfolio.php did.
        $raw = strtoupper(trim((string) $this->input('ticker', '')));
        $firstToken = explode(' ', $raw)[0] ?? '';

        $this->merge(['ticker' => $firstToken]);
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ticker = $this->normalizedTicker();
            $lotSize = config('trading.lot_size');
            $value = (float) $this->input('price') * (int) $this->input('lot') * $lotSize;
            $userId = $this->user()->id;

            if ($this->input('action') === 'buy') {
                $fee = $value * config('trading.fees.buy_rate');
                $wallet = UserWallet::query()->where('user_id', $userId)->first();
                $balance = $wallet ? (float) $wallet->cash_balance : (float) config('trading.starting_cash_balance');

                if ($balance < ($value + $fee)) {
                    $validator->errors()->add('lot', "Saldo tidak cukup untuk membeli {$this->input('lot')} lot {$ticker}.");
                }
            } else {
                $position = UserPortfolio::query()->where('user_id', $userId)->where('ticker', $ticker)->first();

                if (! $position || $position->lots < (int) $this->input('lot')) {
                    $validator->errors()->add('lot', "Lot {$ticker} yang dimiliki tidak cukup untuk dijual.");
                }
            }
        });
    }

    public function normalizedTicker(): string
    {
        $ticker = strtoupper((string) $this->input('ticker', ''));

        return str_contains($ticker, '.JK') ? $ticker : $ticker.'.JK';
    }
}
