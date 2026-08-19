<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockPrice;
use App\Models\StockRef;
use App\Services\TechnicalAnalysisService;
use App\Support\Format;
use App\Support\MarketClock;
use App\Support\QuoteFreshness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Current figures for the emiten a page is displaying, so it can update
 * without being reloaded.
 *
 * The dashboard was static HTML: open it at nine and at three you are still
 * reading nine o'clock prices, because nothing on the page ever asked the
 * server again. That is a longer delay than the five-minute cron everyone
 * worries about, and unlike the cron it is unbounded.
 *
 * Whole rows come back, not just prices. The score and the trading plan are
 * derived from the price, so refreshing the number alone would leave a row
 * quoting a new price beside an entry band computed from the old one —
 * internally contradictory in a way a reader cannot see. Recomputing them
 * costs what rendering the page already costs.
 */
class LiveQuoteController extends Controller
{
    /** Beyond this the caller is not a dashboard, and one query cannot serve it. */
    private const MAX_TICKERS = 60;

    public function __construct(private readonly TechnicalAnalysisService $ta)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $requested = collect(explode(',', (string) $request->query('tickers', '')))
            ->map(fn (string $t) => trim($t))
            ->filter()
            ->map(fn (string $t) => StockRef::normalizeTicker($t))
            ->unique()
            ->take(self::MAX_TICKERS);

        $quotes = [];

        if ($requested->isNotEmpty()) {
            $prices = StockPrice::query()
                ->with('stockRef')
                ->whereIn('ticker', $requested->all())
                ->get();

            foreach ($prices as $price) {
                $quotes[$price->stockRef?->cleanTicker() ?? str_replace('.JK', '', $price->ticker)] = $this->quote($price);
            }
        }

        $freshness = QuoteFreshness::current();

        return response()->json([
            // Whether to keep polling at all. Nothing is written outside the
            // session, so a tab left open overnight should stop asking.
            'market_open' => $freshness['open'],
            // Already worded and coloured, so the badge the poller updates
            // says exactly what the badge Blade rendered would have said.
            'freshness' => $freshness,
            'server_time' => MarketClock::now()->toIso8601String(),
            'quotes' => $quotes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function quote(StockPrice $price): array
    {
        $change = $price->dailyChange();
        $changePct = $price->dailyChangePct();
        $score = $this->ta->calculateScore($price, $price->stockRef);
        $plan = $this->ta->buildTradingPlan($price);

        return [
            // Pre-formatted, so the browser cannot disagree with the server
            // about how a number is written — the page already had to render
            // these once, and re-implementing Indonesian number formatting in
            // JavaScript is how the two drift apart.
            'price' => number_format((float) $price->close_price),
            'change_pct' => $changePct === null ? null : ($changePct > 0 ? '+' : '').number_format($changePct, 2).'%',
            // The detail header quotes the move in rupiah as well as percent,
            // and says so in words when there is nothing to compare against.
            'change_line' => $change === null
                ? 'Perubahan harian belum tersedia'
                : ($change > 0 ? '+' : '').number_format($change).' ('.number_format((float) $changePct, 2).'%)',
            // Why there is no change, so the tooltip stays as informative
            // after a poll as it was when Blade rendered it.
            'change_issue' => $price->dailyChangeIssue(),
            // Colour classes are resolved here, not in the browser, for the
            // same reason the numbers are: the thresholds and the palette have
            // one definition in App\Support\Format, and a second copy in
            // JavaScript is how a red figure ends up under a green label.
            'change_class' => Format::changeTextClass($changePct),
            'flow' => $price->moneyFlow(),
            'flow_class' => Format::flowBadgeClass($price->moneyFlow()),
            'value_transaction' => 'Rp '.Format::compactRupiah((float) $price->value_transaction),
            'score' => $score->total(),
            'score_class' => Format::scoreBadgeClass($score->total()),
            'verdict' => $score->verdict(),
            'verdict_class' => Format::verdictTextClass($score->verdict()),
            'entry' => $plan->entryText(),
            'take_profit' => number_format($plan->takeProfit),
            'take_profit_pct' => $plan->takeProfitPct(),
            'stop_loss' => number_format($plan->stopLoss),
            'stop_loss_pct' => $plan->stopLossPct(),
            'updated_at' => $price->updated_at?->toIso8601String(),
        ];
    }
}
