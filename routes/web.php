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
    Route::post('articles/{article}/publications', [ArticlePublicationController::class, 'store'])
        ->name('articles.publications.store');
    Route::resource('articles', ArticleController::class);

    Route::get('sites/{site}/categories', [SiteCategoryController::class, 'index'])
        ->name('sites.categories.index');
    Route::post('sites/{site}/categories', [SiteCategoryController::class, 'store'])
        ->name('sites.categories.store');
    Route::delete('sites/{site}/categories/{category}', [SiteCategoryController::class, 'destroy'])
        ->name('sites.categories.destroy');

    Route::get('sites/{site}/authors', [SiteAuthorController::class, 'index'])
        ->name('sites.authors.index');

    Route::post('sites/{site}/api-key', [SiteController::class, 'regenerateApiKey'])
        ->name('sites.api-key.update');
    Route::patch('sites/{site}/status', [SiteController::class, 'toggleStatus'])
        ->name('sites.status.update');
    Route::resource('sites', SiteController::class);

    Route::resource('authors', AuthorController::class)->except(['show']);

    Route::resource('users', UserController::class)->except(['show']);
});
