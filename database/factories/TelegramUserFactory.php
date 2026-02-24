<?php

namespace Database\Factories;

use App\Models\TelegramUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TelegramUser>
 */
class TelegramUserFactory extends Factory
{
    protected $model = TelegramUser::class;

    public function definition(): array
    {
        return [
            'user_id' => $this->faker->unique()->numberBetween(100000, 999999),
            'username' => $this->faker->unique()->userName(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'last_interaction_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'level' => 2,
            'state' => 'normal',
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['level' => 1]);
    }
}
