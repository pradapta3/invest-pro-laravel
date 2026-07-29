<?php

namespace App\Http\Controllers;

use App\Models\StockRef;
use App\Services\MarketData\MarketDataService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Standalone month-by-month seasonality explorer for any ticker
 * (seasonality.php — the same aggregation also appears inline on the
 * stock detail page via MarketDataService::seasonalityStats()).
 */
class SeasonalityController extends Controller
{
    public function __construct(private readonly MarketDataService $marketData)
    {
    }

    public function show(Request $request): View
    {
        $ticker = StockRef::normalizeTicker($request->string('ticker', 'BBCA')->toString());
        $ref = StockRef::query()->find($ticker);

        $monthly = $this->marketData->monthlySeasonality($ticker);
        $stats = $this->marketData->seasonalityStats($monthly);

        return view('seasonality.index', [
            'ticker' => str_replace('.JK', '', $ticker),
            'ref' => $ref,
            'monthlyByYear' => $monthly,
            'seasonality' => $stats,
        ]);
    }
}
