<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAdminSettingsRequest extends FormRequest
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
            'envato_api_token' => [
                'nullable',
                'string',
                'min:10',
                'max:4096',
            ],
            'license_hmac_key' => [
                'nullable',
                'string',
                'min:32',
                'max:255',
            ],
            'envato_mock_mode' => [
                'sometimes',
                'boolean',
            ],
            'rate_limit_per_minute' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100000',
            ],
            'domain_policies' => [
                'sometimes',
                'array',
            ],
            'domain_policies.treat_www_as_same' => [
                'sometimes',
                'boolean',
            ],
            'domain_policies.allow_localhost' => [
                'sometimes',
                'boolean',
            ],
            'domain_policies.allow_ip_domains' => [
                'sometimes',
                'boolean',
            ],
            'reset_policies' => [
                'sometimes',
                'array',
            ],
            'reset_policies.max_resets_allowed' => [
                'sometimes',
                'integer',
                'min:0',
                'max:100000',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $input = $this->all();
            $hasAnySettingField = array_key_exists('envato_api_token', $input)
                || array_key_exists('license_hmac_key', $input)
                || array_key_exists('envato_mock_mode', $input)
                || array_key_exists('rate_limit_per_minute', $input)
                || array_key_exists('domain_policies', $input)
                || array_key_exists('reset_policies', $input);

            if (
                ! $hasAnySettingField
            ) {
                $validator->errors()->add(
                    'settings',
                    'At least one setting must be provided.',
                );
            }
        });
    }
}
