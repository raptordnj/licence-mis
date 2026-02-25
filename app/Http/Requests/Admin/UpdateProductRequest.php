<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : null;

        return [
            'envato_item_id' => ['required', 'integer', 'min:1', Rule::unique('products', 'envato_item_id')->ignore($productId)],
            'name' => ['required', 'string', 'max:255'],
            'activation_limit' => ['required', 'integer', 'min:1', 'max:20'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'strict_domain_binding' => ['required', 'boolean'],
        ];
    }
}
