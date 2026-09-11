<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Site;
use App\Support\RemoteRecord;
use App\Support\RemoteSiteGateway;
use Illuminate\Http\JsonResponse;

class SiteAuthorController extends Controller
{
    public function __construct(private RemoteSiteGateway $gateway) {}

    public function index(Site $site): JsonResponse
    {
        $this->authorize('viewAny', [Author::class, $site]);

        $fetch = $this->gateway->fetchAuthors($site);

        if (! $fetch->reachable) {
            return response()->json([
                'message' => $fetch->message,
            ], 503);
        }

        return response()->json($fetch->items->map(fn (RemoteRecord $author): array => [
            'id' => $author->int('id'),
            'name' => $author->displayLine(),
        ]));
    }
}
