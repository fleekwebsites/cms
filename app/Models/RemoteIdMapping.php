<?php

namespace App\Models;

use App\Enums\RemoteResource;
use Database\Factories\RemoteIdMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'site_id',
    'resource',
    'client_id',
    'remote_id',
])]
class RemoteIdMapping extends Model
{
    /** @use HasFactory<RemoteIdMappingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resource' => RemoteResource::class,
            'client_id' => 'integer',
            'remote_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
