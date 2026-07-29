<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Standalone calculators (average-down helper, investment forecasting).
 * Pure client-side JS in the legacy tools.php — no backend logic to port.
 */
class ToolsController extends Controller
{
    public function index(): View
    {
        return view('tools.index');
    }
}
