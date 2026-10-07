<?php

namespace App\Http\Requests\Admin;

use App\Models\Setting;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanExamplesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'examples' => ['nullable', 'array'],
            'examples.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove' => ['nullable', 'array'],
            'remove.*' => ['integer'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $remaining = count(array_diff(array_keys(Setting::planExamples()), array_map('intval', (array) $this->input('remove'))));
            $total = $remaining + count((array) $this->file('examples'));

            if ($total > Setting::PLAN_EXAMPLE_LIMIT) {
                $validator->errors()->add('examples', 'You can keep at most '.Setting::PLAN_EXAMPLE_LIMIT.' example images (Telegram sends them as one album).');
            }
        }];
    }
}
