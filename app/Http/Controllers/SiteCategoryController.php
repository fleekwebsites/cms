<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteCategoryRequest;
use App\Http\Requests\UpdateSiteCategoryRequest;
use App\Models\Site;
use App\Models\SiteCategory;
use App\Support\RemoteRecord;
use App\Support\RemoteSendResult;
use App\Support\RemoteSiteGateway;
use App\Support\SiteAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteCategoryController extends Controller
{
    public function __construct(
        private RemoteSiteGateway $gateway,
        private SiteAccess $siteAccess,
    ) {}

    public function index(Site $site): JsonResponse
    {
        $this->authorize('viewAny', [SiteCategory::class, $site]);

        $fetch = $this->gateway->fetchCategories($site);

        if (! $fetch->reachable) {
            return response()->json([
                'message' => $fetch->message,
            ], 503);
        }

        return response()->json($fetch->items->map(fn (RemoteRecord $category): array => [
            'id' => $category->int('id'),
            'name' => $category->string('name'),
        ]));
    }

    public function manage(Site $site): View
    {
        $this->authorize('viewAny', [SiteCategory::class, $site]);

        $fetch = $this->gateway->fetchCategories($site);

        return view('sites.categories', [
            'site' => $site,
            'categories' => $fetch->items,
            'remoteReachable' => $fetch->reachable,
            'remoteMessage' => $fetch->message,
        ]);
    }

    public function store(StoreSiteCategoryRequest $request, Site $site): RedirectResponse|JsonResponse
    {
        $id = random_int(100_000, 999_999);
        $payload = [
            'id' => $id,
            'name' => $request->string('name')->toString(),
        ];

        $result = $this->gateway->sendCategory($site, $payload, $request->user()->id);

        if ($request->expectsJson() || $request->ajax() || $request->isJson()) {
            if ($result->successful || $result->queued) {
                return response()->json([
                    'id' => $result->remoteId ?? $id,
                    'client_id' => $id,
                    'name' => $payload['name'],
                    'queued' => $result->queued,
                    'message' => $result->message,
                ], $result->queued ? 202 : 201);
            }

            return response()->json([
                'message' => $result->message,
            ], 422);
        }

        return $this->redirectWithResult($site, $result, 'Category added.');
    }

    public function update(UpdateSiteCategoryRequest $request, Site $site, string $categoryId): RedirectResponse
    {
        $payload = [
            'id' => (int) $categoryId,
            'name' => $request->string('name')->toString(),
        ];

        $result = $this->gateway->sendCategory($site, $payload, $request->user()->id);

        return $this->redirectWithResult($site, $result, 'Category updated.');
    }

    public function destroy(Request $request, Site $site, string $categoryId): RedirectResponse
    {
        abort_unless($this->siteAccess->canManageCategories($request->user(), $site), 403);

        $result = $this->gateway->deleteCategory($site, $categoryId, $request->user()->id);

        return $this->redirectWithResult($site, $result, 'Category removed.');
    }

    private function redirectWithResult(Site $site, RemoteSendResult $result, string $prefix): RedirectResponse
    {
        if ($result->successful) {
            return redirect()
                ->route('sites.categories.index', $site)
                ->with('status', $prefix.' Synced to remote site.');
        }

        if ($result->queued) {
            return redirect()
                ->route('sites.categories.index', $site)
                ->with('warning', $prefix.' '.$result->message);
        }

        return redirect()
            ->route('sites.categories.index', $site)
            ->with('error', $prefix.' '.$result->message);
    }
}
