<?php

namespace App\Http\Controllers;

use App\Actions\PublishAuthorToSite;
use App\Http\Requests\StoreAuthorRequest;
use App\Http\Requests\UpdateAuthorRequest;
use App\Models\Author;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AuthorController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Author::class);

        $authors = Author::query()
            ->with('site:id,name')
            ->withCount('articles')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return view('authors.index', ['authors' => $authors]);
    }

    public function create(): View
    {
        $this->authorize('create', Author::class);

        return view('authors.create', [
            'sites' => Site::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function store(StoreAuthorRequest $request, PublishAuthorToSite $publisher): RedirectResponse
    {
        $author = Author::query()->create($request->validated());

        $status = $this->syncAuthorToSite($author, $publisher, 'Author added.');

        return redirect()
            ->route('authors.index')
            ->with('status', $status);
    }

    public function edit(Author $author): View
    {
        $this->authorize('update', $author);

        $author->load('site:id,name');

        return view('authors.edit', ['author' => $author]);
    }

    public function update(UpdateAuthorRequest $request, Author $author, PublishAuthorToSite $publisher): RedirectResponse
    {
        $author->update($request->validated());

        $status = $this->syncAuthorToSite($author, $publisher, 'Author updated.');

        return redirect()
            ->route('authors.index')
            ->with('status', $status);
    }

    public function destroy(Author $author): RedirectResponse
    {
        $this->authorize('delete', $author);

        $author->delete();

        return redirect()
            ->route('authors.index')
            ->with('status', 'Author removed.');
    }

    private function syncAuthorToSite(Author $author, PublishAuthorToSite $publisher, string $successMessage): string
    {
        try {
            $response = $publisher->handle($author);
        } catch (\RuntimeException $exception) {
            return $successMessage.' '.$exception->getMessage();
        }

        if ($response->successful()) {
            return $successMessage.' Synced to site.';
        }

        return $successMessage.' Site sync failed: '.$response->reason();
    }
}
