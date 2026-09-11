<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAuthorRequest;
use App\Http\Requests\UpdateAuthorRequest;
use App\Models\Author;
use App\Models\Site;
use App\Support\RemoteRecord;
use App\Support\RemoteSendResult;
use App\Support\RemoteSiteGateway;
use App\Support\SiteAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class AuthorController extends Controller
{
    public function __construct(
        private RemoteSiteGateway $gateway,
        private SiteAccess $siteAccess,
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

        return view('authors.create', ['site' => $site]);
    }

    public function store(StoreAuthorRequest $request, Site $site): RedirectResponse
    {
        $id = random_int(100_000, 999_999);
        $payload = [
            'id' => $id,
            'name' => $request->string('name')->toString(),
            'credentials' => $request->string('credentials')->toString() ?: null,
            'bio' => $request->string('bio')->toString() ?: null,
        ];

        $result = $this->gateway->sendAuthor($site, $payload, $request->user()->id);

        return $this->redirectWithResult($site, $result, 'Author added.');
    }

    public function edit(Site $site, string $authorId): View|RedirectResponse
    {
        $author = $this->findAuthor($site, $authorId);

        if ($author === null) {
            abort(404);
        }

        abort_unless($this->siteAccess->canManageAuthors(request()->user(), $site), 403);

        return view('authors.edit', [
            'site' => $site,
            'author' => $author,
        ]);
    }

    public function update(UpdateAuthorRequest $request, Site $site, string $authorId): RedirectResponse
    {
        $payload = [
            'id' => (int) $authorId,
            'name' => $request->string('name')->toString(),
            'credentials' => $request->string('credentials')->toString() ?: null,
            'bio' => $request->string('bio')->toString() ?: null,
        ];

        $result = $this->gateway->sendAuthor($site, $payload, $request->user()->id);

        return $this->redirectWithResult($site, $result, 'Author updated.');
    }

    public function destroy(Request $request, Site $site, string $authorId): RedirectResponse
    {
        abort_unless($this->siteAccess->canManageAuthors($request->user(), $site), 403);

        $result = $this->gateway->deleteAuthor($site, $authorId, $request->user()->id);

        return $this->redirectWithResult($site, $result, 'Author removed.');
    }

    private function findAuthor(Site $site, string $authorId): ?RemoteRecord
    {
        $fetch = $this->gateway->fetchAuthor($site, $authorId);

        return $fetch->items->first();
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
