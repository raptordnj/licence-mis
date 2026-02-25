<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\EnvatoItemStatus;
use App\Enums\Marketplace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnvatoItemRequest extends FormRequest
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
            'marketplace' => ['required', Rule::enum(Marketplace::class)],
            'envato_item_id' => ['required', 'integer', 'min:1', 'unique:envato_items,envato_item_id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(EnvatoItemStatus::class)],
        ];
    }
}
