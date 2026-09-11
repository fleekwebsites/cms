<?php

namespace App\Support;

use App\Enums\RemoteResource;
use App\Models\Site;
use Illuminate\Validation\Validator;

class RemoteTaxonomyValidator
{
    public function __construct(
        private RemoteSiteGateway $gateway,
        private RemoteIdMapper $idMapper,
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
     * @return array<int, string>
     */
    public function topicIds(Site $site, int $categoryId): array
    {
        $fetch = $this->gateway->fetchTopics($site, $categoryId);

        if (! $fetch->reachable) {
            return [];
        }

        return $fetch->items
            ->map(fn (RemoteRecord $record): ?int => $record->int('id'))
            ->filter()
            ->values()
            ->all();
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

        if (($validator->getData()['type'] ?? null) === 'blog') {
            $topicId = (int) ($validator->getData()['topic_id'] ?? 0);
            $resolvedTopicId = $this->idMapper->resolveRemoteId($site, RemoteResource::Topics, $topicId);

            if (! in_array($resolvedTopicId, $this->topicIds($site, $resolvedCategoryId), true)) {
                $validator->errors()->add('topic_id', 'The selected topic is invalid for this category.');
            }
        }
    }
}
