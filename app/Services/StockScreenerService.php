<?php

namespace App\Services;

use App\Models\StockPrice;
use App\ValueObjects\TitanSignal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every stock-screening strategy from the legacy app (quant_scan.php's
 * Magic Formula / Bandar Radar / Trend Runner / Pullback Sniper /
 * Undervalued tabs, titan_scan.php's Titan Volatility scanner, and the
 * BSJP / Top Picks / watchlist filters embedded in index.php), each as a
 * named method instead of copy-pasted array_filter()/usort() blocks with
 * drifted thresholds. All thresholds live in config/screener.php.
 */
class StockScreenerService
{
    public function __construct(private readonly TechnicalAnalysisService $ta)
    {
    }

    /**
     * The base tradable universe (close_price > min AND volume > 0) with
     * fundamentals eager-loaded, shared by every strategy below. Callers
     * screening several strategies at once (e.g. the Quant Scan page's
     * five tabs) should fetch this once and pass it in, mirroring how
     * quant_scan.php ran one query and derived five lists from it.
     */
    public function tradableUniverse(): Collection
    {
        return StockPrice::query()
            ->tradable(config('screener.baseline.min_price'))
            ->with('stockRef')
            ->get()
            ->filter(fn (StockPrice $price) => $price->stockRef !== null)
            ->values();
    }

    public function magicFormula(?Collection $universe = null): Collection
    {
        $cfg = config('screener.magic_formula');

        return ($universe ?? $this->tradableUniverse())
            ->filter(fn (StockPrice $p) => (float) $p->stockRef->roe > $cfg['min_roe']
                && (float) $p->stockRef->pe_ratio > $cfg['min_pe_ratio']
                && (float) $p->stockRef->pe_ratio < $cfg['max_pe_ratio'])
            ->sortByDesc(fn (StockPrice $p) => (float) $p->stockRef->roe)
            ->take($cfg['limit'])
            ->values();
    }

    public function bandarRadar(?Collection $universe = null): Collection
    {
        $cfg = config('screener.bandar_radar');

        return ($universe ?? $this->tradableUniverse())
            ->filter(fn (StockPrice $p) => $p->volumeSpikeRatio() > $cfg['min_volume_spike_ratio']
                && (float) $p->value_transaction > $cfg['min_transaction_value'])
            ->sortByDesc(fn (StockPrice $p) => $p->volumeSpikeRatio())
            ->values();
    }

    public function trendRunner(?Collection $universe = null): Collection
    {
        $cfg = config('screener.trend_runner');

        return ($universe ?? $this->tradableUniverse())
            ->filter(fn (StockPrice $p) => (float) $p->close_price > (float) $p->ma20
                && (float) $p->rsi_14 > $cfg['min_rsi']
                && (float) $p->rsi_14 < $cfg['max_rsi'])
            ->sortByDesc(fn (StockPrice $p) => (float) $p->rsi_14)
            ->values();
    }

    public function pullbackSniper(?Collection $universe = null): Collection
    {
        $cfg = config('screener.pullback_sniper');

        return ($universe ?? $this->tradableUniverse())
            ->filter(fn (StockPrice $p) => (float) $p->close_price > (float) $p->ma20
                && (float) $p->rsi_14 >= $cfg['min_rsi']
                && (float) $p->rsi_14 <= $cfg['max_rsi']
                && (float) $p->value_transaction > $cfg['min_transaction_value'])
            ->sortBy(fn (StockPrice $p) => (float) $p->rsi_14)
            ->values();
    }

    public function undervalued(?Collection $universe = null): Collection
    {
        $cfg = config('screener.undervalued');

        return ($universe ?? $this->tradableUniverse())
            ->filter(fn (StockPrice $p) => (float) $p->stockRef->roe > $cfg['min_roe']
                && (float) $p->stockRef->der < $cfg['max_der']
                && (float) $p->stockRef->pb_ratio > 0
                && (float) $p->stockRef->pb_ratio < $cfg['max_pb_ratio'])
            ->sortByDesc(fn (StockPrice $p) => (float) $p->stockRef->roe)
            ->values();
    }

