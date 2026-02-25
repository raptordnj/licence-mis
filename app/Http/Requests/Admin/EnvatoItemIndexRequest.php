<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\EnvatoItemStatus;
use App\Enums\Marketplace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnvatoItemIndexRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:120'],
            'marketplace' => ['nullable', Rule::enum(Marketplace::class)],
            'status' => ['nullable', Rule::enum(EnvatoItemStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
