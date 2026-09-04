<?php

namespace App\Http\Controllers;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Models\Article;
use App\Models\PublishLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $articles = Article::query()->visibleTo($user);

        return view('dashboard', [
            'blogCount' => (clone $articles)->where('type', ArticleType::Blog)->count(),
            'faqCount' => (clone $articles)->where('type', ArticleType::Faq)->count(),
            'publishedCount' => (clone $articles)->where('status', ArticleStatus::Published)->count(),
            'draftCount' => (clone $articles)->where('status', ArticleStatus::Draft)->count(),
            'siteCount' => $user->isAdmin() ? Site::query()->count() : null,
            'userCount' => $user->isAdmin() ? User::query()->count() : null,
            'recentArticles' => Article::query()
                ->visibleTo($user)
                ->with(['user:id,name', 'authorName:id,name'])
                ->latest()
                ->orderByDesc('id')
                ->limit(6)
                ->get(),
            'recentLogs' => PublishLog::query()
                ->with(['article:id,title,user_id', 'site:id,name'])
                ->whereHas('article', fn ($query) => $query->visibleTo($user))
                ->latest()
                ->orderByDesc('id')
                ->limit(6)
                ->get(),
        ]);
    }
}
