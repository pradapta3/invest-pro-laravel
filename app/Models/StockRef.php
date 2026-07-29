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
