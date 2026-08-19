<?php

namespace App\Services;

use App\Models\StockFinancial;
use App\Models\StockPrice;
use App\Models\StockRef;

/**
 * One definition of every valuation metric the app displays.
 *
 * These were computed in three different places and did not agree. The stock
 * detail page's sector table printed stock_refs.roe — a snapshot Yahoo
 * supplies — while the annual statements panel directly beneath it printed
 * net income over equity from stock_financials. Two ROE figures for the same
 * emiten, on the same screen, differing by whatever the gap between Yahoo's
 * trailing twelve months and the last filed year happened to be. Every caller
 * goes through this class now, so a disagreement means the inputs differ, not
 * the arithmetic.
 *
 * Every method returns null rather than zero when it cannot be computed. That
 * distinction is the whole point: zero is a real reading — a company can have
 * no debt — and collapsing "unknown" into it is what had the AI score paying
 * out for data that was never fetched, and what makes a dash on screen turn
 * into a confident "0.00x".
 *
 * No method divides without checking its denominator first, so a missing or
 * zero-equity filing cannot raise a division error mid-render.
 */
class FinancialMetricsService
{
    /**
     * Return on equity as a percentage.
     *
     * Prefers the last filed annual statement, because it is auditable and its
     * inputs are on screen next to it; falls back to the vendor snapshot when
     * no statement has been fetched. $source tells the caller which it got, so
     * a page can label a figure rather than presenting two provenances as one
     * number.
     *
     * @return array{value: float|null, source: string}
     */
    public function returnOnEquity(?StockRef $ref, ?StockFinancial $latest = null): array
    {
        if ($latest !== null) {
            $value = $this->ratioPct($latest->net_income, $latest->total_equity);

            if ($value !== null) {
                return ['value' => $value, 'source' => 'laporan '.$latest->fiscal_year];
            }
        }

        // Only when the vendor actually reported something. 0.0 here means
        // "never fetched" — see StockRef::hasFundamentals().
        if ($ref !== null && $ref->hasFundamentals() && (float) $ref->roe != 0.0) {
            return ['value' => (float) $ref->roe, 'source' => 'snapshot'];
        }

        return ['value' => null, 'source' => 'tidak tersedia'];
    }

    /**
     * Return on equity for one filed year, and nothing else.
     *
     * returnOnEquity() falls back to the vendor snapshot when a statement
     * cannot answer, which is right for a headline figure and wrong for a
     * column headed with a year: a 2021 column showing this year's ROE
     * because 2021 filed no equity is not a gap, it is a false statement
     * about 2021.
     */
    public function returnOnEquityForYear(?StockFinancial $year): ?float
    {
        return $year === null ? null : $this->ratioPct($year->net_income, $year->total_equity);
    }

    /** Debt to equity for one filed year, with no snapshot fallback. */
    public function debtToEquityForYear(?StockFinancial $year): ?float
    {
        if ($year === null || $year->total_debt === null || $year->total_equity === null || $year->total_equity <= 0) {
            return null;
        }

        return $year->total_debt / $year->total_equity;
    }

    /** Return on assets as a percentage, from the last filed year. */
    public function returnOnAssets(?StockFinancial $latest): ?float
    {
        return $latest === null ? null : $this->ratioPct($latest->net_income, $latest->total_assets);
    }

    /**
     * Price to book.
     *
     * Taken from the vendor, which computes it against the company's own
     * reported share count. This app has no shares-outstanding figure of its
     * own and must not invent one: dividing a market capitalisation by a lot
     * count rather than a share count would overstate the result by the lot
     * size, and by whatever else the guess got wrong.
     */
    public function priceToBook(?StockRef $ref): ?float
    {
        return $this->positiveOrNull($ref?->pb_ratio);
    }

    /**
     * Price to earnings.
     *
     * Recomputed from price and EPS when both are present, because those two
     * are visible on the page and a reader can check the division. The vendor
     * figure is the fallback for emiten with no stored EPS.
     */
    public function priceToEarnings(?StockRef $ref, ?StockPrice $price = null): ?float
    {
        $eps = $this->positiveOrNull($ref?->eps);
        $close = $price === null ? null : (float) $price->close_price;

        if ($eps !== null && $close !== null && $close > 0) {
            return $close / $eps;
        }

        return $this->positiveOrNull($ref?->pe_ratio);
    }

    /** Earnings per share, in rupiah. */
    public function earningsPerShare(?StockRef $ref, ?StockFinancial $latest = null): ?float
    {
        $stored = $this->positiveOrNull($ref?->eps);

        if ($stored !== null) {
            return $stored;
        }

        return $latest === null ? null : $this->positiveOrNull($latest->eps);
    }

    /**
     * Debt to equity, as a ratio.
     *
     * Negative equity yields null rather than a negative ratio: liabilities
     * exceeding assets is the worst reading there is, and a negative number
     * sorts as though it were the best.
     */
    public function debtToEquity(?StockRef $ref, ?StockFinancial $latest = null): ?float
    {
        if ($latest !== null && $latest->total_debt !== null && $latest->total_equity !== null && $latest->total_equity > 0) {
            return $latest->total_debt / $latest->total_equity;
        }

        if ($ref !== null && $ref->hasFundamentals() && (float) $ref->der > 0) {
            return (float) $ref->der;
        }

        return null;
    }

    /** Net profit as a share of revenue, as a percentage. */
    public function netMargin(?StockFinancial $latest): ?float
    {
        return $latest === null ? null : $this->ratioPct($latest->net_income, $latest->revenue);
    }

    /** Operating profit as a share of revenue, as a percentage. */
    public function operatingMargin(?StockFinancial $latest): ?float
    {
        return $latest === null ? null : $this->ratioPct($latest->operating_income, $latest->revenue);
    }

    /**
     * Whether net profit exceeds operating profit.
     *
     * Not an error, but worth a reader's attention: profit below the operating
     * line normally comes from one-off gains — an asset sale, a revaluation, a
     * tax credit — rather than from the business, so a company whose bottom
     * line beats its operating line is not as profitable as the headline
     * suggests. Persistently, it is the shape of earnings propped up by
     * non-operating items.
     */
    public function netExceedsOperating(?StockFinancial $latest): bool
    {
        if ($latest === null || $latest->net_income === null || $latest->operating_income === null) {
            return false;
        }

        // Only when the operating line is a profit. A company losing money at
        // the operating level and rescued below it is a different story, and
        // the comparison would flag it for the wrong reason.
        return $latest->operating_income > 0 && $latest->net_income > $latest->operating_income;
    }

    /**
     * A percentage, or null when the denominator makes one meaningless.
     *
     * The guard is here rather than at each call site so no formula in this
     * class can be written without it.
     */
    private function ratioPct(?float $numerator, ?float $denominator): ?float
    {
        if ($numerator === null || $denominator === null || $denominator == 0.0) {
            return null;
        }

        return ($numerator / $denominator) * 100;
    }

    /** A stored ratio, or null when it is absent, zero or nonsensical. */
    private function positiveOrNull(mixed $value): ?float
    {
        $value = (float) $value;

        return $value > 0 ? $value : null;
    }
}
