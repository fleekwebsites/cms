<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticleImageController;
use App\Http\Controllers\ArticlePublicationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\SiteAuthorController;
use App\Http\Controllers\SiteCategoryController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('documentation', [DocumentationController::class, 'index'])->name('documentation.index');
Route::get('documentation/{page}', [DocumentationController::class, 'show'])
    ->where('page', '[a-z0-9\-]+')
    ->name('documentation.show');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::post('articles/images', ArticleImageController::class)->name('articles.images.store');

    Route::post('sites/{site}/api-key', [SiteController::class, 'regenerateApiKey'])
        ->name('sites.api-key.update');
    Route::patch('sites/{site}/status', [SiteController::class, 'toggleStatus'])
        ->name('sites.status.update');
    Route::resource('sites', SiteController::class);

    Route::middleware('site.access')->group(function () {
        Route::get('sites/{site}/authors/options', [SiteAuthorController::class, 'index'])
            ->name('sites.authors.options');
        Route::get('sites/{site}/categories/options', [SiteCategoryController::class, 'index'])
            ->name('sites.categories.options');
        Route::get('sites/{site}/categories/{categoryId}/topics', [TopicController::class, 'index'])
            ->where('categoryId', '[0-9]+')
            ->name('sites.topics.options');
        Route::post('sites/{site}/topics', [TopicController::class, 'store'])
            ->name('sites.topics.store');

        Route::post('sites/{site}/articles/{articleUuid}/completion', [ArticlePublicationController::class, 'complete'])
            ->where('articleUuid', '[a-f0-9\-]+')
            ->name('sites.articles.completion.store');
        Route::post('sites/{site}/articles/{articleUuid}/publications', [ArticlePublicationController::class, 'store'])
            ->where('articleUuid', '[a-f0-9\-]+')
            ->name('sites.articles.publications.store');

        Route::get('sites/{site}/articles', [ArticleController::class, 'index'])->name('sites.articles.index');
        Route::get('sites/{site}/articles/create', [ArticleController::class, 'create'])->name('sites.articles.create');
        Route::post('sites/{site}/articles', [ArticleController::class, 'store'])->name('sites.articles.store');
        Route::get('sites/{site}/articles/{articleUuid}', [ArticleController::class, 'show'])
            ->where('articleUuid', '[a-f0-9\-]+')
            ->name('sites.articles.show');
        Route::get('sites/{site}/articles/{articleUuid}/edit', [ArticleController::class, 'edit'])
            ->where('articleUuid', '[a-f0-9\-]+')
            ->name('sites.articles.edit');
        Route::put('sites/{site}/articles/{articleUuid}', [ArticleController::class, 'update'])
            ->where('articleUuid', '[a-f0-9\-]+')
            ->name('sites.articles.update');
        Route::delete('sites/{site}/articles/{articleUuid}', [ArticleController::class, 'destroy'])
            ->where('articleUuid', '[a-f0-9\-]+')
            ->name('sites.articles.destroy');

        Route::get('sites/{site}/authors', [AuthorController::class, 'index'])->name('sites.authors.index');
        Route::get('sites/{site}/authors/create', [AuthorController::class, 'create'])->name('sites.authors.create');
        Route::post('sites/{site}/authors', [AuthorController::class, 'store'])->name('sites.authors.store');
        Route::get('sites/{site}/authors/{authorId}/edit', [AuthorController::class, 'edit'])
            ->where('authorId', '[0-9]+')
            ->name('sites.authors.edit');
        Route::put('sites/{site}/authors/{authorId}', [AuthorController::class, 'update'])
            ->where('authorId', '[0-9]+')
            ->name('sites.authors.update');
        Route::delete('sites/{site}/authors/{authorId}', [AuthorController::class, 'destroy'])
            ->where('authorId', '[0-9]+')
            ->name('sites.authors.destroy');

        Route::get('sites/{site}/categories', [SiteCategoryController::class, 'manage'])
            ->name('sites.categories.index');
        Route::post('sites/{site}/categories', [SiteCategoryController::class, 'store'])
            ->name('sites.categories.store');
        Route::put('sites/{site}/categories/{categoryId}', [SiteCategoryController::class, 'update'])
            ->where('categoryId', '[0-9]+')
            ->name('sites.categories.update');
        Route::delete('sites/{site}/categories/{categoryId}', [SiteCategoryController::class, 'destroy'])
            ->where('categoryId', '[0-9]+')
            ->name('sites.categories.destroy');
    });

    Route::resource('users', UserController::class)->except(['show']);
});
