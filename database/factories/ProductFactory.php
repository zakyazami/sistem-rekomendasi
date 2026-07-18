<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'sku' => 'BRK-'.fake()->unique()->bothify('########????'),
            'name' => fake()->unique()->words(3, true),
            'moving_type' => fake()->randomElement(['FAST', 'MEDIUM', 'SLOW', 'VERY_SLOW']),
            'minimum_stock' => fake()->numberBetween(0, 30),
            'on_order_quantity' => 0,
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
