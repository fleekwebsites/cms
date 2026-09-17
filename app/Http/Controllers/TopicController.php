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

        if ($result->queued) {
            return response()->json([
                'id' => $id,
                'client_id' => $id,
                'name' => $payload['name'],
                'site_category_id' => $request->integer('site_category_id'),
                'queued' => true,
                'message' => $result->message,
            ], 202);
        }

        if ($result->successful) {
            if ($result->remoteId === null) {
                $topicsUrl = $site->apiEndpointFor('topics/');
                $listFetch = $this->gateway->fetchTopics($site, $request->integer('site_category_id'));
                $hint = $listFetch->reachable
                    ? ' The remote site accepted the request, but did not return a topic id or list the new topic for this category.'
                    : ' The topics list endpoint is unreachable: '.($listFetch->message ?? 'Unknown error.');

                return response()->json([
                    'message' => 'The topic could not be confirmed on the remote site. Check POST and GET '.$topicsUrl.$hint,
                ], 422);
            }

            return response()->json([
                'id' => $result->remoteId,
                'client_id' => $id,
                'name' => $payload['name'],
                'site_category_id' => $request->integer('site_category_id'),
                'queued' => false,
            ], 201);
        }

        return response()->json([
            'message' => $result->message,
        ], 422);
    }
}
