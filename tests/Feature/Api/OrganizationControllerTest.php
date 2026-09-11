<?php

namespace Tests\Feature\Api;

use App\Jobs\SyncOrganization;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrganizationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_when_user_is_not_authenticated(): void
    {
        $response = $this->getJson('/api/organization');

        $response->assertUnauthorized();
    }

    public function test_valid_url_is_saved_and_sync_job_is_dispatched(): void
    {
        Queue::fake([SyncOrganization::class]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/organization', [
            'url' => 'https://yandex.ru/maps/org/test/1234567890/',
            'status' => 'complete',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.source_url', 'https://yandex.ru/maps/org/test/1234567890/')
            ->assertJsonPath('data.status', 'queued');
        $this->assertDatabaseHas('organizations', [
            'user_id' => $user->id,
            'source_url' => 'https://yandex.ru/maps/org/test/1234567890/',
            'status' => 'queued',
        ]);
        Queue::assertPushed(
            SyncOrganization::class,
            fn (SyncOrganization $job): bool => $job->organizationId === $user->organization->id
                && $job->sourceUrl === 'https://yandex.ru/maps/org/test/1234567890/'
                && $job->queue === null,
        );
    }

    public function test_returns_422_for_non_yandex_url_without_dispatching_job(): void
    {
        Queue::fake([SyncOrganization::class]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/organization', [
            'url' => 'https://example.com/maps/org/test/1234567890/',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['url'])
            ->assertJsonPath('errors.url.0', 'Поддерживаются только HTTPS-ссылки на карточки в Яндекс.Картах.');
        $this->assertDatabaseCount('organizations', 0);
        Queue::assertNothingPushed();
    }

    public function test_user_cannot_see_another_users_organization(): void
    {
        Organization::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/organization');

        $response->assertOk()->assertJsonPath('data', null);
    }

    public function test_returns_source_and_stored_review_counts_separately(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create([
            'reviews_count' => 1758,
        ]);
        Review::factory()->count(3)->for($organization)->create();

        $response = $this->actingAs($user)->getJson('/api/organization');

        $response->assertOk()
            ->assertJsonPath('data.reviews_count', 1758)
            ->assertJsonPath('data.stored_reviews_count', 3);
    }
}
