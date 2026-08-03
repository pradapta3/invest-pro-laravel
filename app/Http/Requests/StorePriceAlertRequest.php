<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePriceAlertRequest extends FormRequest
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
            'ticker' => ['required', 'string', 'max:20', 'exists:stock_refs,ticker'],
            'direction' => ['required', 'in:above,below'],
            'target_price' => ['required', 'numeric', 'gt:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // The datalist input could contain "BBCA - Bank Central Asia";
        // keep only the ticker token, matching StoreTradeRequest.
        $raw = strtoupper(trim((string) $this->input('ticker', '')));
        $firstToken = explode(' ', $raw)[0] ?? '';
        $ticker = str_contains($firstToken, '.JK') ? $firstToken : $firstToken.'.JK';

        $this->merge(['ticker' => $ticker]);
    }
}
