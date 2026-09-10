<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'api_endpoint', 'api_key', 'is_active'])]
#[Hidden(['api_key'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SiteCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(SiteCategory::class);
    }

    /**
     * @return HasMany<Author, $this>
     */
    public function authors(): HasMany
    {
        return $this->hasMany(Author::class);
    }

    /**
     * @return HasMany<Article, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /**
     * @return HasMany<PublishLog, $this>
     */
    public function publishLogs(): HasMany
    {
        return $this->hasMany(PublishLog::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function generateApiKey(): string
    {
        return 'cms_'.Str::lower(Str::random(40));
    }

    public function apiEndpointFor(string $resource): string
    {
        $endpoint = rtrim($this->api_endpoint, '/');

        if (str_ends_with($endpoint, '/content')) {
            return substr($endpoint, 0, -strlen('content')).$resource;
        }

        return $endpoint.'/'.$resource;
    }
}
