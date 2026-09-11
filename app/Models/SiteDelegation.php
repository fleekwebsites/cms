<?php

namespace App\Models;

use Database\Factories\SiteDelegationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'site_id',
    'can_write_articles',
    'can_manage_authors',
    'can_manage_categories',
])]
class SiteDelegation extends Model
{
    /** @use HasFactory<SiteDelegationFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'can_write_articles' => true,
        'can_manage_authors' => false,
        'can_manage_categories' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'can_write_articles' => 'boolean',
            'can_manage_authors' => 'boolean',
            'can_manage_categories' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
