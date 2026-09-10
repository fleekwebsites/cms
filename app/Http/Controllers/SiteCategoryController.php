<?php

namespace App\Http\Controllers;

use App\Actions\PublishSiteCategoryToSite;
use App\Http\Requests\StoreSiteCategoryRequest;
use App\Models\Site;
use App\Models\SiteCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class SiteCategoryController extends Controller
{
    public function index(Site $site): JsonResponse
    {
        $this->authorize('viewAny', [SiteCategory::class, $site]);

        $categories = $site->categories()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);

        return response()->json($categories);
    }

    public function store(StoreSiteCategoryRequest $request, Site $site, PublishSiteCategoryToSite $publisher): RedirectResponse
    {
        $category = $site->categories()->create($request->validated());

        $status = 'Category added.';

        try {
            $response = $publisher->handle($category);

            if ($response->successful()) {
                $status .= ' Synced to site.';
            } else {
                $status .= ' Site sync failed: '.$response->reason();
            }
        } catch (\RuntimeException $exception) {
            $status .= ' '.$exception->getMessage();
        }

        return redirect()
            ->route('sites.show', $site)
            ->with('status', $status);
    }

    public function destroy(Site $site, SiteCategory $category): RedirectResponse
    {
        abort_unless($category->site_id === $site->id, 404);

        $this->authorize('delete', $category);

        $category->delete();

        return redirect()
            ->route('sites.show', $site)
            ->with('status', 'Category removed.');
    }
}
