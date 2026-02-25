<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'envato_item_id' => ['required', 'integer', 'min:1', 'unique:products,envato_item_id'],
            'name' => ['required', 'string', 'max:255'],
            'activation_limit' => ['required', 'integer', 'min:1', 'max:20'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'strict_domain_binding' => ['sometimes', 'boolean'],
        ];
    }
}
