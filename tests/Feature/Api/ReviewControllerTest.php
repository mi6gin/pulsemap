<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_when_user_is_not_authenticated(): void
    {
        $response = $this->getJson('/api/organization/reviews');

        $response->assertUnauthorized();
    }

    public function test_returns_users_reviews_in_pages_of_50_with_stable_order(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create();
        Review::factory()
            ->count(55)
            ->for($organization)
            ->sequence(fn (Sequence $sequence): array => [
                'published_at' => now()->subDays($sequence->index),
            ])
            ->create();

        $response = $this->actingAs($user)->getJson('/api/organization/reviews?page=2');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 55);
    }

    public function test_response_does_not_include_reviews_from_another_user(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create();
        Review::factory()->for($organization)->create(['source_review_id' => 'owned-review']);
        Review::factory()->create(['source_review_id' => 'foreign-review']);

        $response = $this->actingAs($user)->getJson('/api/organization/reviews');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'owned-review')
            ->assertJsonMissing(['id' => 'foreign-review']);
    }
}
