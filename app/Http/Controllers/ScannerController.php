<?php

namespace App\Http\Controllers;

use App\Services\StockScreenerService;
use Illuminate\View\View;

/**
 * Quant Scan page: Magic Formula, Bandar Radar, Trend Runner, Pullback
 * Sniper and Undervalued tabs, all derived from one shared tradable
 * universe fetch (quant_scan.php).
 */
class ScannerController extends Controller
{
    public function __construct(private readonly StockScreenerService $screener)
    {
    }

    public function quant(): View
    {
        $universe = $this->screener->tradableUniverse();

        return view('scanner.quant', [
            'magic' => $this->screener->magicFormula($universe),
            'bandar' => $this->screener->bandarRadar($universe),
            'trend' => $this->screener->trendRunner($universe),
            'pullback' => $this->screener->pullbackSniper($universe),
            'value' => $this->screener->undervalued($universe),
        ]);
    }
}
