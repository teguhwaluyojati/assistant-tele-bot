<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id' => $this->faker->numberBetween(100000, 999999),
            'type' => $this->faker->randomElement(['income', 'expense']),
            'amount' => $this->faker->numberBetween(1000, 500000),
            'description' => $this->faker->sentence(3),
            'created_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'updated_at' => now(),
        ];
    }
}
