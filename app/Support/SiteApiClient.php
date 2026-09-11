<?php

namespace App\Support;

use App\Models\Site;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SiteApiClient
{
    /**
     * @param  array<string, mixed>  $query
     */
    public function get(Site $site, string $resource, array $query = []): Response
    {
        return $this->request($site)
            ->get($site->apiEndpointFor($resource), $query);
    }

    public function post(Site $site, string $resource, array $payload, string $idempotencyKey): Response
    {
        return $this->request($site)
            ->withHeaders([
                'Idempotency-Key' => $idempotencyKey,
            ])
            ->post($site->apiEndpointFor($resource), $payload);
    }

    public function delete(Site $site, string $resource, string $idempotencyKey): Response
    {
        return $this->request($site)
            ->withHeaders([
                'Idempotency-Key' => $idempotencyKey,
            ])
            ->delete($site->apiEndpointFor($resource));
    }

    private function request(Site $site): PendingRequest
    {
        return Http::connectTimeout(config('cms.http.connect_timeout'))
            ->timeout(config('cms.http.timeout'))
            ->acceptJson()
            ->withHeaders([
                'X-API-Key' => $site->api_key,
            ]);
    }
}
