<?php

namespace App\Services\YandexMaps;

class ParsedOrganization
{
    /** @param list<ParsedReview> $reviews */
    public function __construct(
        public readonly string $externalId,
        public readonly string $canonicalUrl,
        public readonly string $name,
        public readonly ?float $rating,
        public readonly int $ratingsCount,
        public readonly int $reviewsCount,
        public readonly array $reviews,
    ) {}
}
