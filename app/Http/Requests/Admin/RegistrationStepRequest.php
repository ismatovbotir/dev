<?php

namespace App\Http\Requests\Admin;

use App\Enums\Language;
use App\Enums\StepSection;
use App\Enums\StepType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrationStepRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_required' => $this->boolean('is_required'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'type' => [$this->isCore() ? 'prohibited' : 'required', Rule::enum(StepType::class)],
            'section' => [$this->isCore() ? 'prohibited' : 'required', Rule::enum(StepSection::class)],
            'is_required' => ['boolean'],
            'is_active' => ['boolean'],
        ];

        foreach (Language::cases() as $language) {
            $rules["label.{$language->value}"] = ['required', 'string', 'max:60'];
            $rules["question.{$language->value}"] = ['required', 'string', 'max:800'];
            $rules["options.{$language->value}"] = [
                Rule::requiredIf(fn () => $this->expectsOptions()),
                'nullable',
                'string',
                'max:2000',
            ];
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->expectsOptions() || $validator->errors()->has('options.*')) {
                return;
            }

            $lists = collect($this->optionMap());
            $counts = $lists->map(fn (array $list) => count($list));

            if ($counts->unique()->count() > 1) {
                $validator->errors()->add('options', 'Every language needs the same number of options, in the same order.');
            } elseif ($lists->flatten()->contains(fn (string $option) => mb_strlen($option) > 60) || $counts->max() > 20) {
                $validator->errors()->add('options', 'Use at most 20 options of 60 characters each.');
            }
        }];
    }

    /**
     * Button texts per language, one option per line.
     *
     * @return array<string, list<string>>
     */
    public function optionMap(): array
    {
        $map = [];

        foreach (Language::cases() as $language) {
            $lines = preg_split('/\R/', (string) $this->input("options.{$language->value}")) ?: [];
            $map[$language->value] = array_values(array_unique(array_filter(array_map('trim', $lines))));
        }

        return $map;
    }

    private function isCore(): bool
    {
        return (bool) $this->route('registrationStep')?->is_core;
    }

    private function expectsOptions(): bool
    {
        return ! $this->isCore() && $this->input('type') === StepType::Choice->value;
    }
}
