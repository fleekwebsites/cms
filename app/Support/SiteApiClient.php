<?php

namespace App\Support;

use App\Models\Site;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SiteApiClient
{
    public function post(Site $site, string $resource, array $payload, string $idempotencyKey): Response
    {
        return Http::connectTimeout(config('cms.http.connect_timeout'))
            ->timeout(config('cms.http.timeout'))
            ->acceptJson()
            ->withHeaders([
                'X-API-Key' => $site->api_key,
                'Idempotency-Key' => $idempotencyKey,
            ])
            ->post($site->apiEndpointFor($resource), $payload);
    }
}
