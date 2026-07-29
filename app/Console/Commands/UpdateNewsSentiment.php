<?php

namespace App\Console\Commands;

use App\Services\SentimentAnalysisService;
use Illuminate\Console\Command;

/**
 * Rescans IDX news feeds and refreshes per-ticker sentiment scores,
 * replacing update_sentiment.php.
 */
class UpdateNewsSentiment extends Command
{
    protected $signature = 'idx:update-news-sentiment';

    protected $description = 'Rescan IDX news feeds and refresh per-ticker sentiment scores';

    public function __construct(private readonly SentimentAnalysisService $sentiment)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->sentiment->rescan();
        $this->info("Scanned {$result['scanned']} headlines, matched {$result['matched']} tickers.");

        return self::SUCCESS;
    }
}
