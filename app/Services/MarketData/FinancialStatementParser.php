<?php

namespace App\Services\MarketData;

/**
 * Turns Yahoo's fundamentals-timeseries payload into one row per fiscal year.
 *
 * Kept apart from the HTTP client so the shape handling can be exercised
 * without a network call — which matters here, because the response is
 * awkward: each requested type comes back as its own object holding a sparse
 * array of {asOfDate, reportedValue: {raw}} entries, years are not aligned
 * across types, and a company that does not report a line simply has no entry
 * for it rather than a null. Anything missing stays null all the way to the
 * database.
 */
class FinancialStatementParser
{
    /**
     * Yahoo's type name for each of our columns. Requested as one batch and
     * matched back by suffix, since the response labels each series with the
     * type name it was asked for.
     */
    public const FIELD_TYPES = [
        'revenue' => 'annualTotalRevenue',
        'gross_profit' => 'annualGrossProfit',
        'operating_income' => 'annualOperatingIncome',
        'net_income' => 'annualNetIncome',
        'total_assets' => 'annualTotalAssets',
        'total_liabilities' => 'annualTotalLiabilitiesNetMinorityInterest',
        'total_equity' => 'annualStockholdersEquity',
        'total_debt' => 'annualTotalDebt',
        'cash_and_equivalents' => 'annualCashAndCashEquivalents',
        'operating_cash_flow' => 'annualOperatingCashFlow',
        'investing_cash_flow' => 'annualInvestingCashFlow',
        'financing_cash_flow' => 'annualFinancingCashFlow',
        'capital_expenditure' => 'annualCapitalExpenditure',
        'eps' => 'annualBasicEPS',
    ];

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return array_values(self::FIELD_TYPES);
    }

    /**
     * @param  array<int, array<string, mixed>>  $series  timeseries.result
     * @return array<int, array<string, mixed>> one row per fiscal year, newest first
     */
    public function parse(array $series): array
    {
        $byYear = [];

        foreach (self::FIELD_TYPES as $column => $type) {
            foreach ($this->entriesFor($series, $type) as $entry) {
                $asOf = $entry['asOfDate'] ?? null;
                $value = $entry['reportedValue']['raw'] ?? null;

                if ($asOf === null || $value === null || ! is_numeric($value)) {
                    continue;
                }

                $year = (int) substr((string) $asOf, 0, 4);

                if ($year < 1990 || $year > (int) date('Y') + 1) {
                    continue;
                }

                $byYear[$year]['fiscal_year'] = $year;
                // Several series can carry different period_end dates for the
                // same fiscal year; the latest is the one that closes it.
                $existing = $byYear[$year]['period_end'] ?? null;
                $byYear[$year]['period_end'] = $existing === null || $asOf > $existing ? $asOf : $existing;
                $byYear[$year][$column] = (float) $value;
            }
        }

        krsort($byYear);

        return array_values($byYear);
    }

    /**
     * The entries belonging to one type. The result array is a list of
     * objects, each keyed by the type it holds alongside metadata, so the
     * series is found by key rather than by position — Yahoo does not
     * guarantee the order matches the request.
     *
     * @param  array<int, array<string, mixed>>  $series
     * @return array<int, array<string, mixed>>
     */
    private function entriesFor(array $series, string $type): array
    {
        foreach ($series as $entry) {
            if (! is_array($entry) || ! isset($entry[$type]) || ! is_array($entry[$type])) {
                continue;
            }

            // Filter out the nulls Yahoo pads sparse series with.
            return array_values(array_filter($entry[$type], 'is_array'));
        }

        return [];
    }
}
