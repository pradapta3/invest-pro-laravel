<?php

namespace App\Http\Controllers;

use App\Services\NewsService;
use Illuminate\View\View;

/**
 * IDX news feed aggregator (news.php).
 */
class NewsController extends Controller
{
    public function __construct(private readonly NewsService $news)
    {
    }

    public function index(): View
    {
        return view('news.index', [
            'articles' => $this->news->latestArticles(),
        ]);
    }
}
