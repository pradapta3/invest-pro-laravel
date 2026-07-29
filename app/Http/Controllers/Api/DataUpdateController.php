<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * On-demand "Update Now" trigger for the dashboard's Data Updater menu —
 * the manual equivalent of the legacy update_realtime.php /
 * update_market.php / update_fundamentals.php links, which each ran as a
 * long, streamed-output page load.
 *
 * These jobs loop over hundreds of external API calls and can take
 * minutes, so running one synchronously inside a web request would hang
 * the browser tab and risk a PHP/Apache timeout. Instead this spawns the
 * artisan command as a detached background process and returns
 * immediately; refresh the page after a minute or two to see results.
 * The commands only run automatically on a schedule if something is
 * actually invoking `php artisan schedule:run` (cron / Task Scheduler) —
 * this button does not require that to be set up.
 */
class DataUpdateController extends Controller
{
    private const JOBS = [
        'realtime' => ['command' => 'idx:update-realtime-quotes', 'label' => 'Update Realtime'],
        'market' => ['command' => 'idx:update-market-data', 'label' => 'Update Market (EOD)'],
        'fundamentals' => ['command' => 'idx:update-fundamentals', 'label' => 'Update Fundamental'],
        'sentiment' => ['command' => 'idx:update-news-sentiment', 'label' => 'Update News Sentiment'],
        'history' => ['command' => 'idx:backfill-price-history --years=2', 'label' => 'Backfill Riwayat 2 Tahun (untuk Backtest)'],
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

        $this->spawnDetached(self::JOBS[$key]['command'], $key);

        Log::info('Manual data update triggered', ['job' => $key]);

        return response()->json([
            'status' => 'ok',
            'message' => self::JOBS[$key]['label'].' dimulai di background. Muat ulang halaman dalam beberapa menit.',
        ]);
    }

    /**
     * Spawns `php artisan {$artisanCommand}` as a process genuinely
     * independent of this HTTP request. $artisanCommand may include
     * options (e.g. "idx:backfill-price-history --years=2") — each
     * whitespace-separated token becomes its own -ArgumentList entry, the
     * same way argv works; passing the whole string as one quoted token
     * would make Artisan see a single malformed argument instead of a
     * command name plus an option.
     *
     * Symfony Process's default start() pipes the child's stdout/stderr
     * back to this PHP process so they can be read later — but on
     * Windows, once this request finishes and Apache tears down its
     * worker, those pipe handles close and the child dies the instant it
     * tries to write anything (its own startup banner included) with a
     * broken-pipe error. PowerShell's Start-Process avoids that
     * entirely: it launches a truly separate process tree with its own
     * output redirected to a log file, unaffected by this request ending.
     */
    private function spawnDetached(string $artisanCommand, string $logKey): void
    {
        $phpPath = (new PhpExecutableFinder)->find() ?: 'php';
        $logDir = storage_path('logs');
        $outLog = "{$logDir}/update-{$logKey}.log";
        $errLog = "{$logDir}/update-{$logKey}.err.log";

        $psQuote = fn (string $value): string => "'".str_replace("'", "''", $value)."'";

        $arguments = array_merge([base_path('artisan')], preg_split('/\s+/', trim($artisanCommand)));
        $argumentList = implode(',', array_map($psQuote, $arguments));

        $psScript = sprintf(
            'Start-Process -FilePath %s -ArgumentList %s -WorkingDirectory %s -WindowStyle Hidden -RedirectStandardOutput %s -RedirectStandardError %s',
            $psQuote($phpPath),
            $argumentList,
            $psQuote(base_path()),
            $psQuote($outLog),
            $psQuote($errLog),
        );

        $handle = popen('powershell.exe -NoProfile -NonInteractive -Command '.escapeshellarg($psScript), 'r');
        if ($handle !== false) {
            pclose($handle);
        }
    }
}
