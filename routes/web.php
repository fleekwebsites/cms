<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticleImageController;
use App\Http\Controllers\ArticlePublicationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

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

    Route::post('sites/{site}/api-key', [SiteController::class, 'regenerateApiKey'])
        ->name('sites.api-key.update');
    Route::patch('sites/{site}/status', [SiteController::class, 'toggleStatus'])
        ->name('sites.status.update');
    Route::resource('sites', SiteController::class);

    Route::resource('users', UserController::class)->except(['show']);
});
