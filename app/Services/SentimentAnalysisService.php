<?php

namespace App\Services;

use App\Models\StockPrice;
use App\Models\StockRef;

/**
 * Lexicon-based headline sentiment scoring, replacing
 * update_sentiment.php's keyword miner. Each configured feed
 * (config('services.idx_news.sentiment_feeds')) is scanned; a headline is
 * matched to a ticker by its code or a cleaned company-name keyword, then
 * scored by counting positive vs. negative Indonesian finance-news words.
 * Matches accumulate per ticker across all feeds in one rescan, clamped
 * to keep the tinyint sentiment_score column sane.
 */
class SentimentAnalysisService
{
    private const POSITIVE_WORDS = [
        'laba', 'untung', 'naik', 'tumbuh', 'meroket', 'dividen', 'akuisisi', 'ekspansi',
        'tertinggi', 'bullish', 'hijau', 'menguat', 'rekor', 'proyek', 'kerjasama',
        'buyback', 'positif', 'optimis', 'surplus', 'bonanza',
    ];

    private const NEGATIVE_WORDS = [
        'rugi', 'turun', 'anjlok', 'merosot', 'utang', 'pailit', 'sanksi', 'gugat',
        'merah', 'bearish', 'melemah', 'koreksi', 'batal', 'phk', 'korupsi',
        'negatif', 'pesimis', 'suspend', 'delisting', 'default',
    ];

    private const SCORE_CLAMP = 5;

    public function __construct(private readonly NewsService $news)
    {
    }

    /**
     * @return array{scanned: int, matched: int}
     */
    public function rescan(): array
    {
        $keywordMap = $this->buildKeywordMap();

        StockPrice::query()->update(['sentiment_score' => 0, 'last_news_title' => null]);

        $scores = [];
        $lastTitle = [];
        $scanned = 0;

        foreach (config('services.idx_news.sentiment_feeds') as $feedUrl) {
            $xml = $this->news->fetchFeed($feedUrl);
            if ($xml === null) {
                continue;
            }

            foreach ($xml->channel->item as $item) {
                $scanned++;
                $title = (string) $item->title;
                $normalized = strtolower((string) preg_replace('/[^a-zA-Z0-9\s]/', ' ', $title));

                $ticker = $this->matchTicker($normalized, $keywordMap);
                if ($ticker === null) {
                    continue;
                }

                $net = $this->scoreText($normalized);
                if ($net === 0) {
                    continue;
                }

                $scores[$ticker] = max(-self::SCORE_CLAMP, min(self::SCORE_CLAMP, ($scores[$ticker] ?? 0) + $net));
                $lastTitle[$ticker] = $title;
            }
        }

        foreach ($scores as $ticker => $score) {
            StockPrice::query()->where('ticker', $ticker)->update([
                'sentiment_score' => $score,
                'last_news_title' => $lastTitle[$ticker],
            ]);
        }

        return ['scanned' => $scanned, 'matched' => count($scores)];
    }

    private function matchTicker(string $normalizedTitle, array $keywordMap): ?string
    {
        foreach ($keywordMap as $keyword => $ticker) {
            if (preg_match('/\b'.preg_quote($keyword, '/').'\b/', $normalizedTitle)) {
                return $ticker;
            }
        }

        return null;
    }

    private function scoreText(string $normalizedText): int
    {
        $score = 0;

        foreach (self::POSITIVE_WORDS as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/', $normalizedText)) {
                $score++;
            }
        }

        foreach (self::NEGATIVE_WORDS as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/', $normalizedText)) {
                $score--;
            }
        }

        return $score;
    }

    /**
     * @return array<string, string> lowercased keyword => ticker (e.g. "BBCA.JK")
     */
    private function buildKeywordMap(): array
    {
        $map = [];

        foreach (StockRef::query()->get(['ticker', 'nama_perusahaan']) as $ref) {
            $map[strtolower($ref->cleanTicker())] = $ref->ticker;

            $nameClean = trim(str_ireplace(['Tbk', 'Persero'], '', (string) $ref->nama_perusahaan));
            if (strlen($nameClean) > 5) {
                $map[strtolower($nameClean)] = $ref->ticker;
            }
        }

        return $map;
    }
}
