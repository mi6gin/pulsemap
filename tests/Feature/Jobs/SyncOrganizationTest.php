<?php

namespace Tests\Feature\Jobs;

use App\Exceptions\YandexMapsParsingException;
use App\Jobs\SyncOrganization;
use App\Models\Organization;
use App\Models\Review;
use App\OrganizationStatus;
use App\Services\YandexMaps\ParsedOrganization;
use App\Services\YandexMaps\ParsedReview;
use App\Services\YandexMaps\YandexMapsParser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SyncOrganizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_successful_sync_persists_metrics_reviews_and_snapshot(): void
    {
        $organization = Organization::factory()->queued()->create();
        $parser = Mockery::mock(YandexMapsParser::class);
        $parser->shouldReceive('parse')
            ->once()
            ->with($organization->source_url, Mockery::type('Closure'))
            ->andReturn($this->parsedOrganization());

        (new SyncOrganization($organization->id, $organization->source_url))->handle($parser);

        $organization->refresh();
        $this->assertSame(OrganizationStatus::Complete, $organization->status);
        $this->assertSame(100, $organization->progress);
        $this->assertSame(321, $organization->ratings_count);
        $this->assertSame(2, $organization->reviews_count);
        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'source_review_id' => 'review-1',
            'author_name' => 'Алина',
        ]);
        $this->assertDatabaseCount('reviews', 2);
        $this->assertDatabaseHas('organization_snapshots', [
            'organization_id' => $organization->id,
            'reviews_count' => 2,
        ]);
    }

    public function test_repeated_sync_updates_reviews_without_duplicates_and_removes_stale_records(): void
    {
        $organization = Organization::factory()->create();
        Review::factory()->for($organization)->create([
            'source_review_id' => 'review-1',
            'text' => 'Старый текст',
        ]);
        Review::factory()->for($organization)->create(['source_review_id' => 'stale-review']);
        $parser = Mockery::mock(YandexMapsParser::class);
        $parser->shouldReceive('parse')->once()->andReturn($this->parsedOrganization());

        (new SyncOrganization($organization->id, $organization->source_url))->handle($parser);

        $this->assertDatabaseCount('reviews', 2);
        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'source_review_id' => 'review-1',
            'text' => 'Отличное место',
        ]);
        $this->assertDatabaseMissing('reviews', [
            'organization_id' => $organization->id,
            'source_review_id' => 'stale-review',
        ]);
    }

    public function test_terminal_failure_is_visible_on_organization(): void
    {
        $organization = Organization::factory()->queued()->create();
        $exception = new RuntimeException('Источник изменился');

        (new SyncOrganization($organization->id, $organization->source_url))->failed($exception);

        $organization->refresh();
        $this->assertSame(OrganizationStatus::Failed, $organization->status);
        $this->assertSame('Источник изменился', $organization->sync_error);
    }

    public function test_non_retryable_parser_failure_is_recorded_without_retrying_the_job(): void
    {
        $organization = Organization::factory()->queued()->create();
        $parser = Mockery::mock(YandexMapsParser::class);
        $parser->shouldReceive('parse')
            ->once()
            ->andThrow(new YandexMapsParsingException('Формат источника изменился'));

        (new SyncOrganization($organization->id, $organization->source_url))->handle($parser);

        $organization->refresh();
        $this->assertSame(OrganizationStatus::Failed, $organization->status);
        $this->assertSame('Формат источника изменился', $organization->sync_error);
    }

    public function test_results_are_discarded_when_source_url_changes_during_sync(): void
    {
        $organization = Organization::factory()->queued()->create();
        $sourceUrl = $organization->source_url;
        $newSourceUrl = 'https://yandex.ru/maps/org/another/9876543210/';
        $parser = Mockery::mock(YandexMapsParser::class);
        $parser->shouldReceive('parse')
            ->once()
            ->with($sourceUrl, Mockery::type('Closure'))
            ->andReturnUsing(function () use ($organization, $newSourceUrl): ParsedOrganization {
                Organization::query()
                    ->whereKey($organization->id)
                    ->update([
                        'source_url' => $newSourceUrl,
                        'status' => OrganizationStatus::Queued,
                        'progress' => 0,
                    ]);

                return $this->parsedOrganization();
            });

        (new SyncOrganization($organization->id, $sourceUrl))->handle($parser);

        $organization->refresh();
        $this->assertSame($newSourceUrl, $organization->source_url);
        $this->assertSame(OrganizationStatus::Queued, $organization->status);
        $this->assertSame(0, $organization->progress);
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('organization_snapshots', 0);
    }

    public function test_unique_lock_is_scoped_to_organization_and_source_url(): void
    {
        $organization = Organization::factory()->create();
        $first = new SyncOrganization($organization->id, 'https://yandex.ru/maps/org/first/1234567890/');
        $second = new SyncOrganization($organization->id, 'https://yandex.ru/maps/org/second/9876543210/');

        $this->assertNotSame($first->uniqueId(), $second->uniqueId());
        $this->assertSame($first->uniqueId(), (new SyncOrganization($organization->id, $first->sourceUrl))->uniqueId());
    }

    private function parsedOrganization(): ParsedOrganization
    {
        return new ParsedOrganization(
            externalId: '1234567890',
            canonicalUrl: 'https://yandex.ru/maps/org/test/1234567890/',
            name: 'Тестовая компания',
            rating: 4.7,
            ratingsCount: 321,
            reviewsCount: 2,
            reviews: [
                new ParsedReview(
                    id: 'review-1',
                    authorName: 'Алина',
                    authorAvatarUrl: null,
                    publishedAt: CarbonImmutable::parse('2026-09-01T10:00:00+03:00'),
                    text: 'Отличное место',
                    rating: 5,
                    updatedAt: CarbonImmutable::parse('2026-09-01T10:00:00+03:00'),
                ),
                new ParsedReview(
                    id: 'review-2',
                    authorName: 'Марат',
                    authorAvatarUrl: null,
                    publishedAt: CarbonImmutable::parse('2026-08-15T12:00:00+03:00'),
                    text: 'Всё хорошо',
                    rating: 4,
                    updatedAt: null,
                ),
            ],
        );
    }
}
