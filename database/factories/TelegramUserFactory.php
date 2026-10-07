<?php

namespace Database\Factories;

use App\Enums\ConversationState;
use App\Enums\Language;
use App\Models\TelegramUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramUser>
 */
class TelegramUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_id' => fake()->unique()->numberBetween(100000, 999999999),
            'username' => fake()->userName(),
            'first_name' => fake()->firstName(),
            'language' => Language::Uz,
            'state' => ConversationState::Idle,
            'draft' => null,
        ];
    }
}
