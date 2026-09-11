<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Review */
class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->source_review_id,
            'author' => $this->author_name,
            'author_avatar_url' => $this->author_avatar_url,
            'published_at' => $this->published_at?->toIso8601String(),
            'text' => $this->text,
            'rating' => $this->rating,
        ];
    }
}
