<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One fiscal year of an emiten's income statement, balance sheet and cash
 * flow, as shown on the stock detail page.
 *
 * Ratios are derived here rather than stored: they are cheap, and storing
 * them would mean a margin that silently disagrees with the revenue and
 * profit printed next to it after a restatement. Each returns null when its
 * inputs are missing, so the view never renders a computed 0% for a year the
 * data does not cover.
 */
class StockFinancial extends Model
{
    protected $fillable = [
        'ticker',
        'fiscal_year',
        'period_end',
        'revenue',
        'gross_profit',
        'operating_income',
        'net_income',
        'total_assets',
        'total_liabilities',
        'total_equity',
        'total_debt',
        'cash_and_equivalents',
        'operating_cash_flow',
        'investing_cash_flow',
        'financing_cash_flow',
        'capital_expenditure',
        'eps',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'period_end' => 'date',
            'revenue' => 'float',
            'gross_profit' => 'float',
            'operating_income' => 'float',
            'net_income' => 'float',
            'total_assets' => 'float',
            'total_liabilities' => 'float',
            'total_equity' => 'float',
            'total_debt' => 'float',
            'cash_and_equivalents' => 'float',
            'operating_cash_flow' => 'float',
            'investing_cash_flow' => 'float',
            'financing_cash_flow' => 'float',
            'capital_expenditure' => 'float',
            'eps' => 'float',
        ];
    }

    public function stockRef(): BelongsTo
    {
        return $this->belongsTo(StockRef::class, 'ticker', 'ticker');
    }

    /**
     * The most recent $years fiscal years for a ticker, newest first.
     */
    public function scopeRecentFor(Builder $query, string $ticker, int $years = 5): Builder
    {
        return $query->where('ticker', $ticker)
            ->orderByDesc('fiscal_year')
            ->limit($years);
    }

    /** Net profit as a share of revenue. */
    public function netMargin(): ?float
    {
        return $this->ratio($this->net_income, $this->revenue);
    }

    public function operatingMargin(): ?float
    {
        return $this->ratio($this->operating_income, $this->revenue);
    }

    public function grossMargin(): ?float
    {
        return $this->ratio($this->gross_profit, $this->revenue);
    }

    /** Return on equity for the year, as a percentage. */
    public function roe(): ?float
    {
        return $this->ratio($this->net_income, $this->total_equity);
    }

    public function roa(): ?float
    {
        return $this->ratio($this->net_income, $this->total_assets);
    }

    /**
     * Debt to equity. Negative equity returns null rather than a negative
     * ratio: the number would be arithmetically fine and financially
     * meaningless, and the same mistake in the AI score scored negative
     * equity as the safest possible balance sheet.
     */
    public function debtToEquity(): ?float
    {
        if ($this->total_debt === null || $this->total_equity === null || $this->total_equity <= 0) {
            return null;
        }

        return $this->total_debt / $this->total_equity;
    }

    /**
     * Operating cash flow less capital expenditure. Yahoo reports capex as a
     * negative number (it is a cash outflow), so it is added rather than
     * subtracted once normalised.
     */
    public function freeCashFlow(): ?float
    {
        if ($this->operating_cash_flow === null) {
            return null;
        }

        $capex = $this->capital_expenditure ?? 0.0;

        return $this->operating_cash_flow - abs($capex);
    }

    /**
     * Whether earnings are backed by cash. Net profit far above operating
     * cash flow year after year is the classic sign of profit that exists on
     * paper only.
     */
    public function cashConversion(): ?float
    {
        return $this->ratio($this->operating_cash_flow, $this->net_income);
    }

    /**
     * Percentage change of one field against an earlier year's row.
     */
    public function growthOver(?self $previous, string $field): ?float
    {
        if ($previous === null) {
            return null;
        }

        $now = $this->{$field};
        $then = $previous->{$field};

        // A base at or below zero makes a percentage meaningless — "up 300%
        // from a loss" says nothing about the size of the recovery.
        if ($now === null || $then === null || $then <= 0) {
            return null;
        }

        return (($now - $then) / $then) * 100;
    }

    private function ratio(?float $numerator, ?float $denominator): ?float
    {
        if ($numerator === null || $denominator === null || $denominator == 0.0) {
            return null;
        }

        return ($numerator / $denominator) * 100;
    }
}
