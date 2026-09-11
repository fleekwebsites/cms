<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PendingRemoteWriteQueue;
use App\Support\SiteAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, SiteAccess $siteAccess, PendingRemoteWriteQueue $queue): View
    {
        $user = $request->user();
        $sites = $siteAccess->sitesFor($user);
        $siteIds = $sites->modelKeys();

        return view('dashboard', [
            'sites' => $sites,
            'siteCount' => $sites->count(),
            'userCount' => $user->isAdmin() ? User::query()->count() : null,
            'pendingWriteCount' => $queue->pendingCountForSites($siteIds),
            'recentPendingWrites' => $queue->recentForSites($siteIds),
        ]);
    }
}
