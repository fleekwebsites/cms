<?php

namespace App\Actions;

use App\Models\Author;
use App\Support\SiteApiClient;
use Illuminate\Http\Client\Response;

class PublishAuthorToSite
{
    public function __construct(private SiteApiClient $client) {}

    public function handle(Author $author): Response
    {
        $author->loadMissing('site');

        $site = $author->site;

        if ($site === null || ! $site->is_active) {
            throw new \InvalidArgumentException('Author site is missing or inactive.');
        }

        return $this->client->post(
            $site,
            'authors/',
            [
                'id' => $author->id,
                'name' => $author->name,
                'credentials' => $author->credentials,
                'bio' => $author->bio,
            ],
            'author-'.$author->id,
        );
    }
}
