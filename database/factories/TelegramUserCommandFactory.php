<?php

namespace Database\Factories;

use App\Models\TelegramUserCommand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TelegramUserCommand>
 */
class TelegramUserCommandFactory extends Factory
{
    protected $model = TelegramUserCommand::class;

    public function definition(): array
    {
        return [
            'user_id' => $this->faker->numberBetween(100000, 999999),
            'command' => $this->faker->randomElement(['/start', '/help', '/stats']),
        ];
    }
}
