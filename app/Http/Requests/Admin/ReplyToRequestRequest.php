<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplyToRequestRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'drawing' => [
                Rule::requiredIf(fn () => $this->route('shopRequest')->drawing_path === null),
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:10240',
            ],
            'admin_comment' => ['nullable', 'string', 'max:600'],
        ];
    }
}
