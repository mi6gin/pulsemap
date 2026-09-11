<?php

namespace App\Jobs;

use App\Exceptions\YandexMapsParsingException;
use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use App\Models\Review;
use App\OrganizationStatus;
use App\Services\YandexMaps\ParsedOrganization;
use App\Services\YandexMaps\ParsedReview;
use App\Services\YandexMaps\YandexMapsParser;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncOrganization implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $organizationId,
        public readonly string $sourceUrl,
    ) {}

    public function uniqueId(): string
    {
        return $this->organizationId.':'.hash('sha256', $this->sourceUrl);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60, 180];
    }

    /**
     * Execute the job.
     */
    public function handle(YandexMapsParser $parser): void
    {
        $started = Organization::query()
            ->whereKey($this->organizationId)
            ->where('source_url', $this->sourceUrl)
            ->update([
                'status' => OrganizationStatus::Syncing,
                'progress' => 2,
                'sync_started_at' => now(),
                'sync_error' => null,
            ]);

        if ($started === 0) {
            return;
        }

        try {
            $parsed = $parser->parse(
                $this->sourceUrl,
                function (int $progress): void {
                    Organization::query()
                        ->whereKey($this->organizationId)
                        ->where('source_url', $this->sourceUrl)
                        ->update(['progress' => $progress]);
                },
            );

            $this->persist($parsed);
        } catch (YandexMapsParsingException $exception) {
            if (! $exception->isRetryable()) {
                $this->updateCurrentSource([
                    'status' => OrganizationStatus::Failed,
                    'sync_error' => $exception->getMessage(),
                ]);

                Log::warning('Парсер Яндекс.Карт обнаружил несовместимый ответ источника.', [
                    'organization_id' => $this->organizationId,
                    'exception' => $exception,
                ]);

                return;
            }

            $this->updateCurrentSource([
                'status' => OrganizationStatus::Retrying,
                'sync_error' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $this->updateCurrentSource([
                'status' => OrganizationStatus::Retrying,
                'sync_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $organization = Organization::query()->find($this->organizationId);
        if ($organization === null) {
            return;
        }

        $this->updateCurrentSource([
            'status' => OrganizationStatus::Failed,
            'sync_error' => $exception?->getMessage() ?? 'Неизвестная ошибка синхронизации.',
        ]);

        Log::error('Синхронизация организации с Яндекс.Картами завершилась ошибкой.', [
            'organization_id' => $this->organizationId,
            'exception' => $exception,
        ]);
    }

    private function persist(ParsedOrganization $parsed): void
    {
        DB::transaction(function () use ($parsed): void {
            $organization = Organization::query()
                ->lockForUpdate()
                ->findOrFail($this->organizationId);

            if ($organization->source_url !== $this->sourceUrl) {
                return;
            }

            $previous = [
                'name' => $organization->name,
                'rating' => $organization->rating === null ? null : (float) $organization->rating,
                'ratings_count' => $organization->ratings_count,
                'reviews_count' => $organization->reviews_count,
            ];

            $now = now();
            $reviewRows = array_map(
                static fn (ParsedReview $review): array => [
                    'organization_id' => $organization->id,
                    'source_review_id' => $review->id,
                    'author_name' => $review->authorName,
                    'author_avatar_url' => $review->authorAvatarUrl,
                    'published_at' => $review->publishedAt,
                    'text' => $review->text,
                    'rating' => $review->rating,
                    'source_updated_at' => $review->updatedAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $parsed->reviews,
            );

            if ($reviewRows !== []) {
                Review::query()->upsert(
                    $reviewRows,
                    ['organization_id', 'source_review_id'],
                    ['author_name', 'author_avatar_url', 'published_at', 'text', 'rating', 'source_updated_at', 'updated_at'],
                );
            }

            $sourceReviewIds = array_column($reviewRows, 'source_review_id');
            $staleReviews = $organization->reviews();
            if ($sourceReviewIds !== []) {
                $staleReviews->whereNotIn('source_review_id', $sourceReviewIds);
            }
            $staleReviews->delete();

            $current = [
                'name' => $parsed->name,
                'rating' => $parsed->rating,
                'ratings_count' => $parsed->ratingsCount,
                'reviews_count' => $parsed->reviewsCount,
            ];

            $organization->update([
                'external_id' => $parsed->externalId,
                'canonical_url' => $parsed->canonicalUrl,
                ...$current,
                'status' => OrganizationStatus::Complete,
                'progress' => 100,
                'last_synced_at' => $now,
                'sync_error' => null,
            ]);

            $changes = [];
            foreach ($current as $key => $value) {
                if ($previous[$key] !== null && $previous[$key] != $value) {
                    $changes[$key] = ['from' => $previous[$key], 'to' => $value];
                }
            }

            $hasSnapshot = OrganizationSnapshot::query()->whereBelongsTo($organization)->exists();
            if (! $hasSnapshot || $changes !== []) {
                OrganizationSnapshot::query()->create([
                    'organization_id' => $organization->id,
                    'captured_at' => $now,
                    ...$current,
                    'changes' => $changes === [] ? null : $changes,
                ]);
            }
        });
    }

    /** @param array<string, mixed> $attributes */
    private function updateCurrentSource(array $attributes): void
    {
        Organization::query()
            ->whereKey($this->organizationId)
            ->where('source_url', $this->sourceUrl)
            ->update($attributes);
    }
}