    public function topPicks(?Collection $universe = null): Collection
    {
        $cfg = config('screener.top_picks');

        return ($universe ?? $this->tradableUniverse())
            ->filter(fn (StockPrice $p) => (float) $p->stockRef->roe > $cfg['min_roe']
                && (float) $p->close_price > (float) $p->ma20
                && ((float) $p->stockRef->pe_ratio < $cfg['max_pe_ratio'] || (float) $p->macd_hist > 0))
            ->values();
    }

    public function bsjp(?Collection $universe = null): Collection
    {
        $cfg = config('screener.bsjp');

        return ($universe ?? $this->tradableUniverse())
            ->filter(fn (StockPrice $p) => (float) $p->close_price > (float) $p->open_price
                && (float) $p->close_price >= ((float) $p->high_price * $cfg['min_close_vs_high_ratio'])
                && $p->volumeSpikeRatio() > $cfg['min_volume_spike_ratio']
                && (float) $p->value_transaction > $cfg['min_transaction_value'])
            ->sortByDesc(fn (StockPrice $p) => $p->volumeSpikeRatio())
            ->values();
    }

    public function valueInvesting(?int $limit = null): Collection
    {
        $cfg = config('screener.value_investing');
        $limit ??= $cfg['limit'];

        return $this->tradableUniverse()
            ->filter(fn (StockPrice $p) => (float) $p->stockRef->roe > $cfg['min_roe']
                && (float) $p->stockRef->pe_ratio > $cfg['min_pe_ratio']
                && (float) $p->stockRef->pe_ratio < $cfg['max_pe_ratio'])
            ->sortByDesc(fn (StockPrice $p) => (float) $p->stockRef->roe)
            ->take($limit)
            ->values();
    }

    /**
     * Per-user watchlist (user_watchlists), replacing the legacy app's
     * single shared stock_refs.is_watchlist column.
     */
    public function watchlist(int $userId): Collection
    {
        $tickers = DB::table('user_watchlists')->where('user_id', $userId)->pluck('ticker');

        return StockPrice::query()
            ->with('stockRef')
            ->whereIn('ticker', $tickers)
            ->get();
    }

    /**
     * Dashboard "All Stocks" / search filter — ticker or company name.
     */
    public function search(string $query = ''): Collection
    {
        $builder = StockPrice::query()->with('stockRef');

        if ($query !== '') {
            $builder->where(function ($q) use ($query) {
                $q->where('ticker', 'like', "%{$query}%")
                    ->orWhereHas('stockRef', fn ($r) => $r->where('nama_perusahaan', 'like', "%{$query}%"));
            });
        }

        return $builder->get();
    }

    /**
     * % of the tradable universe trading above its MA20 — the "market
     * mood" gauge on the dashboard header, replacing index.php's inline
     * breadth query.
     */
    public function marketBreadthPct(): int
    {
        $total = StockPrice::query()->where('close_price', '>', 0)->count();
        if ($total === 0) {
            return 50;
        }

        $uptrend = StockPrice::query()->where('close_price', '>', 0)
            ->whereColumn('close_price', '>', 'ma20')
            ->count();

        return (int) round(($uptrend / $total) * 100);
    }

