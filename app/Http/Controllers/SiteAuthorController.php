<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

class SiteAuthorController extends Controller
{
    public function index(Site $site): JsonResponse
    {
        $this->authorize('viewAny', [Author::class, $site]);

        $authors = $site->authors()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'credentials']);

        return response()->json($authors->map(fn (Author $author): array => [
            'id' => $author->id,
            'name' => $author->displayLine(),
        ]));
    }
}
