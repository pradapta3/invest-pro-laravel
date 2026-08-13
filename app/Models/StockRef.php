<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockRef extends Model
{
    protected $table = 'stock_refs';

    protected $primaryKey = 'ticker';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'ticker',
        'nama_perusahaan',
        'pe_ratio',
        'pb_ratio',
        'market_cap',
        'roe',
        'div_yield',
        'der',
        'sector',
        'financials_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'pe_ratio' => 'decimal:2',
            'pb_ratio' => 'decimal:2',
            'market_cap' => 'integer',
            'roe' => 'decimal:2',
            'div_yield' => 'decimal:2',
            'der' => 'decimal:2',
            'financials_fetched_at' => 'datetime',
        ];
    }

    public function price(): HasOne
    {
        return $this->hasOne(StockPrice::class, 'ticker', 'ticker');
    }

    public function portfolioPositions(): HasMany
    {
        return $this->hasMany(UserPortfolio::class, 'ticker', 'ticker');
    }

    /**
     * Whether UpdateFundamentals has ever landed figures on this row.
     *
     * roe, pe_ratio and der are NOT NULL DEFAULT 0, so an untouched row reads
     * as a company with no earnings and no debt. der = 0 in particular is a
     * genuine and desirable value — it means no borrowings — so it cannot be
     * used as its own presence test without penalising exactly the companies
     * with the strongest balance sheets.
     *
     * This is the same test UpdateFundamentals applies before it writes at
     * all (its $hasData), so the two agree by construction: a row it declined
     * to write reports false here.
     */
    public function hasFundamentals(): bool
    {
        return (float) $this->roe != 0.0
            || (float) $this->pe_ratio != 0.0
            || (int) $this->market_cap != 0;
    }

    /**
     * Ticker without the ".JK" Yahoo Finance suffix, e.g. "BBCA".
     */
    public function cleanTicker(): string
    {
        return str_replace('.JK', '', $this->ticker);
    }

    /**
     * Normalizes a route/query-string ticker (with or without the ".JK"
     * suffix, any case) to the DB primary-key form, e.g. "bbca" -> "BBCA.JK".
     */
    public static function normalizeTicker(string $raw): string
    {
        $ticker = strtoupper(trim($raw));

        return str_contains($ticker, '.JK') ? $ticker : $ticker.'.JK';
    }
}
