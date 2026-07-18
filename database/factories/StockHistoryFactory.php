<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockHistory>
 */
class StockHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $initial = fake()->numberBetween(10, 100);
        $incoming = fake()->numberBetween(0, 20);
        $outgoing = fake()->numberBetween(0, $initial + $incoming);

        return [
            'product_id' => Product::factory(),
            'date' => fake()->unique()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'initial_stock' => $initial,
            'incoming_stock' => $incoming,
            'outgoing_stock' => $outgoing,
            'final_stock' => $initial + $incoming - $outgoing,
        ];
    }
}
