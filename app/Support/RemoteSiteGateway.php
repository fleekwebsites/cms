<?php

namespace App\Support;

use App\Enums\RemoteResource;
use App\Models\Site;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Throwable;

class RemoteSiteGateway
{
    public function __construct(
        private SiteApiClient $client,
        private PendingRemoteWriteQueue $queue,
        private RemoteIdMapper $idMapper,
    ) {}

    public function fetchArticles(Site $site, ?string $type = null, ?string $status = null): RemoteFetchResult
    {
        $query = array_filter([
            'type' => $type,
            'status' => $status,
        ], fn (mixed $value): bool => is_string($value) && $value !== '');

        return $this->fetchCollection($site, RemoteResource::Articles, $query);
    }

    public function fetchArticle(Site $site, string $uuid): RemoteFetchResult
    {
        return $this->fetchSingle($site, RemoteResource::Articles, $uuid);
    }

    public function fetchAuthors(Site $site): RemoteFetchResult
    {
        return $this->fetchCollection($site, RemoteResource::Authors);
    }

    public function fetchAuthor(Site $site, string $id): RemoteFetchResult
    {
        return $this->fetchSingle($site, RemoteResource::Authors, $id);
    }

    public function fetchCategories(Site $site): RemoteFetchResult
    {
        return $this->fetchCollection($site, RemoteResource::Categories);
    }

