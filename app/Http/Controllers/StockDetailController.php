<?php

namespace App\Http\Controllers;

use App\Models\StockFinancial;
use App\Models\StockRef;
use App\Services\FinancialMetricsService;
use App\Services\MarketData\MarketDataService;
use App\Services\TechnicalAnalysisService;
use App\Support\QuoteFreshness;
use Illuminate\View\View;

/**
 * Full single-stock analysis page (detail.php): TradingView chart, AI
 * score breakdown, pivot levels, seasonality, sector peer comparison,
 * MA20 backtest and news sentiment.
 */
class StockDetailController extends Controller
{
    public function __construct(
        private readonly MarketDataService $marketData,
        private readonly TechnicalAnalysisService $ta,
        private readonly FinancialMetricsService $metrics,
    ) {
    }

    public function show(string $ticker): View
    {
        $normalized = StockRef::normalizeTicker($ticker);

        $ref = StockRef::query()->with('price')->find($normalized);
        abort_if(! $ref || ! $ref->price, 404, "Data {$ticker} tidak ditemukan.");

        $price = $ref->price;

        $peers = StockRef::query()
            ->with('price')
            ->where('sector', $ref->sector)
            ->where('ticker', '!=', $ref->ticker)
            ->orderByDesc('market_cap')
            ->limit(5)
            ->get()
            ->filter(fn (StockRef $r) => $r->price !== null)
            ->values();

        $score = $this->ta->calculateScore($price, $ref);
        $pivots = $this->ta->pivotPoints((float) $price->high_price, (float) $price->low_price, (float) $price->close_price);

        $seasonality = $this->marketData->monthlySeasonality($ref->ticker);
        $seasonalityStats = $this->marketData->seasonalityStats($seasonality);

        $dailyChart = $this->marketData->dailyChart($ref->ticker, '1y', '1d');
        $backtest = $this->ta->backtestMa20Strategy($dailyChart['close']);

        // Newest first for the table's column order; the view walks pairs to
        // work out year-on-year growth, so the ordering is load-bearing.
        $financials = StockFinancial::query()
            ->recentFor($ref->ticker, config('screener.financial_statement_years'))
            ->get();

        // The latest filed year, shared by the header ratios, the sector
        // table and the statements panel — so all three describe the same
        // period instead of each reaching for whichever figure was nearest.
        $latest = $financials->first();

        return view('stocks.detail', [
            'financials' => $financials,
            'latest' => $latest,
            'metrics' => $this->metrics,
            'ref' => $ref,
            'price' => $price,
            'peers' => $peers,
            'score' => $score,
            'pivots' => $pivots,
            'monthlyByYear' => $seasonality,
            'seasonality' => $seasonalityStats,
            'backtest' => $backtest,
            'ta' => $this->ta,
            // This page quotes a price too, and had the same problem the
            // dashboard did: left open it goes on showing the price it was
            // opened with, with nothing to say how old that is.
            'quoteFreshness' => QuoteFreshness::current(),
        ]);
    }
}
