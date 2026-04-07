<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidationLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'result' => ['nullable', Rule::in(['success', 'fail'])],
            'fail_reason' => ['nullable', 'string', 'max:120'],
            'item' => ['nullable', 'string', 'max:120'],
            'purchase_code' => ['nullable', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:253'],
            'ip' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