    public function fetchTopics(Site $site, int $categoryId): RemoteFetchResult
    {
        return $this->fetchCollection($site, RemoteResource::Topics, [
            'site_category_id' => $categoryId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendArticle(Site $site, array $payload, ?int $userId = null): RemoteSendResult
    {
        $uuid = isset($payload['uuid']) && is_string($payload['uuid']) ? trim($payload['uuid']) : '';

        if ($uuid === '') {
            return RemoteSendResult::failed('Article uuid is required.');
        }

        $payload = $this->idMapper->translateArticlePayload($site, $payload);

        return $this->send(
            $site,
            RemoteResource::Articles,
            $uuid,
            $payload,
            $uuid,
            $userId,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendAuthor(Site $site, array $payload, ?int $userId = null): RemoteSendResult
    {
        $id = $payload['id'] ?? null;

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return RemoteSendResult::failed('Author id is required.');
        }

        $key = (string) $id;

        return $this->send(
            $site,
            RemoteResource::Authors,
            $key,
            $payload,
            'author-'.$key,
            $userId,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendCategory(Site $site, array $payload, ?int $userId = null): RemoteSendResult
    {
        $id = $payload['id'] ?? null;

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return RemoteSendResult::failed('Category id is required.');
        }

        $key = (string) $id;

        return $this->send(
            $site,
            RemoteResource::Categories,
            $key,
            $payload,
            'category-'.$key,
            $userId,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendTopic(Site $site, array $payload, ?int $userId = null): RemoteSendResult
    {
        $id = $payload['id'] ?? null;

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return RemoteSendResult::failed('Topic id is required.');
        }

        $key = (string) $id;

        $payload = $this->idMapper->translateTopicPayload($site, $payload);

        return $this->send(
            $site,
            RemoteResource::Topics,
            $key,
            $payload,
            'topic-'.$key,
            $userId,
        );
    }

    public function deleteArticle(Site $site, string $uuid, ?int $userId = null): RemoteSendResult
    {
        return $this->delete($site, RemoteResource::Articles, $uuid, $uuid, $userId);
    }

    public function deleteAuthor(Site $site, string $id, ?int $userId = null): RemoteSendResult
    {
        return $this->delete($site, RemoteResource::Authors, $id, 'author-'.$id, $userId);
    }

    public function deleteCategory(Site $site, string $id, ?int $userId = null): RemoteSendResult
    {
        return $this->delete($site, RemoteResource::Categories, $id, 'category-'.$id, $userId);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function fetchCollection(Site $site, RemoteResource $resource, array $query = []): RemoteFetchResult
    {
        if (! $site->is_active) {
            return RemoteFetchResult::unreachable('This site is inactive.');
        }

        try {
            $response = $this->client->get($site, $resource->value.'/', $query);
        } catch (ConnectionException $exception) {
            return RemoteFetchResult::unreachable($exception->getMessage());
        } catch (Throwable $exception) {
            return RemoteFetchResult::unreachable($exception->getMessage());
        }

        if (! $response->successful()) {
            return RemoteFetchResult::unreachable($this->failureMessage($response));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return RemoteFetchResult::unreachable('The remote site returned an invalid response.');
        }

        $items = collect($payload)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): RemoteRecord => RemoteRecord::fromPayload($item))
            ->values();

        return RemoteFetchResult::success($items);
    }

    private function fetchSingle(Site $site, RemoteResource $resource, string $key): RemoteFetchResult
    {
        if (! $site->is_active) {
            return RemoteFetchResult::unreachable('This site is inactive.');
        }

        try {
            $response = $this->client->get($site, $resource->value.'/'.$key);
        } catch (ConnectionException $exception) {
            return RemoteFetchResult::unreachable($exception->getMessage());
        } catch (Throwable $exception) {
            return RemoteFetchResult::unreachable($exception->getMessage());
        }

        if ($response->status() === 404) {
            return RemoteFetchResult::success(collect());
        }

        if (! $response->successful()) {
            return RemoteFetchResult::unreachable($this->failureMessage($response));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return RemoteFetchResult::unreachable('The remote site returned an invalid response.');
        }

        return RemoteFetchResult::success(collect([RemoteRecord::fromPayload($payload)]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(
        Site $site,
        RemoteResource $resource,
        string $resourceKey,
        array $payload,
        string $idempotencyKey,
        ?int $userId,
    ): RemoteSendResult {
        if (! $site->is_active) {
            return RemoteSendResult::failed('This site is inactive.');
        }

        try {
            $response = $this->client->post($site, $resource->value.'/', $payload, $idempotencyKey);
        } catch (ConnectionException $exception) {
            $pending = $this->queue->store(
                $site,
                $resource,
                $resourceKey,
                $payload,
                $idempotencyKey,
                $userId,
                $exception->getMessage(),
            );

            return RemoteSendResult::queued($pending);
        } catch (Throwable $exception) {
            $pending = $this->queue->store(
                $site,
                $resource,
                $resourceKey,
                $payload,
                $idempotencyKey,
                $userId,
                $exception->getMessage(),
            );

            return RemoteSendResult::queued($pending);
        }

        if ($response->successful()) {
            $this->queue->markSent($site, $resource, $resourceKey);
            $remoteId = $this->idMapper->syncFromResponse($site, $resource, $payload, $response);

            return RemoteSendResult::delivered($response->status(), $remoteId);
        }

        if ($this->shouldQueue($response)) {
            $pending = $this->queue->store(
                $site,
                $resource,
                $resourceKey,
                $payload,
                $idempotencyKey,
                $userId,
                $this->failureMessage($response),
            );

            return RemoteSendResult::queued($pending, $this->failureMessage($response));
        }

        return RemoteSendResult::failed($this->failureMessage($response), $response->status());
    }

    private function delete(
        Site $site,
        RemoteResource $resource,
        string $resourceKey,
        string $idempotencyKey,
        ?int $userId,
    ): RemoteSendResult {
        if (! $site->is_active) {
            return RemoteSendResult::failed('This site is inactive.');
        }

        try {
            $response = $this->client->delete($site, $resource->value.'/'.$resourceKey, $idempotencyKey);
        } catch (ConnectionException $exception) {
            $pending = $this->queue->storeDelete(
                $site,
                $resource,
                $resourceKey,
                $idempotencyKey,
                $userId,
                $exception->getMessage(),
            );

            return RemoteSendResult::queued($pending);
        } catch (Throwable $exception) {
            $pending = $this->queue->storeDelete(
                $site,
                $resource,
                $resourceKey,
                $idempotencyKey,
                $userId,
                $exception->getMessage(),
            );

            return RemoteSendResult::queued($pending);
        }

        if ($response->successful() || $response->status() === 404) {
            $this->queue->markSent($site, $resource, $resourceKey);

            return RemoteSendResult::delivered($response->status());
        }

        if ($this->shouldQueue($response)) {
            $pending = $this->queue->storeDelete(
                $site,
                $resource,
                $resourceKey,
                $idempotencyKey,
                $userId,
                $this->failureMessage($response),
            );

            return RemoteSendResult::queued($pending, $this->failureMessage($response));
        }

        return RemoteSendResult::failed($this->failureMessage($response), $response->status());
    }

    private function shouldQueue(Response $response): bool
    {
        return $response->serverError() || $response->status() === 429;
    }

    private function failureMessage(Response $response): string
    {
        $json = $response->json();

        if (is_array($json) && isset($json['error']) && is_string($json['error'])) {
            return $json['error'];
        }

        return $response->reason() !== ''
            ? $response->reason()
            : 'The remote site rejected the request.';
    }

    /**
     * @return Collection<int, RemoteRecord>
     */
    public function mergePendingArticles(Site $site, Collection $remoteArticles): Collection
    {
        return $this->queue->mergePendingArticles($site, $remoteArticles);
    }
}
