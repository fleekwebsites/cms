<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Site;
use App\Models\User;
use App\Support\SiteAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::transliterate(
                Str::lower($request->string('email')->toString()).'|'.$request->ip()
            ));
        });

        Route::bind('article', function (string $value): Article {
            $user = auth()->user();

            abort_unless($user, 404);

            $query = Article::query()
                ->visibleTo($user)
                ->whereKey($value);

            $site = request()->route('site');

            if ($site instanceof Site) {
                $query->where('site_id', $site->id);
            }

            return $query->firstOrFail();
        });

        View::composer('components.layouts.app', function ($view): void {
            $user = auth()->user();
            $site = request()->route('site');

            $view->with([
                'currentSite' => $site instanceof Site ? $site : null,
                'accessibleSites' => $user instanceof User
                    ? app(SiteAccess::class)->sitesFor($user)
                    : collect(),
            ]);
        });
    }
}
