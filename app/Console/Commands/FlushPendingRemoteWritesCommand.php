<?php

namespace App\Console\Commands;

use App\Enums\PendingRemoteWriteStatus;
use App\Models\PendingRemoteWrite;
use App\Support\PendingRemoteWritePayloadStore;
use App\Support\RemoteIdMapper;
use App\Support\SiteApiClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

class FlushPendingRemoteWritesCommand extends Command
{
    protected $signature = 'cms:flush-pending-writes {--limit=25 : Maximum writes to attempt per run}';

    protected $description = 'Retry pending remote writes queued while a site was unreachable';

    public function handle(SiteApiClient $client, RemoteIdMapper $idMapper): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $writes = PendingRemoteWrite::query()
            ->with('site')
            ->where('status', PendingRemoteWriteStatus::Pending)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($writes->isEmpty()) {
            $this->info('No pending remote writes.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($writes as $write) {
            $site = $write->site;

            if ($site === null || ! $site->is_active) {
                continue;
            }

            try {
                $resource = $write->resource->value;
                $payload = $write->resolvedPayload() ?? [];
                $response = $write->method === 'DELETE'
                    ? $client->delete($site, $resource.'/'.$write->resource_key, $write->idempotency_key)
                    : $client->post($site, $resource.'/', $payload, $write->idempotency_key);
            } catch (ConnectionException $exception) {
                $write->update([
                    'attempts' => $write->attempts + 1,
                    'last_attempted_at' => now(),
                    'error_message' => $exception->getMessage(),
                ]);

                continue;
            } catch (Throwable $exception) {
                $write->update([
                    'attempts' => $write->attempts + 1,
                    'last_attempted_at' => now(),
                    'error_message' => $exception->getMessage(),
                ]);

                continue;
            }

            $write->update([
                'attempts' => $write->attempts + 1,
                'last_attempted_at' => now(),
            ]);

            if ($response->successful() || ($write->method === 'DELETE' && $response->status() === 404)) {
                if ($write->method === 'POST' && is_array($payload)) {
                    $idMapper->syncFromResponse($site, $write->resource, $payload, $response);
                }

                app(PendingRemoteWritePayloadStore::class)->discard($write->payload_path);

                $write->update([
                    'status' => PendingRemoteWriteStatus::Sent,
                    'sent_at' => now(),
                    'error_message' => null,
                    'payload' => [],
                    'payload_path' => null,
                ]);
                $sent++;

                continue;
            }

            if ($response->serverError() || $response->status() === 429) {
                $write->update([
                    'error_message' => $response->reason(),
                ]);

                continue;
            }

            $write->update([
                'error_message' => $response->reason(),
            ]);
        }

        $this->info("Sent {$sent} of {$writes->count()} pending remote writes.");

        return self::SUCCESS;
    }
}
