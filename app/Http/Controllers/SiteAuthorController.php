<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Site;
use App\Support\AuthorCategoryScope;
use App\Support\RemoteRecord;
use App\Support\RemoteSiteGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteAuthorController extends Controller
{
    public function __construct(
        private RemoteSiteGateway $gateway,
        private AuthorCategoryScope $authorCategoryScope,
    ) {}

    public function index(Request $request, Site $site): JsonResponse
    {
        $this->authorize('viewAny', [Author::class, $site]);

        $fetch = $this->gateway->fetchAuthors($site);

        if (! $fetch->reachable) {
            return response()->json([
                'message' => $fetch->message,
            ], 503);
        }

        $authors = $fetch->items;

        if ($request->filled('site_category_id')) {
            $authors = $this->authorCategoryScope->filterAuthorsForCategory(
                $site,
                $authors,
                $request->integer('site_category_id'),
            );
        }

        return response()->json($authors->map(fn (RemoteRecord $author): array => [
            'id' => $author->int('id'),
            'name' => $author->displayLine(),
        ]));
    }
}
