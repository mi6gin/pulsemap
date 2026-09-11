<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'source_review_id' => fake()->unique()->uuid(),
            'author_name' => fake()->name(),
            'author_avatar_url' => fake()->optional()->imageUrl(96, 96),
            'published_at' => fake()->dateTimeBetween('-2 years'),
            'text' => fake()->paragraph(),
            'rating' => fake()->numberBetween(1, 5),
            'source_updated_at' => fake()->dateTimeBetween('-2 years'),
        ];
    }
}
