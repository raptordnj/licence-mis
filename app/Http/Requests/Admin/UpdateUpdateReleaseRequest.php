<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUpdateReleaseRequest extends FormRequest
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
        $maxMb = max(1, (int) config('update_releases.max_package_size_mb', 300));
        $semanticVersion = 'regex:/^\d+\.\d+(?:\.\d+)?(?:[-+][0-9A-Za-z.\-]+)?$/';

        return [
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'envato_item_id' => ['nullable', 'integer', 'exists:envato_items,envato_item_id'],
            'channel' => ['sometimes', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/'],
            'version' => ['sometimes', 'string', 'max:120', $semanticVersion],
            'min_version' => ['nullable', 'string', 'max:120', $semanticVersion],
            'max_version' => ['nullable', 'string', 'max:120', $semanticVersion],
            'release_notes' => ['nullable', 'string'],
            'is_published' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'package' => ['sometimes', 'file', 'mimes:zip', 'max:'.($maxMb * 1024)],
        ];
    }
}
