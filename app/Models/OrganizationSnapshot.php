<?php

namespace App\Models;

use Database\Factories\OrganizationSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id', 'captured_at', 'name', 'rating', 'ratings_count',
    'reviews_count', 'changes',
])]
class OrganizationSnapshot extends Model
{
    /** @use HasFactory<OrganizationSnapshotFactory> */
    use HasFactory;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'rating' => 'decimal:2',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'changes' => 'array',
        ];
    }
}
