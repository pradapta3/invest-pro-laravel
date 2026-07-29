<?php

namespace App\Http\Controllers;

use App\Models\StockPrice;
use App\Models\StockRef;
use App\Services\TechnicalAnalysisService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Ghost Pattern" similarity search: Pearson-correlates a target ticker's
 * trailing price history against every other tradable stock's, surfacing
 * the closest chart-shape matches (similarity.php).
 */
class SimilarityController extends Controller
{
    private const MIN_HISTORY_POINTS = 10;

    private const MIN_CORRELATION = 0.60;

    private const MAX_MATCHES = 12;

    public function __construct(private readonly TechnicalAnalysisService $ta)
    {
    }

    public function show(Request $request): View
    {
        $ticker = StockRef::normalizeTicker($request->string('ticker', 'BBCA')->toString());

        $target = StockPrice::query()->find($ticker);
        $targetHistory = $target?->closeHistory() ?? [];

        $matches = collect();

        if (count($targetHistory) >= self::MIN_HISTORY_POINTS) {
            $candidates = StockPrice::query()
                ->with('stockRef')
                ->where('ticker', '!=', $ticker)
                ->where('volume', '>', 0)
                ->get();

            foreach ($candidates as $candidate) {
                $candidateHistory = $candidate->closeHistory();
                if (count($candidateHistory) < self::MIN_HISTORY_POINTS || $candidate->stockRef === null) {
                    continue;
                }

                $len = min(count($targetHistory), count($candidateHistory), 30);
                $correlation = $this->ta->pearsonCorrelation(
                    array_slice($targetHistory, -$len),
                    array_slice($candidateHistory, -$len),
                );

                if ($correlation > self::MIN_CORRELATION) {
                    $matches->push([
                        'price' => $candidate,
                        'similarity' => round($correlation * 100, 1),
                    ]);
                }
            }

            $matches = $matches->sortByDesc('similarity')->take(self::MAX_MATCHES)->values();
        }

        return view('similarity.index', [
            'ticker' => str_replace('.JK', '', $ticker),
            'target' => $target,
            'matches' => $matches,
        ]);
    }
}
