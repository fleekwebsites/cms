<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Site::class);

        abort_unless(request()->user()->isAdmin(), 403);

        $sites = Site::query()
            ->withCount('publishLogs')
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);

        return view('sites.index', ['sites' => $sites]);
    }

    public function create(): View
    {
        $this->authorize('create', Site::class);

        return view('sites.create');
    }

    public function store(StoreSiteRequest $request): RedirectResponse
    {
        $site = Site::query()->create([
            ...$request->safe()->only(['name', 'api_endpoint']),
            'is_active' => $request->boolean('is_active', true),
            'api_key' => Site::generateApiKey(),
        ]);

        return redirect()
            ->route('sites.show', $site)
            ->with('status', 'Site added. Use the generated API key on the receiving endpoint.');
    }

    public function show(Site $site): View
    {
        $this->authorize('view', $site);

        $site->load([
            'categories' => fn ($query) => $query->orderBy('name')->orderBy('id'),
            'publishLogs' => fn ($query) => $query->with('article:id,title')->latest()->orderByDesc('id')->limit(20),
        ]);

        return view('sites.show', ['site' => $site]);
    }

    public function edit(Site $site): View
    {
        $this->authorize('update', $site);

        return view('sites.edit', ['site' => $site]);
    }

    public function update(UpdateSiteRequest $request, Site $site): RedirectResponse
    {
        $site->update([
            ...$request->safe()->only(['name', 'api_endpoint']),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('sites.show', $site)
            ->with('status', 'Site updated.');
    }

    public function toggleStatus(Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $site->update([
            'is_active' => ! $site->is_active,
        ]);

        $message = $site->is_active
            ? 'Site activated.'
            : 'Site deactivated.';

        return redirect()
            ->back()
            ->with('status', $message);
    }

    public function destroy(Site $site): RedirectResponse
    {
        $this->authorize('delete', $site);

        $site->delete();

        return redirect()
            ->route('sites.index')
            ->with('status', 'Site removed.');
    }

    public function regenerateApiKey(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $site->update([
            'api_key' => Site::generateApiKey(),
        ]);

        return redirect()
            ->route('sites.show', $site)
            ->with('status', 'A new API key was generated. Update the receiving site.');
    }
}
