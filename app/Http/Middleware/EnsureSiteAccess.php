<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteAccess
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $site = $request->route('site');

        abort_unless($site instanceof Site, 404);

        $user = $request->user();
        $user?->loadMissing('siteDelegations');

        abort_unless($user?->can('access', $site) ?? false, 403);

        return $next($request);
    }
}
