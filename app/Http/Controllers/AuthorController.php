<?php

namespace App\Http\Controllers;

use App\Enums\RemoteResource;
use App\Http\Requests\StoreAuthorRequest;
use App\Http\Requests\UpdateAuthorRequest;
use App\Models\Author;
use App\Models\Site;
use App\Support\AuthorCategoryScope;
use App\Support\RemoteAuthorPayload;
use App\Support\RemoteIdMapper;
use App\Support\RemoteMediaUrlResolver;
use App\Support\RemoteRecord;
use App\Support\RemoteSendResult;
use App\Support\RemoteSiteGateway;
use App\Support\SiteAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class AuthorController extends Controller
{
    public function __construct(
        private RemoteSiteGateway $gateway,
        private SiteAccess $siteAccess,
        private RemoteAuthorPayload $authorPayload,
        private RemoteMediaUrlResolver $mediaUrlResolver,
        private RemoteIdMapper $idMapper,
        private AuthorCategoryScope $authorCategoryScope,
    ) {}

    public function index(Request $request, Site $site): View
    {
        $this->authorize('viewAny', [Author::class, $site]);

        $fetch = $this->gateway->fetchAuthors($site);
        $authors = $fetch->items;

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 15;
        $paginated = new LengthAwarePaginator(
            $authors->forPage($page, $perPage)->values(),
            $authors->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('authors.index', [
            'site' => $site,
            'authors' => $paginated,
            'remoteReachable' => $fetch->reachable,
            'remoteMessage' => $fetch->message,
        ]);
    }

    public function create(Site $site): View|RedirectResponse
    {
        $this->authorize('create', [Author::class, $site]);

        $fetch = $this->gateway->fetchAuthors($site);

        if (! $fetch->reachable) {
            return redirect()
                ->route('sites.authors.index', $site)
                ->with('error', $fetch->message);
        }

        return view('authors.create', [
            'site' => $site,
            'siteCategories' => $this->gateway->fetchCategories($site)->items,
        ]);
    }

    public function store(StoreAuthorRequest $request, Site $site): RedirectResponse
    {
        $id = random_int(100_000, 999_999);
        $payload = $this->authorPayload->fromRequest(
            $request->validated(),
            $id,
            profilePhoto: $request->file('profile_photo') instanceof UploadedFile
                ? $request->file('profile_photo')
                : null,
        );

        $result = $this->gateway->sendAuthor($site, $payload, $request->user()->id);

        $this->authorCategoryScope->syncForAuthor(
            $site,
            $id,
            $request->input('site_category_ids', []),
        );

        return $this->redirectWithResult($site, $result, 'Author added.');
    }

    public function edit(Site $site, string $authorId): View|RedirectResponse
    {
        $author = $this->findAuthor($site, $authorId);

        if ($author === null) {
            abort(404);
        }

        abort_unless($this->siteAccess->canManageAuthors(request()->user(), $site), 403);

        $categories = $this->gateway->fetchCategories($site);

        return view('authors.edit', [
            'site' => $site,
            'author' => $author,
            'resolvedProfilePhotoUrl' => $this->mediaUrlResolver->resolve(
                $author->string('profile_photo_url'),
                $site,
            ),
            'siteCategories' => $categories->items,
            'assignedCategoryIds' => $this->authorCategoryScope->selectedCategoryIdsForForm(
                $site,
                (int) $authorId,
                $categories->items,
            ),
        ]);
    }

    public function update(UpdateAuthorRequest $request, Site $site, string $authorId): RedirectResponse
    {
        $existing = $this->findAuthor($site, $authorId);

        $payload = $this->authorPayload->fromRequest(
            $request->validated(),
            (int) $authorId,
            $existing,
            $request->file('profile_photo') instanceof UploadedFile
                ? $request->file('profile_photo')
                : null,
        );

        $result = $this->gateway->sendAuthor($site, $payload, $request->user()->id);

        $this->authorCategoryScope->syncForAuthor(
            $site,
            (int) $authorId,
            $request->input('site_category_ids', []),
        );

        return $this->redirectWithResult($site, $result, 'Author updated.');
    }

    public function destroy(Request $request, Site $site, string $authorId): RedirectResponse
    {
        abort_unless($this->siteAccess->canManageAuthors($request->user(), $site), 403);

        $result = $this->gateway->deleteAuthor($site, $authorId, $request->user()->id);

        $this->authorCategoryScope->deleteForAuthor($site, (int) $authorId);

        return $this->redirectWithResult($site, $result, 'Author removed.');
    }

    private function findAuthor(Site $site, string $authorId): ?RemoteRecord
    {
        foreach ($this->authorLookupIds($site, $authorId) as $lookupId) {
            $fetch = $this->gateway->fetchAuthor($site, $lookupId);

            if ($fetch->items->isNotEmpty()) {
                return $fetch->items->first();
            }
        }

        $authors = $this->gateway->fetchAuthors($site);

        if (! $authors->reachable) {
            return null;
        }

        $lookupIds = $this->authorLookupIds($site, $authorId);

        return $authors->items->first(function (RemoteRecord $author) use ($lookupIds): bool {
            $authorId = $author->int('id');

            if ($authorId !== null && in_array((string) $authorId, $lookupIds, true)) {
                return true;
            }

            return in_array($author->routeKey(), $lookupIds, true);
        });
    }

    /**
     * @return list<string>
     */
    private function authorLookupIds(Site $site, string $authorId): array
    {
        $lookupIds = [$authorId];

        if (ctype_digit($authorId)) {
            $resolvedRemoteId = $this->idMapper->resolveRemoteId(
                $site,
                RemoteResource::Authors,
                (int) $authorId,
            );

            $resolved = (string) $resolvedRemoteId;

            if (! in_array($resolved, $lookupIds, true)) {
                $lookupIds[] = $resolved;
            }
        }

        return $lookupIds;
    }

    private function redirectWithResult(Site $site, RemoteSendResult $result, string $prefix): RedirectResponse
    {
        if ($result->successful) {
            return redirect()
                ->route('sites.authors.index', $site)
                ->with('status', $prefix.' Synced to remote site.');
        }

        if ($result->queued) {
            return redirect()
                ->route('sites.authors.index', $site)
                ->with('warning', $prefix.' '.$result->message);
        }

        return redirect()
            ->route('sites.authors.index', $site)
            ->with('error', $prefix.' '.$result->message);
    }
}
