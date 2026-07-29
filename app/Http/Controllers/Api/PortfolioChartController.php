<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PortfolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Portfolio-vs-IHSG performance series for the dashboard chart
 * (portfolio.php's `?action=get_chart_data` AJAX handler).
 */
class PortfolioChartController extends Controller
{
    public function __construct(private readonly PortfolioService $portfolio)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $range = $request->string('range', '1mo')->toString();

        return response()->json($this->portfolio->performanceSeries($request->user()->id, $range));
    }
}
