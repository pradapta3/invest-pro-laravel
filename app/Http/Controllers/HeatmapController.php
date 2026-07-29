<?php

namespace App\Http\Controllers;

use App\Services\StockScreenerService;
use Illuminate\View\View;

/**
 * Sector-grouped ECharts treemap of the whole market (heatmap.php). Data
 * is passed to the view as a plain PHP array and JSON-encoded once in the
 * Blade template, rather than the string concatenation the legacy page
 * did inline.
 */
class HeatmapController extends Controller
{
    public function __construct(private readonly StockScreenerService $screener)
    {
    }

    public function index(): View
    {
        $treemap = $this->screener->heatmapTreemap();
        $stockCount = collect($treemap)->sum(fn ($sector) => count($sector['children']));

        return view('heatmap.index', [
            'treemap' => $treemap,
            'stockCount' => $stockCount,
        ]);
    }
}
