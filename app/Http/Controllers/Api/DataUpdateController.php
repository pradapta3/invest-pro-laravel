<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * On-demand "Update Now" trigger for the dashboard's Data Updater menu —
 * the manual equivalent of the legacy update_realtime.php /
 * update_market.php / update_fundamentals.php links, which each ran as a
 * long, streamed-output page load.
 *
 * These jobs loop over hundreds of external API calls and can take
 * minutes, so running one synchronously inside a web request would hang
 * the browser tab and risk a timeout. Instead the artisan command is
 * pushed onto the queue and picked up by the dedicated queue worker;
 * refresh the page after a minute or two to see results.
 *
 * This used to shell out to PowerShell's Start-Process, which only ever
 * worked on the Windows box the app was originally developed on. In the
 * Linux container there is no powershell.exe, so popen() silently failed
 * and every button reported success while doing nothing at all.
 * Artisan::queue() needs no detachment tricks and works the same on any
 * host: the queue container is a separate long-lived process by
 * construction, so nothing dies when this request ends.
 */
class DataUpdateController extends Controller
{
    private const JOBS = [
        'realtime' => ['command' => 'idx:update-realtime-quotes', 'label' => 'Update Realtime'],
        'market' => ['command' => 'idx:update-market-data', 'label' => 'Update Market (EOD)'],
        'fundamentals' => ['command' => 'idx:update-fundamentals', 'label' => 'Update Fundamental'],
        'sentiment' => ['command' => 'idx:update-news-sentiment', 'label' => 'Update News Sentiment'],
        // Parameters are kept separate rather than appended to the command
        // string: Artisan::queue() takes them as an associative array, and a
        // bare "--years=2" token is read as a positional argument and rejected.
        'history' => ['command' => 'idx:backfill-price-history', 'parameters' => ['--years' => 2], 'label' => 'Backfill Riwayat 2 Tahun (untuk Backtest)'],
    ];

    public function jobs(): JsonResponse
    {
        return response()->json(collect(self::JOBS)->map(fn ($job, $key) => ['key' => $key, 'label' => $job['label']])->values());
    }

    public function __invoke(string $key): JsonResponse
    {
        if (! isset(self::JOBS[$key])) {
            return response()->json(['status' => 'error', 'message' => 'Unknown update job.'], 404);
        }

        Artisan::queue(self::JOBS[$key]['command'], self::JOBS[$key]['parameters'] ?? []);

        Log::info('Manual data update triggered', ['job' => $key]);

        return response()->json([
            'status' => 'ok',
            'message' => self::JOBS[$key]['label'].' dimulai di background. Muat ulang halaman dalam beberapa menit.',
        ]);
    }

}
