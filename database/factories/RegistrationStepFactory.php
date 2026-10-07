<?php

namespace Database\Factories;

use App\Enums\StepSection;
use App\Enums\StepType;
use App\Models\RegistrationStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationStep>
 */
class RegistrationStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = fake()->unique()->word();

        return [
            'key' => 'custom_'.fake()->unique()->bothify('??????'),
            'type' => StepType::Text,
            'section' => StepSection::Conversation,
            'label' => ['uz' => $word, 'ru' => $word, 'en' => $word],
            'question' => ['uz' => "{$word}?", 'ru' => "{$word}?", 'en' => "{$word}?"],
            'options' => null,
            'is_required' => true,
            'is_active' => true,
            'is_core' => false,
            'position' => fake()->numberBetween(10, 99),
        ];
    }

    public function choice(string ...$options): static
    {
        return $this->state(['type' => StepType::Choice, 'options' => ['uz' => array_values($options), 'ru' => array_values($options), 'en' => array_values($options)]]);
    }

    public function registration(): static
    {
        return $this->state(['section' => StepSection::Registration, 'position' => 5]);
    }

    public function optional(): static
    {
        return $this->state(['is_required' => false]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
