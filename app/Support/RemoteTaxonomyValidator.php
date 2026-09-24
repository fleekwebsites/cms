<?php

namespace App\Support;

use App\Enums\PendingRemoteWriteStatus;
use App\Enums\RemoteResource;
use App\Models\PendingRemoteWrite;
use App\Models\RemoteIdMapping;
use App\Models\Site;
use Illuminate\Validation\Validator;

class RemoteTaxonomyValidator
{
    public function __construct(
        private RemoteSiteGateway $gateway,
        private RemoteIdMapper $idMapper,
        private AuthorCategoryScope $authorCategoryScope,
    ) {}

    /**
     * @return array<int, string>
     */
    public function categoryIds(Site $site): array
    {
        $fetch = $this->gateway->fetchCategories($site);

        if (! $fetch->reachable) {
            return [];
        }

        return $fetch->items
            ->map(fn (RemoteRecord $record): ?int => $record->int('id'))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function authorIds(Site $site): array
    {
        $fetch = $this->gateway->fetchAuthors($site);

        if (! $fetch->reachable) {
            return [];
        }

        return $fetch->items
            ->map(fn (RemoteRecord $record): ?int => $record->int('id'))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function topicIds(Site $site, int $categoryId): array
    {
        $fetch = $this->gateway->fetchTopics($site, $categoryId);

        if (! $fetch->reachable) {
            return [];
        }

        return $this->remoteTopicIds($fetch);
    }

    /**
     * @return array<int, int>
     */
    public function acceptableTopicIds(Site $site, int $categoryId): array
    {
        $remoteIds = $this->topicIds($site, $categoryId);

        if ($remoteIds === []) {
            return [];
        }

        $clientIds = RemoteIdMapping::query()
            ->where('site_id', $site->id)
            ->where('resource', RemoteResource::Topics)
            ->whereIn('remote_id', $remoteIds)
            ->pluck('client_id')
            ->all();

        return array_values(array_unique([...$remoteIds, ...$clientIds]));
    }

    public function validateArticleTaxonomy(Validator $validator, Site $site): void
    {
        $categories = $this->gateway->fetchCategories($site);
        $authors = $this->gateway->fetchAuthors($site);

        if (! $categories->reachable || ! $authors->reachable) {
            $validator->errors()->add('remote', $categories->message ?? $authors->message ?? 'The remote site is unreachable.');

            return;
        }

        $categoryId = (int) $validator->getData()['site_category_id'];
        $authorId = (int) $validator->getData()['author_id'];
        $resolvedCategoryId = $this->idMapper->resolveRemoteId($site, RemoteResource::Categories, $categoryId);
        $resolvedAuthorId = $this->idMapper->resolveRemoteId($site, RemoteResource::Authors, $authorId);

        if (! in_array($resolvedCategoryId, $this->categoryIds($site), true)) {
            $validator->errors()->add('site_category_id', 'The selected category is invalid for this site.');
        }

        if (! in_array($resolvedAuthorId, $this->authorIds($site), true)) {
            $validator->errors()->add('author_id', 'The selected author is invalid for this site.');
        }

        if (! $this->authorCategoryScope->authorMayUseCategory($site, $authorId, $categoryId)) {
            $validator->errors()->add('author_id', 'The selected author is not allowed for this category.');
        }

        if (($validator->getData()['type'] ?? null) === 'blog') {
            $topicId = (int) ($validator->getData()['topic_id'] ?? 0);
            $resolvedTopicId = $this->idMapper->resolveRemoteId($site, RemoteResource::Topics, $topicId);

            if (! $this->topicIsValid($site, $resolvedCategoryId, $topicId, $resolvedTopicId)) {
                $validator->errors()->add('topic_id', 'The selected topic is invalid for this category.');
            }
        }
    }

    private function topicIsValid(Site $site, int $resolvedCategoryId, int $topicId, int $resolvedTopicId): bool
    {
        $acceptableIds = $this->acceptableTopicIds($site, $resolvedCategoryId);

        if ($acceptableIds !== []
            && (in_array($topicId, $acceptableIds, true) || in_array($resolvedTopicId, $acceptableIds, true))) {
            return true;
        }

        if ($this->topicExistsOnRemote($site, $resolvedCategoryId, $topicId, $resolvedTopicId)) {
            return true;
        }

        return PendingRemoteWrite::query()
            ->where('site_id', $site->id)
            ->where('resource', RemoteResource::Topics)
            ->where('status', PendingRemoteWriteStatus::Pending)
            ->where('resource_key', 'topic-'.$topicId)
            ->exists();
    }

    private function topicExistsOnRemote(
        Site $site,
        int $resolvedCategoryId,
        int $topicId,
        int $resolvedTopicId,
    ): bool {
        $fetch = $this->gateway->fetchAllTopics($site);

        if (! $fetch->reachable) {
            return false;
        }

        return $fetch->items->contains(function (RemoteRecord $record) use (
            $resolvedCategoryId,
            $topicId,
            $resolvedTopicId,
        ): bool {
            $remoteTopicId = $record->int('id');

            if ($remoteTopicId === null) {
                return false;
            }

            if ($remoteTopicId !== $topicId && $remoteTopicId !== $resolvedTopicId) {
                return false;
            }

            $topicCategoryId = $record->int('site_category_id');

            return $topicCategoryId === null || $topicCategoryId === $resolvedCategoryId;
        });
    }

    /**
     * @return array<int, int>
     */
    private function remoteTopicIds(RemoteFetchResult $fetch): array
    {
        return $fetch->items
            ->map(fn (RemoteRecord $record): ?int => $record->int('id'))
            ->filter()
            ->values()
            ->all();
    }
}
