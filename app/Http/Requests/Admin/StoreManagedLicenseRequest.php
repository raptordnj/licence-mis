<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\LicenseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagedLicenseRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'purchase_code' => ['required', 'string', 'max:255', 'unique:licenses,purchase_code'],
            'buyer' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([
                LicenseStatus::VALID->value,
                LicenseStatus::INVALID->value,
                LicenseStatus::REVOKED->value,
                LicenseStatus::REFUNDED->value,
                LicenseStatus::CHARGEBACK->value,
            ])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
