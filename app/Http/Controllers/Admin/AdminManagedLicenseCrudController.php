<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ApiErrorCode;
use App\Enums\LicenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreManagedLicenseRequest;
use App\Http\Requests\Admin\UpdateManagedLicenseStatusRequest;
use App\Models\License;
use App\Models\Product;
use App\Models\User;
use App\Repositories\LicenseManagerLicenseRepository;
use App\Services\Contracts\EnvatoPurchaseValidatorInterface;
use App\Services\PurchaseCodeNormalizer;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AdminManagedLicenseCrudController extends Controller
{
    public function store(
        StoreManagedLicenseRequest $request,
        EnvatoPurchaseValidatorInterface $purchaseValidator,
        LicenseManagerLicenseRepository $licenseRepository,
        PurchaseCodeNormalizer $purchaseCodeNormalizer,
    ): JsonResponse
    {
        if (! $this->isAdminUser($request->user())) {
            return ApiResponse::error(ApiErrorCode::FORBIDDEN, 'Admin access is required.', 403);
        }

        $validated = $request->validated();
        $product = Product::query()->findOrFail((int) data_get($validated, 'product_id'));
        $metadata = is_array(data_get($validated, 'metadata')) ? (array) data_get($validated, 'metadata') : [];

        try {
            $purchaseCode = $purchaseCodeNormalizer->normalize((string) data_get($validated, 'purchase_code'));
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error(ApiErrorCode::VALIDATION_ERROR, $exception->getMessage(), 422);
        }

        if ($licenseRepository->findByPurchaseCode($purchaseCode) instanceof License) {
            return ApiResponse::error(ApiErrorCode::VALIDATION_ERROR, 'The purchase code has already been taken.', 422);
        }

        $validationResult = $purchaseValidator->validate(
            purchaseCode: $purchaseCode,
            envatoItemId: $product->envato_item_id,
            productId: $product->id,
        );

        if (! $validationResult->valid) {
            return ApiResponse::error(
                ApiErrorCode::PURCHASE_INVALID,
                'Purchase code is not valid for the selected product.',
                422,
            );
        }

        $license = $licenseRepository->createFromValidation(
            purchaseCode: $purchaseCode,
            product: $product,
            validationResult: $validationResult,
        );

        /** @var array<string, mixed> $existingMetadata */
        $existingMetadata = is_array($license->metadata) ? $license->metadata : [];

        $license->forceFill([
            'buyer' => data_get($validated, 'buyer') !== null
                ? (string) data_get($validated, 'buyer')
                : $license->buyer,
            'status' => (string) data_get($validated, 'status', LicenseStatus::VALID->value),
            'notes' => data_get($validated, 'notes') !== null
                ? (string) data_get($validated, 'notes')
                : $license->notes,
            'metadata' => $metadata !== [] ? array_replace_recursive($existingMetadata, $metadata) : $existingMetadata,
        ])->save();

        return ApiResponse::success($license->refresh()->toArray(), 201);
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
