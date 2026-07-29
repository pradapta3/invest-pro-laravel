<?php

namespace App\Http\Controllers;

use App\Services\Backtesting\BacktestEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * /backtest page: pick a strategy + date range, see whether it actually
 * had an edge historically (BacktestEngine).
 */
class BacktestController extends Controller
{
    public function __construct(private readonly BacktestEngine $engine)
    {
    }

    public function index(Request $request): View
    {
        $strategy = $request->string('strategy', 'bsjp')->toString();
        $from = Carbon::parse($request->string('from', now()->subYears(2)->toDateString())->toString());
        $to = Carbon::parse($request->string('to', now()->toDateString())->toString());
        $walkForward = $request->boolean('walk_forward');

        $result = null;
        $walkForwardReport = null;
        $error = null;

        if (! in_array($strategy, BacktestEngine::SUPPORTED_STRATEGIES, true)) {
            $error = "'{$strategy}' tidak bisa di-backtest — ".BacktestEngine::UNSUPPORTED_STRATEGY_REASON;
        } else {
            try {
                if ($walkForward) {
                    $walkForwardReport = $this->engine->runWalkForward($strategy, $from, $to);
                } else {
                    $result = $this->engine->run($strategy, $from, $to);
                }
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        return view('backtest.index', [
            'strategy' => $strategy,
            'strategies' => BacktestEngine::SUPPORTED_STRATEGIES,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'walkForward' => $walkForward,
            'result' => $result,
            'walkForwardReport' => $walkForwardReport,
            'error' => $error,
        ]);
    }
}
