<?php

namespace App\Http\Controllers;

use App\Services\StockScreenerService;
use Illuminate\View\View;

/**
 * Titan Radar page: high-conviction volume-spike signals (titan_scan.php).
 */
class TitanScanController extends Controller
{
    public function __construct(private readonly StockScreenerService $screener)
    {
    }

    public function index(): View
    {
        return view('scanner.titan', [
            'signals' => $this->screener->titan(),
        ]);
    }
}
