<?php

namespace App\Actions;

use App\Models\SiteCategory;
use App\Support\SiteApiClient;
use Illuminate\Http\Client\Response;

class PublishSiteCategoryToSite
{
    public function __construct(private SiteApiClient $client) {}

    public function handle(SiteCategory $category): Response
    {
        $category->loadMissing('site');

        $site = $category->site;

        if ($site === null || ! $site->is_active) {
            throw new \RuntimeException('Category site is missing or inactive.');
        }

        return $this->client->post(
            $site,
            'categories/',
            [
                'id' => $category->id,
                'name' => $category->name,
            ],
            'category-'.$category->id,
        );
    }
}
