<?php

namespace App\Support;

use App\Enums\RemoteResource;
use App\Models\AuthorCategoryAssignment;
use App\Models\Site;
use Illuminate\Support\Collection;

class AuthorCategoryScope
{
    public function __construct(private RemoteIdMapper $idMapper) {}

    /**
     * @param  array<int, int|string>  $categoryIdentifiers
     */
    public function syncForAuthor(Site $site, int $authorIdentifier, array $categoryIdentifiers): void
    {
        $authorId = $this->idMapper->resolveRemoteId($site, RemoteResource::Authors, $authorIdentifier);

        AuthorCategoryAssignment::query()
            ->where('site_id', $site->id)
            ->where('author_id', $authorId)
            ->delete();

        $categoryIds = collect($categoryIdentifiers)
            ->map(fn (int|string $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->map(fn (int $id): int => $this->idMapper->resolveRemoteId($site, RemoteResource::Categories, $id))
            ->unique()
            ->values();

        foreach ($categoryIds as $categoryId) {
            AuthorCategoryAssignment::query()->create([
                'site_id' => $site->id,
                'author_id' => $authorId,
                'category_id' => $categoryId,
            ]);
        }
    }

    public function deleteForAuthor(Site $site, int $authorIdentifier): void
    {
        $authorId = $this->idMapper->resolveRemoteId($site, RemoteResource::Authors, $authorIdentifier);

        AuthorCategoryAssignment::query()
            ->where('site_id', $site->id)
            ->where('author_id', $authorId)
            ->delete();
    }

    public function authorMayUseCategory(Site $site, int $authorIdentifier, int $categoryIdentifier): bool
    {
        if (! $this->siteUsesCategoryScopes($site)) {
            return true;
        }

        $authorId = $this->idMapper->resolveRemoteId($site, RemoteResource::Authors, $authorIdentifier);
        $categoryId = $this->idMapper->resolveRemoteId($site, RemoteResource::Categories, $categoryIdentifier);

        $authorCategories = AuthorCategoryAssignment::query()
            ->where('site_id', $site->id)
            ->where('author_id', $authorId)
            ->pluck('category_id');

        if ($authorCategories->isEmpty()) {
            return true;
        }

        return $authorCategories->containsStrict($categoryId);
    }

    /**
     * @param  Collection<int, RemoteRecord>  $authors
     * @return Collection<int, RemoteRecord>
     */
    public function filterAuthorsForCategory(Site $site, Collection $authors, int $categoryIdentifier): Collection
    {
        if (! $this->siteUsesCategoryScopes($site)) {
            return $authors;
        }

        return $authors
            ->filter(function (RemoteRecord $author) use ($site, $categoryIdentifier): bool {
                $authorId = $author->int('id');

                if ($authorId === null) {
                    return false;
                }

                return $this->authorMayUseCategory($site, $authorId, $categoryIdentifier);
            })
            ->values();
    }

    /**
     * Category ids as shown in CMS dropdowns (remote list ids).
     *
     * @return list<int>
     */
    public function categoryIdsForAuthorForm(Site $site, int $authorIdentifier): array
    {
        $authorId = $this->idMapper->resolveRemoteId($site, RemoteResource::Authors, $authorIdentifier);

        $storedCategoryIds = AuthorCategoryAssignment::query()
            ->where('site_id', $site->id)
            ->where('author_id', $authorId)
            ->pluck('category_id')
            ->all();

        if ($storedCategoryIds === []) {
            return [];
        }

        return $storedCategoryIds;
    }

    /**
     * @param  Collection<int, RemoteRecord>  $categories
     * @return list<int>
     */
    public function selectedCategoryIdsForForm(Site $site, int $authorIdentifier, Collection $categories): array
    {
        $storedCategoryIds = $this->categoryIdsForAuthorForm($site, $authorIdentifier);

        if ($storedCategoryIds === []) {
            return [];
        }

        return $categories
            ->map(fn (RemoteRecord $category): ?int => $category->int('id'))
            ->filter(function (?int $categoryId) use ($site, $storedCategoryIds): bool {
                if ($categoryId === null) {
                    return false;
                }

                $resolved = $this->idMapper->resolveRemoteId($site, RemoteResource::Categories, $categoryId);

                return in_array($resolved, $storedCategoryIds, true);
            })
            ->values()
            ->all();
    }

    private function siteUsesCategoryScopes(Site $site): bool
    {
        return AuthorCategoryAssignment::query()
            ->where('site_id', $site->id)
            ->exists();
    }
}
