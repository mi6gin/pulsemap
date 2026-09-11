<?php

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
class OrganizationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_url' => $this->source_url,
            'canonical_url' => $this->canonical_url,
            'name' => $this->name,
            'rating' => $this->rating === null ? null : (float) $this->rating,
            'ratings_count' => $this->ratings_count,
            'reviews_count' => $this->reviews_count,
            'stored_reviews_count' => $this->whenCounted('reviews'),
            'status' => $this->status->value,
            'progress' => $this->progress,
            'sync_error' => $this->sync_error,
            'sync_started_at' => $this->sync_started_at?->toIso8601String(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'history' => $this->whenLoaded('snapshots', fn (): array => $this->snapshots->map(fn ($snapshot): array => [
                'captured_at' => $snapshot->captured_at->toIso8601String(),
                'rating' => $snapshot->rating === null ? null : (float) $snapshot->rating,
                'ratings_count' => $snapshot->ratings_count,
                'reviews_count' => $snapshot->reviews_count,
                'changes' => $snapshot->changes,
            ])->all()),
        ];
    }
}
