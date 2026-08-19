<?php

namespace App\Console\Commands;

use App\Models\StockPrice;
use App\Services\TechnicalAnalysisService;
use Illuminate\Console\Command;

/**
 * What the AI score actually looks like across the exchange.
 *
 * The scoring rules can be reasoned about anywhere, but whether the ramp
 * endpoints in config('screener.score') are set sensibly is a question about
 * real IDX prices — how far above its MA20 a typical mover trades, what a
 * normal volume ratio is — and that can only be answered against a database
 * the ingest commands have actually filled. So this reports the distribution
 * rather than asserting anything: run it after idx:update-market and
 * idx:update-fundamentals and tune the endpoints against what comes back.
 *
 * What to look for:
 *  - "distinct scores" near the emiten count means the score can rank; a
 *    handful means it cannot, and the ramps are too wide or too narrow.
 *  - a component whose median sits at 0 or at its maximum is not
 *    discriminating — its endpoints are outside the range real data occupies.
 *  - the verdict split should not put almost everything in one band.
 */
class ScoreDistribution extends Command
{
    protected $signature = 'idx:score-distribution {--buckets=10 : Histogram rows}';

    protected $description = 'Report how the AI score is distributed across the exchange, to calibrate config/screener.php';

    public function __construct(private readonly TechnicalAnalysisService $ta)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $rows = StockPrice::query()->with('stockRef')->get();

        if ($rows->isEmpty()) {
            $this->components->error('No stock_prices rows. Run idx:update-market first.');

            return self::FAILURE;
        }

        $withIndicators = $rows->filter(fn (StockPrice $p) => $p->hasIndicators())->count();
        $withFundamentals = $rows->filter(fn (StockPrice $p) => $p->stockRef?->hasFundamentals() ?? false)->count();
        $withVwap = $rows->filter(fn (StockPrice $p) => (float) $p->vwap > 0)->count();
        $n = $rows->count();

        $this->components->info("Scoring {$n} emiten");
        $this->line(sprintf('  indicators computed : %d (%.0f%%)  <- idx:update-market', $withIndicators, $withIndicators / $n * 100));
        $this->line(sprintf('  fundamentals loaded : %d (%.0f%%)  <- idx:update-fundamentals', $withFundamentals, $withFundamentals / $n * 100));
        $this->line(sprintf('  VWAP collected      : %d (%.0f%%)  <- idx:update-realtime-quotes', $withVwap, $withVwap / $n * 100));

        if ($withIndicators < $n) {
            $this->newLine();
            $this->components->warn('Rows without indicators score zero on every technical rule. Anything below 100% makes the numbers here pessimistic, not wrong.');
        }

        $totals = [];
        $parts = ['trend' => [], 'momentum' => [], 'flow' => [], 'fundamental' => []];
        $verdicts = [];

        foreach ($rows as $price) {
            $score = $this->ta->calculateScore($price, $price->stockRef);

            $totals[] = $score->total();
            $parts['trend'][] = $score->trend;
            $parts['momentum'][] = $score->momentum;
            $parts['flow'][] = $score->flow;
            $parts['fundamental'][] = $score->fundamental;
            $verdicts[$score->verdict()] = ($verdicts[$score->verdict()] ?? 0) + 1;
        }

        sort($totals);

        $this->newLine();
        $this->components->info('Total score');
        $this->line(sprintf(
            '  min %d   p25 %d   median %d   p75 %d   max %d',
            $totals[0],
            $totals[(int) ($n * 0.25)],
            $totals[intdiv($n, 2)],
            $totals[(int) ($n * 0.75)],
            $totals[$n - 1],
        ));
        $this->line(sprintf('  distinct scores: %d of %d emiten', count(array_unique($totals)), $n));

        $this->newLine();
        $this->components->info('Per component (max in brackets)');
        foreach (['trend' => 35, 'momentum' => 25, 'flow' => 25, 'fundamental' => 15] as $key => $max) {
            $vals = $parts[$key];
            sort($vals);
            $this->line(sprintf(
                '  %-12s [%2d]  median %5.1f   p75 %5.1f   max %5.1f   at zero: %d%%',
                $key,
                $max,
                $vals[intdiv($n, 2)],
                $vals[(int) ($n * 0.75)],
                $vals[$n - 1],
                (int) round(count(array_filter($vals, fn ($v) => $v <= 0.001)) / $n * 100),
            ));
        }

        $this->newLine();
        $this->components->info('Verdicts');
        foreach (['STRONG BUY', 'BUY', 'NEUTRAL', 'AVOID'] as $v) {
            $count = $verdicts[$v] ?? 0;
            $this->line(sprintf('  %-11s %4d  %s', $v, $count, str_repeat('#', (int) round($count / $n * 40))));
        }

        $buckets = max(2, (int) $this->option('buckets'));
        $width = 100 / $buckets;
        $histogram = array_fill(0, $buckets, 0);

        foreach ($totals as $t) {
            $histogram[min($buckets - 1, (int) ($t / $width))]++;
        }

        $this->newLine();
        $this->components->info('Histogram');
        foreach ($histogram as $i => $count) {
            $this->line(sprintf(
                '  %3d-%-3d %4d  %s',
                (int) ($i * $width),
                (int) (($i + 1) * $width) - 1,
                $count,
                str_repeat('#', (int) round($count / $n * 40)),
            ));
        }

        return self::SUCCESS;
    }
}