    /**
     * Sector-grouped treemap data for the Market Heatmap page, replacing
     * heatmap.php's per-row query + PHP array building. See
     * config('screener.heatmap') for why the market-cap-vs-transaction-
     * value sizing decision is made once for the whole dataset.
     *
     * @return array<int, array{name: string, children: array<int, array{name: string, value: array{0: float, 1: float, 2: float, 3: float}}>}>
     */
    public function heatmapTreemap(): array
    {
        $cfg = config('screener.heatmap');

        $rows = StockPrice::query()
            ->tradable(config('screener.baseline.min_price'))
            ->with('stockRef')
            ->orderByDesc('value_transaction')
            ->get()
            ->filter(fn (StockPrice $p) => $p->stockRef !== null);

        $hasMarketCapData = $rows->contains(fn (StockPrice $p) => (float) $p->stockRef->market_cap > $cfg['market_cap_probe_threshold']);

        $bySector = [];

        foreach ($rows as $row) {
            $sector = $row->stockRef->sector ?: 'Others';
            $marketCap = (float) $row->stockRef->market_cap;
            $valueTransaction = (float) $row->value_transaction;
            $close = (float) $row->close_price;
            $prevClose = (float) $row->prev_close;
            $open = (float) $row->open_price;

            $basePrice = $prevClose > 0 ? $prevClose : $open;
            $changePct = $basePrice > 0 ? (($close - $basePrice) / $basePrice) * 100 : 0.0;

            $sizeMetric = $hasMarketCapData ? $marketCap : $valueTransaction;
            $minSize = $hasMarketCapData ? $cfg['min_market_cap'] : $cfg['min_transaction_value'];

            if ($sizeMetric < $minSize) {
                continue;
            }

            $bySector[$sector][] = [
                'name' => $row->stockRef->cleanTicker(),
                'value' => [$sizeMetric, round($changePct, 2), $close, $marketCap],
            ];
        }

        $tree = [];
        foreach ($bySector as $sector => $children) {
            $tree[] = [
                'name' => $sector,
                'children' => array_slice($children, 0, $cfg['max_per_sector']),
            ];
        }

        return $tree;
    }

    /**
     * Titan Volatility scanner: tiered volume-spike score with trend/flow/
     * momentum bonuses and an S/A/B tier classification, replacing
     * titan_scan.php's scoring loop.
     *
     * @return Collection<int, TitanSignal>
     */
    public function titan(): Collection
    {
        $cfg = config('screener.titan');

        $universe = StockPrice::query()
            ->tradable($cfg['min_price'])
            ->minTransactionValue($cfg['min_transaction_value'])
            ->with('stockRef')
            ->get();

        $signals = collect();

        foreach ($universe as $price) {
            $ratio = $price->volumeSpikeRatio();
            $tier = collect($cfg['volume_spike_tiers'])->first(fn ($t) => $ratio >= $t['ratio']);

            if ($tier === null) {
                continue;
            }

            $score = $tier['points'];
            $tags = array_filter([$tier['tag']]);

            if ((float) $price->close_price > (float) $price->ma20) {
                $score += $cfg['trend_points'];
            }
            if ((float) $price->close_price > (float) $price->vwap) {
                $score += $cfg['vwap_points'];
            }
            if ($price->is_breakout) {
                $score += $cfg['breakout_points'];
                $tags[] = 'BREAKOUT';
            }
            if ((float) $price->macd_hist > 0) {
                $score += $cfg['macd_points'];
            }
            if ((float) $price->rsi_14 >= $cfg['rsi_min'] && (float) $price->rsi_14 <= $cfg['rsi_max']) {
                $score += $cfg['rsi_points'];
            }

            if ($score < $cfg['qualify_score']) {
                continue;
            }

            $tierLabel = match (true) {
                $score >= $cfg['tier_s_score'] => 'S',
                $score >= $cfg['tier_a_score'] => 'A',
                default => 'B',
            };

            $signals->push(new TitanSignal(
                price: $price,
                score: $score,
                tier: $tierLabel,
                tags: array_values($tags),
                volumeSpikeRatio: $ratio,
                plan: $this->ta->buildTradingPlan($price, 'titan'),
            ));
        }

        return $signals->sortByDesc(fn (TitanSignal $s) => $s->score)
            ->take($cfg['result_limit'])
            ->values();
    }
}
