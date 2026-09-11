<?php

namespace App\Services\YandexMaps;

use Carbon\CarbonImmutable;

class ParsedReview
{
    public function __construct(
        public readonly string $id,
        public readonly string $authorName,
        public readonly ?string $authorAvatarUrl,
        public readonly ?CarbonImmutable $publishedAt,
        public readonly ?string $text,
        public readonly int $rating,
        public readonly ?CarbonImmutable $updatedAt,
    ) {}
}
