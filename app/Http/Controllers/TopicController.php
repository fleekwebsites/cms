<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTopicRequest;
use App\Models\Site;
use App\Models\Topic;
use App\Support\RemoteRecord;
use App\Support\RemoteSiteGateway;
use Illuminate\Http\JsonResponse;

class TopicController extends Controller
{
    public function __construct(private RemoteSiteGateway $gateway) {}

    public function index(Site $site, string $categoryId): JsonResponse
    {
        $this->authorize('viewAny', [Topic::class, $site]);

        $fetch = $this->gateway->fetchTopics($site, (int) $categoryId);

        if (! $fetch->reachable) {
            return response()->json([
                'message' => $fetch->message,
            ], 503);
        }

        return response()->json($fetch->items->map(fn (RemoteRecord $topic): array => [
            'id' => $topic->int('id'),
            'name' => $topic->string('name'),
        ]));
    }

    public function store(StoreTopicRequest $request, Site $site): JsonResponse
    {
        $id = random_int(100_000, 999_999);
        $payload = [
            'id' => $id,
            'site_category_id' => $request->integer('site_category_id'),
            'name' => $request->string('name')->toString(),
        ];

        $result = $this->gateway->sendTopic($site, $payload, $request->user()->id);

        if ($result->successful || $result->queued) {
            return response()->json([
                'id' => $result->remoteId ?? $id,
                'client_id' => $id,
                'name' => $payload['name'],
                'site_category_id' => $payload['site_category_id'],
                'queued' => $result->queued,
                'message' => $result->message,
            ], $result->queued ? 202 : 201);
        }

        return response()->json([
            'message' => $result->message,
        ], 422);
    }
}
