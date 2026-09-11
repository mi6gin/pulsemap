<?php

namespace App\Models;

use App\OrganizationStatus;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'external_id', 'source_url', 'canonical_url', 'name', 'rating',
    'ratings_count', 'reviews_count', 'status', 'progress', 'sync_started_at',
    'last_synced_at', 'sync_error',
])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(OrganizationSnapshot::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'status' => OrganizationStatus::class,
            'progress' => 'integer',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'sync_started_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
