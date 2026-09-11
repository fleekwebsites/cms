<?php

namespace App\Actions;

use App\Models\Topic;
use App\Support\SiteApiClient;
use Illuminate\Http\Client\Response;

class PublishTopicToSite
{
    public function __construct(private SiteApiClient $client) {}

    public function handle(Topic $topic): Response
    {
        $topic->loadMissing('siteCategory.site');

        $site = $topic->siteCategory?->site;

        if ($site === null || ! $site->is_active) {
            throw new \InvalidArgumentException('Topic site is missing or inactive.');
        }

        return $this->client->post(
            $site,
            'topics/',
            [
                'id' => $topic->id,
                'site_category_id' => $topic->site_category_id,
                'name' => $topic->name,
            ],
            'topic-'.$topic->id,
        );
    }
}
