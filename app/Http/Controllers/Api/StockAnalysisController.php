<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockPrice;
use App\Models\StockRef;
use App\Services\AiGenerativeService;
use App\Services\TechnicalAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Prophet" AI analysis button on the dashboard/scanner pages
 * (quant_scan.php's `action=analyze` AJAX handler): linear-regression
 * forecast plus a Gemini-generated narrative for one ticker.
 */
class StockAnalysisController extends Controller
{
    public function __construct(
        private readonly TechnicalAnalysisService $ta,
        private readonly AiGenerativeService $ai,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $ticker = StockRef::normalizeTicker((string) $request->input('ticker', ''));
        $price = StockPrice::query()->find($ticker);

        if ($price === null) {
            return response()->json(['status' => 'error', 'msg' => 'Data tidak ditemukan.']);
        }

        $forecast = $this->ta->prophetTrend($price->closeHistory());

        if ($forecast === null) {
            return response()->json(['status' => 'error', 'msg' => 'Data historis kurang.']);
        }

        $cleanTicker = str_replace('.JK', '', $ticker);
        $aiText = $this->ai->analyzeProphetForecast($cleanTicker, $forecast);

        return response()->json([
            'status' => 'success',
            'ticker' => $cleanTicker,
            'price' => number_format($forecast->lastPrice),
            'slope' => $forecast->slope,
            'trend' => $forecast->status,
            'forecast' => number_format($forecast->forecast),
            'ai_analysis' => $aiText,
        ]);
    }
}
