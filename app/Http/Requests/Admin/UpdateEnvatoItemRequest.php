<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\EnvatoItemStatus;
use App\Enums\Marketplace;
use App\Models\EnvatoItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnvatoItemRequest extends FormRequest
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
        $envatoItem = $this->route('envatoItem');
        $envatoItemId = $envatoItem instanceof EnvatoItem ? $envatoItem->id : null;

        return [
            'marketplace' => ['required', Rule::enum(Marketplace::class)],
            'envato_item_id' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('envato_items', 'envato_item_id')->ignore($envatoItemId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(EnvatoItemStatus::class)],
        ];
    }
}
