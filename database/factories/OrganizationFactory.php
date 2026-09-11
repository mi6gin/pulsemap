<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\OrganizationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'external_id' => (string) fake()->unique()->numberBetween(1000000000, 9999999999),
            'source_url' => 'https://yandex.ru/maps/org/example/1234567890/',
            'canonical_url' => 'https://yandex.ru/maps/org/example/1234567890/',
            'name' => fake()->company(),
            'rating' => fake()->randomFloat(2, 1, 5),
            'ratings_count' => fake()->numberBetween(10, 5000),
            'reviews_count' => fake()->numberBetween(5, 600),
            'status' => OrganizationStatus::Complete,
            'progress' => 100,
            'last_synced_at' => now(),
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes): array => [
            'external_id' => null,
            'canonical_url' => null,
            'name' => null,
            'rating' => null,
            'ratings_count' => 0,
            'reviews_count' => 0,
            'status' => OrganizationStatus::Queued,
            'progress' => 0,
            'last_synced_at' => null,
        ]);
    }
}
