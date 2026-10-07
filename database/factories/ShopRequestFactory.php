<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\ShopRequest;
use App\Models\TelegramUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopRequest>
 */
class ShopRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'telegram_user_id' => TelegramUser::factory(),
            'phone' => '+998'.fake()->numerify('#########'),
            'name' => fake()->name(),
            'location_text' => fake()->streetAddress(),
            'latitude' => fake()->latitude(39, 42),
            'longitude' => fake()->longitude(64, 70),
            'brand' => fake()->company(),
            'status' => RequestStatus::New,
        ];
    }
}
