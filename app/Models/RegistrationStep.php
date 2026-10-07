<?php

namespace App\Models;

use App\Enums\StepSection;
use App\Enums\StepType;
use Database\Factories\RegistrationStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'type', 'section', 'label', 'question', 'options', 'is_required', 'is_active', 'is_core', 'position'])]
class RegistrationStep extends Model
{
    /** @use HasFactory<RegistrationStepFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StepType::class,
            'section' => StepSection::class,
            'label' => 'array',
            'question' => 'array',
            'options' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'is_core' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @param  Builder<RegistrationStep>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<RegistrationStep>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByRaw("case when section = 'registration' then 0 else 1 end")->orderBy('position')->orderBy('id');
    }

    public function questionFor(string $locale): string
    {
        return $this->translated($this->question, $locale);
    }

    public function labelFor(string $locale): string
    {
        return $this->translated($this->label, $locale);
    }

    /**
     * Button texts for a choice step in the given language, falling back to English.
     *
     * @return list<string>
     */
    public function optionsFor(string $locale): array
    {
        return array_values($this->options[$locale] ?? $this->options['en'] ?? []);
    }

    /**
     * @param  array<string, string>|null  $translations
     */
    private function translated(?array $translations, string $locale): string
    {
        return (string) ($translations[$locale] ?? $translations['en'] ?? $translations['uz'] ?? (reset($translations) ?: ''));
    }
}
