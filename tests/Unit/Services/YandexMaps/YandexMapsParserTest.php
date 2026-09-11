<?php

namespace Tests\Unit\Services\YandexMaps;

use App\Exceptions\YandexMapsParsingException;
use App\Services\YandexMaps\YandexMapsParser;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexMapsParserTest extends TestCase
{
    public function test_parses_metadata_and_paginated_reviews_from_embedded_page_data(): void
    {
        config()->set('services.yandex_maps.delay_min_ms', 0);
        config()->set('services.yandex_maps.delay_max_ms', 0);
        config()->set('services.yandex_maps.page_size', 1);
        Http::preventStrayRequests();
        Http::fake([
            'https://yandex.ru/maps/org/test/1234567890/' => Http::response($this->organizationHtml()),
            'https://yandex.ru/maps/org/test/1234567890/reviews/?page=1' => Http::response($this->organizationHtml([[
                'reviewId' => 'review-1',
                'author' => ['name' => 'Алина', 'avatarHref' => 'https://avatars.example/alina.jpg'],
                'updatedTime' => '2026-09-01T10:00:00+03:00',
                'text' => 'Отличное место',
                'rating' => 5,
            ]])),
            'https://yandex.ru/maps/org/test/1234567890/reviews/?page=2' => Http::response($this->organizationHtml([[
                'reviewId' => 'review-2',
                'author' => ['name' => 'Марат'],
                'createdTime' => 1788350400000,
                'text' => 'Всё хорошо',
                'rating' => 4,
            ]])),
        ]);
        $progress = [];

        $result = (new YandexMapsParser)->parse(
            'https://yandex.ru/maps/org/test/1234567890/',
            function (int $value) use (&$progress): void {
                $progress[] = $value;
            },
        );

        $this->assertSame('1234567890', $result->externalId);
        $this->assertSame('Тестовая компания', $result->name);
        $this->assertSame(4.7, $result->rating);
        $this->assertSame(321, $result->ratingsCount);
        $this->assertSame(2, $result->reviewsCount);
        $this->assertCount(2, $result->reviews);
        $this->assertSame('Алина', $result->reviews[0]->authorName);
        $this->assertNotEmpty($progress);
        Http::assertSentCount(3);
    }

    public function test_throws_clear_exception_when_reviews_shape_changes(): void
    {
        config()->set('services.yandex_maps.delay_min_ms', 0);
        config()->set('services.yandex_maps.delay_max_ms', 0);
        Http::preventStrayRequests();
        Http::fake([
            'https://yandex.ru/maps/org/test/1234567890/' => Http::response($this->organizationHtml()),
            'https://yandex.ru/maps/org/test/1234567890/reviews/?page=1' => Http::response($this->organizationHtml()),
        ]);

        $this->expectException(YandexMapsParsingException::class);
        $this->expectExceptionMessage('не найден список отзывов');

        (new YandexMapsParser)->parse('https://yandex.ru/maps/org/test/1234567890/');
    }

    /** @param list<array<string, mixed>> $reviews */
    private function organizationHtml(array $reviews = []): string
    {
        $metadata = json_encode([
            'business' => [
                'id' => '1234567890',
                'title' => 'Тестовая компания',
                'rating' => 4.7,
                'ratingCount' => 321,
                'reviewCount' => 2,
            ],
            'reviewResults' => ['reviews' => $reviews],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return '<!doctype html><html><head><title>Тестовая компания — Яндекс Карты</title></head><body>'
            .'<script type="application/json">'.$metadata.'</script>'
            .str_repeat('content ', 200).'</body></html>';
    }
}
