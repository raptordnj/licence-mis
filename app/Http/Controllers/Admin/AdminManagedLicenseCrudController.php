<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ApiErrorCode;
use App\Enums\LicenseStatus;
use App\Enums\Marketplace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreManagedLicenseRequest;
use App\Http\Requests\Admin\UpdateManagedLicenseStatusRequest;
use App\Models\License;
use App\Models\Product;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminManagedLicenseCrudController extends Controller
{
    public function store(StoreManagedLicenseRequest $request): JsonResponse
    {
        if (! $this->isAdminUser($request->user())) {
            return ApiResponse::error(ApiErrorCode::FORBIDDEN, 'Admin access is required.', 403);
        }

        $validated = $request->validated();
        $product = Product::query()->findOrFail((int) data_get($validated, 'product_id'));
        $metadata = data_get($validated, 'metadata');

        $license = License::query()->create([
            'product_id' => $product->id,
            'purchase_code' => (string) data_get($validated, 'purchase_code'),
            'buyer' => data_get($validated, 'buyer') !== null ? (string) data_get($validated, 'buyer') : null,
            'marketplace' => Marketplace::ENVATO,
            'envato_item_id' => $product->envato_item_id,
            'status' => (string) data_get($validated, 'status', LicenseStatus::VALID->value),
            'notes' => data_get($validated, 'notes') !== null ? (string) data_get($validated, 'notes') : null,
            'metadata' => is_array($metadata) ? $metadata : [],
        ]);

        return ApiResponse::success($license->toArray(), 201);
    }

    public function setStatus(UpdateManagedLicenseStatusRequest $request, License $license): JsonResponse
    {
        if (! $this->isAdminUser($request->user())) {
            return ApiResponse::error(ApiErrorCode::FORBIDDEN, 'Admin access is required.', 403);
        }

        $validated = $request->validated();

        $license->forceFill([
            'status' => (string) data_get($validated, 'status'),
            'notes' => data_get($validated, 'notes') !== null
                ? (string) data_get($validated, 'notes')
                : $license->notes,
        ])->save();

        return ApiResponse::success($license->refresh()->toArray());
    }

    public function index(Request $request): JsonResponse
    {
        if (! $this->isAdminUser($request->user())) {
            return ApiResponse::error(ApiErrorCode::FORBIDDEN, 'Admin access is required.', 403);
        }

        $licenses = License::query()
            ->whereNotNull('product_id')
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 20));

        return ApiResponse::success($licenses->toArray());
    }

    private function isAdminUser(mixed $user): bool
    {
        return $user instanceof User && $user->isAdmin();
    }
}
