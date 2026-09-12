<?php

namespace Tests\Unit\Services\YandexMaps;

use App\Exceptions\YandexMapsParsingException;
use App\Services\YandexMaps\YandexMapsParser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class YandexMapsParserTest extends TestCase
{
    public function test_parses_metadata_and_paginated_reviews_from_embedded_page_data(): void
    {
        config()->set('services.yandex_maps.delay_min_ms', 1);
        config()->set('services.yandex_maps.delay_max_ms', 1);
        config()->set('services.yandex_maps.page_size', 1);
        config()->set('services.yandex_maps.requests_per_minute', 100);
        RateLimiter::clear('yandex-maps:http-requests');
        Sleep::fake();
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
                'rating' => 0,
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
        $this->assertSame(0, $result->reviews[1]->rating);
        $this->assertNotEmpty($progress);
        $this->assertSame(3, RateLimiter::attempts('yandex-maps:http-requests'));
        Sleep::assertSleptTimes(1);
        Http::assertSentCount(3);
    }

    public function test_rejects_partial_paginated_results(): void
    {
        config()->set('services.yandex_maps.delay_min_ms', 0);
        config()->set('services.yandex_maps.delay_max_ms', 0);
        config()->set('services.yandex_maps.page_size', 1);
        Http::preventStrayRequests();
        Http::fake([
            'https://yandex.ru/maps/org/test/1234567890/' => Http::response($this->organizationHtml()),
            'https://yandex.ru/maps/org/test/1234567890/reviews/?page=1' => Http::response($this->organizationHtml([[
                'reviewId' => 'review-1',
                'author' => ['name' => 'Алина'],
                'text' => 'Отличное место',
                'rating' => 5,
            ]])),
            'https://yandex.ru/maps/org/test/1234567890/reviews/?page=2' => Http::response($this->organizationHtml()),
        ]);

        try {
            (new YandexMapsParser)->parse('https://yandex.ru/maps/org/test/1234567890/');
            $this->fail('Ожидалась ошибка неполной выдачи.');
        } catch (YandexMapsParsingException $exception) {
            $this->assertStringContainsString('неожиданно оборвалась', $exception->getMessage());
            $this->assertTrue($exception->isRetryable());
        }
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

    public function test_requires_exact_review_count_instead_of_silently_using_received_rows(): void
    {
        config()->set('services.yandex_maps.delay_min_ms', 0);
        config()->set('services.yandex_maps.delay_max_ms', 0);
        Http::preventStrayRequests();
        Http::fake([
            'https://yandex.ru/maps/org/test/1234567890/reviews/' => Http::response(
                $this->organizationHtml([], ['reviewCount' => null]),
            ),
        ]);

        $this->expectException(YandexMapsParsingException::class);
        $this->expectExceptionMessage('точные счётчики');

        (new YandexMapsParser)->parse('https://yandex.ru/maps/org/test/1234567890/reviews/');
    }

    public function test_loads_all_six_hundred_available_reviews(): void
    {
        config()->set('services.yandex_maps.delay_min_ms', 0);
        config()->set('services.yandex_maps.delay_max_ms', 0);
        config()->set('services.yandex_maps.requests_per_minute', 0);
        Http::preventStrayRequests();

        $responses = [
            'https://yandex.ru/maps/org/1234567890/' => Http::response(
                $this->organizationHtml([], ['reviewCount' => 600]),
            ),
        ];

        for ($page = 1; $page <= 12; $page++) {
            $reviews = [];
            for ($offset = 1; $offset <= 50; $offset++) {
                $number = (($page - 1) * 50) + $offset;
                $reviews[] = [
                    'reviewId' => 'review-'.$number,
                    'author' => ['name' => 'Автор '.$number],
                    'createdTime' => '2026-09-01T10:00:00+03:00',
                    'text' => 'Отзыв '.$number,
                    'rating' => ($number % 5) + 1,
                ];
            }

            $responses['https://yandex.ru/maps/org/1234567890/reviews/?page='.$page] = Http::response(
                $this->organizationHtml($reviews, ['reviewCount' => 600]),
            );
        }

        Http::fake($responses);

        $result = (new YandexMapsParser)->parse('https://yandex.ru/maps/org/1234567890/');

        $this->assertSame(600, $result->reviewsCount);
        $this->assertCount(600, $result->reviews);
        $this->assertSame('review-1', $result->reviews[0]->id);
        $this->assertSame('review-600', $result->reviews[599]->id);
        Http::assertSentCount(13);
    }

    public function test_normalizes_oid_link_and_uses_organization_name_instead_of_generic_map_title(): void
    {
        config()->set('services.yandex_maps.delay_min_ms', 0);
        config()->set('services.yandex_maps.delay_max_ms', 0);
        Http::preventStrayRequests();
        Http::fake([
            'https://yandex.ru/maps/213/moscow/?oid=1234567890' => Http::response(
                '<!doctype html><html><head><title>Карта Москвы — Яндекс Карты</title></head><body>'
                .str_repeat('map content ', 200).'</body></html>',
            ),
            'https://yandex.ru/maps/org/1234567890/reviews/?page=1' => Http::response(
                $this->organizationHtml([], ['reviewCount' => 0]),
            ),
        ]);

        $result = (new YandexMapsParser)->parse('https://yandex.ru/maps/213/moscow/?oid=1234567890');

        $this->assertSame('Тестовая компания', $result->name);
        $this->assertSame('https://yandex.ru/maps/org/1234567890/', $result->canonicalUrl);
        $this->assertSame(0, $result->reviewsCount);
        Http::assertSentCount(2);
    }

    /**
     * @param  list<array<string, mixed>>  $reviews
     * @param  array<string, mixed>  $metadataOverrides
     */
    private function organizationHtml(array $reviews = [], array $metadataOverrides = []): string
    {
        $metadata = json_encode([
            'business' => array_merge([
                'id' => '1234567890',
                'title' => 'Тестовая компания',
                'rating' => 4.7,
                'ratingCount' => 321,
                'reviewCount' => 2,
            ], $metadataOverrides),
            'reviewResults' => ['reviews' => $reviews],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return '<!doctype html><html><head><title>Тестовая компания — Яндекс Карты</title></head><body>'
            .'<script type="application/json">'.$metadata.'</script>'
            .str_repeat('content ', 200).'</body></html>';
    }
}
