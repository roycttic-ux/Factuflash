<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'sku' => 'SKU-' . fake()->unique()->numerify('#####'),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'cost_usd' => fake()->randomFloat(4, 5, 100),
            'margin_percent' => 30.00,
            'recommended_price' => fake()->randomFloat(2, 10, 150),
            'stock' => fake()->numberBetween(10, 100),
            'unlimited_stock' => false,
            'min_stock_alert' => 5,
            'category' => fake()->word(),
            'tags' => 'generic',
            'images' => [],
            'variants' => [],
        ];
    }
}
