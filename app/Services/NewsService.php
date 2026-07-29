<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use Throwable;

/**
 * IDX news RSS aggregation, replacing news.php's simplexml_load_file
 * substitute (a raw cURL fetch + simplexml_load_string, done to dodge
 * simplexml_load_file's flakiness with some feed hosts).
 */
class NewsService
{
    private const PLACEHOLDER_IMAGE = 'https://via.placeholder.com/300x180.png?text=No+Image';

    /**
     * @return array<int, array{source: string, color: string, title: string, link: string, date: int, desc: string, img: string}>
     */
    public function latestArticles(int $perFeedLimit = 6): array
    {
        $articles = [];

        foreach (config('services.idx_news.feeds') as $feed) {
            $xml = $this->fetchFeed($feed['url']);
            if ($xml === null) {
                continue;
            }

            $count = 0;
            foreach ($xml->channel->item as $item) {
                if ($count >= $perFeedLimit) {
                    break;
                }

                $articles[] = [
                    'source' => $feed['name'],
                    'color' => $feed['color'],
                    'title' => (string) $item->title,
                    'link' => (string) $item->link,
                    'date' => strtotime((string) $item->pubDate) ?: time(),
                    'desc' => strip_tags((string) $item->description),
                    'img' => $this->extractImage($item),
                ];
                $count++;
            }
        }

        return $articles;
    }

    /**
     * Fetches and parses one RSS feed. Public so SentimentAnalysisService
     * can reuse the same fetch/parse without duplicating the cURL setup.
     */
    public function fetchFeed(string $url): ?SimpleXMLElement
    {
        try {
            $response = Http::withHeaders(['User-Agent' => config('services.idx_news.user_agent')])
                ->timeout(10)
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('RSS feed fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body());
        libxml_clear_errors();

        return $xml ?: null;
    }

    private function extractImage(SimpleXMLElement $item): string
    {
        if (isset($item->enclosure) && isset($item->enclosure['url'])) {
            return (string) $item->enclosure['url'];
        }

        $namespaces = $item->getNamespaces(true);
        if (isset($namespaces['media'])) {
            $mediaContent = $item->children($namespaces['media'])->content;
            if ($mediaContent !== null && (string) $mediaContent->attributes()->url !== '') {
                return (string) $mediaContent->attributes()->url;
            }
        }

        if (preg_match('/<img[^>]+src="([^">]+)"/', (string) $item->description, $match)) {
            return $match[1];
        }

        return self::PLACEHOLDER_IMAGE;
    }
}
