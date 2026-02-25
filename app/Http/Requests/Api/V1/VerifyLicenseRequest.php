<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class VerifyLicenseRequest extends FormRequest
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
            'purchase_code' => ['required', 'string', 'max:120'],
            'domain' => ['required', 'string', 'max:255'],
            'item_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
