<?php

namespace App\Http\Controllers;

use App\Models\StockPrice;
use App\Models\StockRef;
use App\Services\MarketData\MarketDataService;
use App\Services\StockScreenerService;
use App\Services\TechnicalAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The main stock table + filter chips (All / BSJP / Top Picks /
 * Watchlist) from index.php. AJAX endpoints that used to live inline in
 * index.php (single-signal Telegram push, AI analyze) now live under
 * Api\* controllers instead.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly StockScreenerService $screener,
        private readonly TechnicalAnalysisService $ta,
        private readonly MarketDataService $marketData,
    ) {
    }

    public function index(Request $request): View
    {
        $filter = $request->string('f', 'all')->toString();
        $query = trim($request->string('q', '')->toString());

        $userId = $request->user()->id;

        $stocks = match ($filter) {
            'bsjp' => $this->screener->bsjp(),
            'stockpick' => $this->screener->topPicks(),
            'watchlist' => $this->screener->watchlist($userId),
            default => $this->screener->search(''),
        };

        if ($query !== '') {
            $needle = strtolower($query);
            $stocks = $stocks->filter(fn (StockPrice $p) => str_contains(strtolower($p->ticker), $needle)
                || str_contains(strtolower((string) $p->stockRef?->nama_perusahaan), $needle))
                ->values();
        }

        $stocks = $this->sortForFilter($stocks, $filter);

        $perPage = 20;
        $page = max(1, (int) $request->input('page', 1));
        $paginated = new LengthAwarePaginator(
            $stocks->forPage($page, $perPage)->values(),
            $stocks->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('dashboard.index', [
            'stocks' => $paginated,
            'filter' => $filter,
            'query' => $query,
            'ihsg' => $this->marketData->indexQuote(),
            'marketMood' => $this->marketMood(),
            'tickerTape' => StockPrice::query()->orderByDesc('volume')->limit(25)->get(),
            'ta' => $this->ta,
            'watchlistedTickers' => DB::table('user_watchlists')->where('user_id', $userId)->pluck('ticker')->all(),
        ]);
    }

    public function toggleWatchlist(Request $request, string $ticker): RedirectResponse
    {
        $ticker = StockRef::normalizeTicker($ticker);
        StockRef::query()->findOrFail($ticker);

        $userId = $request->user()->id;
        $exists = DB::table('user_watchlists')->where('user_id', $userId)->where('ticker', $ticker)->exists();

        if ($exists) {
            DB::table('user_watchlists')->where('user_id', $userId)->where('ticker', $ticker)->delete();
        } else {
            DB::table('user_watchlists')->insert([
                'user_id' => $userId,
                'ticker' => $ticker,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->back();
    }

    private function sortForFilter($stocks, string $filter)
    {
        if ($filter === 'all') {
            return $stocks->sortBy('ticker')->values();
        }

        if ($filter === 'bsjp') {
            $cfg = config('screener.bsjp');

            return $stocks->sortByDesc(function (StockPrice $p) use ($cfg) {
                $points = $p->volumeSpikeRatio() * $cfg['sort_weight_volume_spike'];
                $points += $p->is_breakout ? $cfg['sort_weight_breakout'] : 0;
                // moneyFlow(), not a hand-written close > vwap: unguarded, that
                // was true for every row whose VWAP had never been collected
                // (`close > 0`), floating the whole unprocessed exchange above
                // the names genuinely trading above their VWAP.
                $points += $p->moneyFlow() === 'AKUM' ? $cfg['sort_weight_above_vwap'] : 0;

                return $points;
            })->values();
        }

        // "stockpick" / "watchlist": sort by upside to the swing take-profit.
        return $stocks->sortByDesc(function (StockPrice $p) {
            $plan = $this->ta->buildTradingPlan($p, 'swing');
            $close = (float) $p->close_price;

            return $close > 0 ? (($plan->takeProfit - $close) / $close) * 100 : 0;
        })->values();
    }

    /**
     * @return array{pct: int, label: string, color: string, icon: string}
     */
    private function marketMood(): array
    {
        $pct = $this->screener->marketBreadthPct();

        return match (true) {
            $pct >= 70 => ['pct' => $pct, 'label' => 'Greed', 'color' => '#ef4444', 'icon' => 'fire'],
            $pct >= 55 => ['pct' => $pct, 'label' => 'Bullish', 'color' => '#22c55e', 'icon' => 'arrow-trend-up'],
            $pct <= 30 => ['pct' => $pct, 'label' => 'Fear', 'color' => '#3b82f6', 'icon' => 'snowflake'],
            $pct <= 45 => ['pct' => $pct, 'label' => 'Bearish', 'color' => '#64748b', 'icon' => 'cloud-rain'],
            default => ['pct' => $pct, 'label' => 'Neutral', 'color' => '#f59e0b', 'icon' => 'scale-balanced'],
        };
    }
}
